<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class BudgetsController
{
    public function index(): void
    {
        $month = valid_month((string) input('month'));
        $stmt = db()->prepare('SELECT category_id, amount FROM budgets WHERE month = ?');
        $stmt->execute([$month]);
        $budgets = array_map('floatval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));

        $spent = Finance::spendingByCategory($month);
        $rows = [];
        foreach (all_categories('expense') as $c) {
            $rows[] = ['category' => $c, 'budget' => $budgets[$c['id']] ?? 0.0, 'spent' => $spent[$c['id']] ?? 0.0];
        }

        view('budgets/index', [
            'title'         => 'Budget',
            'month'         => $month,
            'rows'          => $rows,
            'uncategorized' => $spent[0] ?? 0.0,
            'totals'        => Finance::monthTotals($month),
            'hasBudget'     => (bool) $budgets,
        ]);
    }

    public function save(): void
    {
        $month = valid_month((string) input('month'));
        $upsert = db()->prepare('INSERT INTO budgets (category_id, month, amount) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount)');
        $delete = db()->prepare('DELETE FROM budgets WHERE category_id = ? AND month = ?');
        foreach ((array) ($_POST['amounts'] ?? []) as $categoryId => $value) {
            $amount = abs(parse_amount($value));
            if ($amount > 0) {
                $upsert->execute([(int) $categoryId, $month, $amount]);
            } else {
                $delete->execute([(int) $categoryId, $month]);
            }
        }
        flash('success', 'Budget de ' . month_label($month) . ' enregistré.');
        redirect('budgets', ['month' => $month]);
    }

    /** Copie le budget du mois précédent vers le mois affiché. */
    public function copy(): void
    {
        $month = valid_month((string) input('month'));
        $previous = date('Y-m', strtotime($month . '-01 -1 month'));
        $stmt = db()->prepare('INSERT INTO budgets (category_id, month, amount)
            SELECT category_id, ?, amount FROM budgets WHERE month = ?
            ON DUPLICATE KEY UPDATE amount = VALUES(amount)');
        $stmt->execute([$month, $previous]);
        flash($stmt->rowCount() ? 'success' : 'warning', $stmt->rowCount()
            ? 'Budget copié depuis ' . month_label($previous) . '.'
            : 'Aucun budget trouvé pour ' . month_label($previous) . '.');
        redirect('budgets', ['month' => $month]);
    }
}
