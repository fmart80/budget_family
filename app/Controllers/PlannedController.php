<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Forecast;

/** Postes prévus : le plan annuel des revenus et dépenses de la famille. */
final class PlannedController
{
    public function index(): void
    {
        $items = Forecast::items(false);
        $totals = ['income' => 0.0, 'expense' => 0.0];
        foreach ($items as $i) {
            if ($i['is_active']) {
                $totals[$i['type']] += $i['annual'];
            }
        }
        view('planned/index', ['title' => 'Postes prévus', 'items' => $items, 'totals' => $totals,
            'recurringBills' => $this->recurringBillsNotPlanned()]);
    }

    public function edit(): void
    {
        $item = ['id' => null, 'name' => (string) input('name'), 'type' => input('type') === 'income' ? 'income' : 'expense',
            'amount' => '', 'months' => '1,2,3,4,5,6,7,8,9,10,11,12', 'day' => 1, 'category_id' => null,
            'start_month' => '', 'end_month' => '', 'is_active' => 1, 'notes' => ''];
        if ($id = int_input('id')) {
            $stmt = db()->prepare('SELECT * FROM planned_items WHERE id = ?');
            $stmt->execute([$id]);
            $item = $stmt->fetch() ?: abort(404);
        }
        view('planned/edit', ['title' => 'Poste prévu', 'item' => $item, 'categories' => all_categories()]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $type = input('type') === 'income' ? 'income' : 'expense';
        $months = Forecast::parseMonths(implode(',', (array) ($_POST['months'] ?? [])));
        $month = fn (string $k) => ($v = (string) input($k)) !== '' ? valid_month($v) : null;
        $data = [
            (string) input('name'),
            $type,
            abs(parse_amount(input('amount'))),
            implode(',', $months),
            max(1, min(31, (int) input('day', 1))),
            int_input('category_id'),
            $month('start_month'),
            $month('end_month'),
            isset($_POST['is_active']) ? 1 : 0,
            input('notes') ?: null,
        ];
        if ($data[0] === '' || $data[2] <= 0 || !$months) {
            flash('danger', 'Nom, montant et au moins un mois sont obligatoires.');
            redirect('planned/edit', $id ? ['id' => $id] : ['type' => $type]);
        }
        if ($id) {
            db()->prepare('UPDATE planned_items SET name=?, type=?, amount=?, months=?, day=?, category_id=?, start_month=?, end_month=?, is_active=?, notes=? WHERE id=?')
                ->execute([...$data, $id]);
        } else {
            db()->prepare('INSERT INTO planned_items (name, type, amount, months, day, category_id, start_month, end_month, is_active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute($data);
        }
        flash('success', 'Poste enregistré.');
        if (isset($_POST['save_new'])) {
            redirect('planned/edit', ['type' => $type]);
        }
        redirect('planned');
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM planned_items WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Poste supprimé.');
        redirect('planned');
    }

    /** Factures récurrentes à payer qui n'ont pas encore de poste prévu du même nom. */
    private function recurringBillsNotPlanned(): array
    {
        return db()->query("SELECT b.* FROM bills b
            WHERE b.status = 'due' AND b.recurrence <> 'none'
              AND NOT EXISTS (SELECT 1 FROM planned_items p WHERE p.name = b.name)
              AND b.id = (SELECT MIN(b2.id) FROM bills b2 WHERE b2.name = b.name AND b2.status = 'due')
            ORDER BY b.name")->fetchAll();
    }

    /** Crée un poste prévu pour chaque facture récurrente, à partir de son échéance et de sa récurrence. */
    public function importBills(): void
    {
        $step = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12];
        $insert = db()->prepare("INSERT INTO planned_items (name, type, amount, months, day, category_id, notes)
            VALUES (?, 'expense', ?, ?, ?, ?, 'Créé depuis les factures')");
        $count = 0;
        foreach ($this->recurringBillsNotPlanned() as $b) {
            $first = (int) date('n', strtotime($b['due_date']));
            $months = [];
            for ($m = 0; $m < 12; $m += $step[$b['recurrence']]) {
                $months[] = (($first - 1 + $m) % 12) + 1;
            }
            sort($months);
            $insert->execute([$b['name'], $b['amount'], implode(',', $months), (int) date('j', strtotime($b['due_date'])), $b['category_id']]);
            $count++;
        }
        flash('success', "$count poste(s) créé(s) depuis vos factures récurrentes.");
        redirect('planned');
    }
}
