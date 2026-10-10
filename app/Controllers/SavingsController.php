<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class SavingsController
{
    private function find(?int $id): array
    {
        $stmt = db()->prepare('SELECT g.*, a.name AS account_name FROM savings_goals g LEFT JOIN accounts a ON a.id = g.account_id WHERE g.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: abort(404, 'Objectif introuvable');
    }

    /** Montant mensuel à épargner pour atteindre l'objectif à la date visée. */
    public static function monthlyNeeded(array $goal, float $saved): ?float
    {
        if (!$goal['target_date']) {
            return null;
        }
        $left = (float) $goal['target_amount'] - $saved;
        if ($left <= 0) {
            return 0.0;
        }
        $now = new \DateTimeImmutable('first day of this month');
        $target = new \DateTimeImmutable($goal['target_date']);
        $diff = $now->diff($target);
        $months = $diff->invert ? 0 : $diff->y * 12 + $diff->m + 1;
        return $months > 0 ? round($left / $months, 2) : $left;
    }

    public function index(): void
    {
        view('savings/index', [
            'title' => 'Épargne',
            'goals' => db()->query('SELECT g.*, a.name AS account_name FROM savings_goals g
                LEFT JOIN accounts a ON a.id = g.account_id ORDER BY g.target_date IS NULL, g.target_date, g.name')->fetchAll(),
            'saved' => Finance::savedByGoal(),
        ]);
    }

    public function show(): void
    {
        $goal = $this->find(int_input('id'));
        $stmt = db()->prepare('SELECT * FROM savings_contributions WHERE goal_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$goal['id']]);
        view('savings/show', [
            'title'         => $goal['name'],
            'goal'          => $goal,
            'contributions' => $stmt->fetchAll(),
            'saved'         => Finance::savedByGoal()[$goal['id']] ?? 0.0,
            'accounts'      => all_accounts(),
        ]);
    }

    public function edit(): void
    {
        $goal = ['id' => null, 'name' => '', 'target_amount' => '', 'target_date' => '', 'account_id' => null,
            'color' => '#198754', 'notes' => ''];
        if ($id = int_input('id')) {
            $goal = $this->find($id);
        }
        view('savings/edit', ['title' => 'Objectif d\'épargne', 'goal' => $goal, 'accounts' => all_accounts()]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $data = [
            (string) input('name'),
            abs(parse_amount(input('target_amount'))),
            valid_date((string) input('target_date')),
            int_input('account_id'),
            preg_match('/^#[0-9a-fA-F]{6}$/', (string) input('color')) ? input('color') : '#198754',
            input('notes') ?: null,
        ];
        if ($data[0] === '' || $data[1] <= 0) {
            flash('danger', 'Le nom et le montant visé sont obligatoires.');
            redirect('savings/edit', $id ? ['id' => $id] : []);
        }
        if ($id) {
            db()->prepare('UPDATE savings_goals SET name=?, target_amount=?, target_date=?, account_id=?, color=?, notes=? WHERE id=?')
                ->execute([...$data, $id]);
        } else {
            db()->prepare('INSERT INTO savings_goals (name, target_amount, target_date, account_id, color, notes) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute($data);
            $id = (int) db()->lastInsertId();
            if (($initial = abs(parse_amount(input('initial_amount')))) > 0) {
                db()->prepare('INSERT INTO savings_contributions (goal_id, date, amount, notes) VALUES (?, ?, ?, ?)')
                    ->execute([$id, date('Y-m-d'), $initial, 'Montant déjà épargné']);
            }
        }
        flash('success', 'Objectif enregistré.');
        redirect('savings/show', ['id' => $id]);
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM savings_goals WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Objectif supprimé.');
        redirect('savings');
    }

    /** Versement (ou retrait si négatif). Peut générer un virement vers le compte épargne lié. */
    public function contribute(): void
    {
        $goal = $this->find(int_input('goal_id'));
        $amount = parse_amount(input('amount'));
        $date = valid_date((string) input('date')) ?? date('Y-m-d');
        if ($amount == 0.0) {
            flash('danger', 'Montant invalide.');
            redirect('savings/show', ['id' => $goal['id']]);
        }
        db()->beginTransaction();
        db()->prepare('INSERT INTO savings_contributions (goal_id, date, amount, notes) VALUES (?, ?, ?, ?)')
            ->execute([$goal['id'], $date, $amount, input('notes') ?: null]);

        $from = int_input('from_account_id');
        if ($from && $goal['account_id'] && $from !== (int) $goal['account_id']) {
            [$src, $dst] = $amount > 0 ? [$from, (int) $goal['account_id']] : [(int) $goal['account_id'], $from];
            Finance::addTransaction(['account_id' => $src, 'to_account_id' => $dst, 'type' => 'transfer',
                'amount' => abs($amount), 'date' => $date, 'description' => 'Épargne : ' . $goal['name']]);
        }
        db()->commit();

        $saved = Finance::savedByGoal()[$goal['id']] ?? 0;
        flash('success', $saved >= $goal['target_amount'] && $amount > 0
            ? '🎉 Objectif atteint, bravo !' : 'Versement enregistré.');
        redirect('savings/show', ['id' => $goal['id']]);
    }

    public function deleteContribution(): void
    {
        $stmt = db()->prepare('SELECT goal_id FROM savings_contributions WHERE id = ?');
        $stmt->execute([int_input('id')]);
        $goalId = $stmt->fetchColumn() ?: abort(404);
        db()->prepare('DELETE FROM savings_contributions WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Versement supprimé (pensez à supprimer le virement associé s\'il y en a un).');
        redirect('savings/show', ['id' => $goalId]);
    }
}
