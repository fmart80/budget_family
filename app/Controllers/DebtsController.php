<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class DebtsController
{
    private function find(?int $id): array
    {
        $stmt = db()->prepare('SELECT * FROM debts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: abort(404, 'Dette introuvable');
    }

    public function index(): void
    {
        $debts = db()->query('SELECT * FROM debts ORDER BY is_closed, name')->fetchAll();
        $remaining = Finance::debtRemaining();
        $open = array_values(array_filter($debts, fn ($d) => !$d['is_closed'] && ($remaining[$d['id']] ?? 0) > 0));

        $avalanche = $open;
        usort($avalanche, fn ($a, $b) => $b['interest_rate'] <=> $a['interest_rate']);
        $snowball = $open;
        usort($snowball, fn ($a, $b) => ($remaining[$a['id']] ?? 0) <=> ($remaining[$b['id']] ?? 0));

        view('debts/index', [
            'title'     => 'Dettes',
            'debts'     => $debts,
            'remaining' => $remaining,
            'avalanche' => $avalanche,
            'snowball'  => $snowball,
        ]);
    }

    public function show(): void
    {
        $debt = $this->find(int_input('id'));
        $stmt = db()->prepare('SELECT * FROM debt_payments WHERE debt_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$debt['id']]);
        $remaining = Finance::debtRemaining()[$debt['id']] ?? 0.0;
        $rate = (float) $debt['interest_rate'];

        // Simulateur : mensualité, versements supplémentaires certains mois (13e salaire…), objectif de durée.
        $sim = [
            'payment' => input('sim_payment') !== '' ? abs(parse_amount(input('sim_payment'))) : (float) $debt['monthly_payment'],
            'extra'   => abs(parse_amount(input('sim_extra'))),
            'months'  => array_values(array_filter(array_map('intval', (array) ($_GET['sim_months'] ?? [])), fn ($m) => $m >= 1 && $m <= 12)),
            'target'  => max(0, min(240, (int) input('sim_target'))),
        ];
        $extras = $sim['extra'] > 0 ? array_fill_keys($sim['months'], $sim['extra']) : [];
        $current = Finance::payoffSummary($remaining, $rate, (float) $debt['monthly_payment']);
        $scenarios = [];
        foreach (array_unique([100, 150, 200, 250, 300, 400, 500, 750, 1000, (int) $debt['monthly_payment']]) as $p) {
            if ($p > 0 && $p >= $remaining * $rate / 1200 && $p <= max($remaining, 1) * 1.05) {
                $scenarios[$p] = Finance::payoffSummary($remaining, $rate, (float) $p);
            }
        }
        ksort($scenarios);

        view('debts/show', [
            'title'     => $debt['name'],
            'debt'      => $debt,
            'payments'  => $stmt->fetchAll(),
            'remaining' => $remaining,
            'schedule'  => Finance::amortization($remaining, $rate, $sim['payment'], 600, $extras),
            'accounts'  => all_accounts(),
            'sim'       => $sim,
            'simResult' => Finance::payoffSummary($remaining, $rate, $sim['payment'], $extras),
            'current'   => $current,
            'scenarios' => $scenarios,
            'targetPayment' => $sim['target'] ? Finance::paymentForMonths($remaining, $rate, $sim['target']) : null,
        ]);
    }

    public function edit(): void
    {
        $debt = ['id' => null, 'name' => '', 'creditor' => '', 'initial_amount' => '', 'interest_rate' => '0',
            'monthly_payment' => '', 'start_date' => date('Y-m-d'), 'notes' => '', 'is_closed' => 0];
        if ($id = int_input('id')) {
            $debt = $this->find($id);
        }
        view('debts/edit', ['title' => 'Dette', 'debt' => $debt]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $data = [
            (string) input('name'),
            input('creditor') ?: null,
            abs(parse_amount(input('initial_amount'))),
            max(0, min(99.99, parse_amount(input('interest_rate')))),
            abs(parse_amount(input('monthly_payment'))),
            valid_date((string) input('start_date')) ?? date('Y-m-d'),
            input('notes') ?: null,
            isset($_POST['is_closed']) ? 1 : 0,
        ];
        if ($data[0] === '' || $data[2] <= 0) {
            flash('danger', 'Le nom et le montant emprunté sont obligatoires.');
            redirect('debts/edit', $id ? ['id' => $id] : []);
        }
        if ($id) {
            db()->prepare('UPDATE debts SET name=?, creditor=?, initial_amount=?, interest_rate=?, monthly_payment=?, start_date=?, notes=?, is_closed=? WHERE id=?')
                ->execute([...$data, $id]);
        } else {
            db()->prepare('INSERT INTO debts (name, creditor, initial_amount, interest_rate, monthly_payment, start_date, notes, is_closed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute($data);
            $id = (int) db()->lastInsertId();
        }
        flash('success', 'Dette enregistrée.');
        redirect('debts/show', ['id' => $id]);
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM debts WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Dette supprimée.');
        redirect('debts');
    }

    /** Enregistre un remboursement (montant négatif = intérêts / frais ajoutés à la dette). */
    public function payment(): void
    {
        $debt = $this->find(int_input('debt_id'));
        $amount = parse_amount(input('amount'));
        $date = valid_date((string) input('date')) ?? date('Y-m-d');
        if ($amount == 0.0) {
            flash('danger', 'Montant invalide.');
            redirect('debts/show', ['id' => $debt['id']]);
        }

        db()->beginTransaction();
        $txId = null;
        if ($amount > 0 && ($accountId = int_input('account_id'))) {
            $txId = Finance::addTransaction(['account_id' => $accountId, 'type' => 'expense', 'amount' => $amount,
                'date' => $date, 'category_id' => Finance::categoryIdByName('Remboursement de dettes'),
                'description' => 'Remboursement : ' . $debt['name']]);
        }
        db()->prepare('INSERT INTO debt_payments (debt_id, date, amount, notes, transaction_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$debt['id'], $date, $amount, input('notes') ?: null, $txId]);

        if ((Finance::debtRemaining()[$debt['id']] ?? 1) <= 0) {
            db()->prepare('UPDATE debts SET is_closed = 1 WHERE id = ?')->execute([$debt['id']]);
            flash('success', '🎉 Félicitations, cette dette est entièrement remboursée !');
        } else {
            flash('success', 'Remboursement enregistré.');
        }
        db()->commit();
        redirect('debts/show', ['id' => $debt['id']]);
    }

    public function deletePayment(): void
    {
        $stmt = db()->prepare('SELECT * FROM debt_payments WHERE id = ?');
        $stmt->execute([int_input('id')]);
        $payment = $stmt->fetch() ?: abort(404);
        db()->beginTransaction();
        db()->prepare('DELETE FROM debt_payments WHERE id = ?')->execute([$payment['id']]);
        if ($payment['transaction_id'] && isset($_POST['delete_transaction'])) {
            db()->prepare('DELETE FROM transactions WHERE id = ?')->execute([$payment['transaction_id']]);
        }
        db()->prepare('UPDATE debts SET is_closed = 0 WHERE id = ?')->execute([$payment['debt_id']]);
        db()->commit();
        flash('success', 'Remboursement supprimé.');
        redirect('debts/show', ['id' => $payment['debt_id']]);
    }
}
