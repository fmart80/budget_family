<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Forecast;

final class DashboardController
{
    public function index(): void
    {
        $month = date('Y-m');
        $balances = Finance::balances();
        $accounts = all_accounts();

        $categories = [];
        foreach (all_categories('expense') as $c) {
            $categories[$c['id']] = $c;
        }
        $spending = Finance::spendingByCategory($month);
        arsort($spending);

        $stmt = db()->prepare("SELECT * FROM bills WHERE status = 'due' AND due_date <= ? ORDER BY due_date LIMIT 8");
        $stmt->execute([date('Y-m-d', strtotime('+30 days'))]);

        $budgetStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM budgets WHERE month = ?');
        $budgetStmt->execute([$month]);

        $debtRemaining = 0.0;
        $remaining = Finance::debtRemaining();
        foreach (db()->query('SELECT id FROM debts WHERE is_closed = 0') as $d) {
            $debtRemaining += $remaining[(int) $d['id']] ?? 0;
        }

        $goals = db()->query('SELECT * FROM savings_goals ORDER BY target_date IS NULL, target_date LIMIT 4')->fetchAll();

        view('dashboard/index', [
            'title'        => 'Tableau de bord',
            'month'        => $month,
            'totals'       => Finance::monthTotals($month),
            'budgetTotal'  => (float) $budgetStmt->fetchColumn(),
            'accounts'     => $accounts,
            'balances'     => $balances,
            'netWorth'     => array_sum(array_map(fn ($a) => $balances[$a['id']] ?? 0, $accounts)),
            'spending'     => $spending,
            'categories'   => $categories,
            'bills'        => $stmt->fetchAll(),
            'history'      => Finance::history(12),
            'debtRemaining' => $debtRemaining,
            'goals'        => $goals,
            'saved'        => Finance::savedByGoal(),
            'forecast'     => Forecast::items() ? Forecast::build(4) : null,
            'provision'    => Forecast::smoothing()['provision'],
            'uncategorized' => (int) db()->query("SELECT COUNT(*) FROM transactions WHERE category_id IS NULL AND type <> 'transfer'")->fetchColumn(),
        ]);
    }
}
