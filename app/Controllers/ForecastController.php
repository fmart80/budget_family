<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Forecast;

final class ForecastController
{
    /** Prévision de trésorerie sur 12 mois. */
    public function index(): void
    {
        $accounts = all_accounts();
        $selected = array_values(array_filter(array_map('intval', (array) ($_GET['accounts'] ?? []))));
        $forecast = Forecast::build(max(3, min(24, (int) input('months', 12))), $selected ?: null);
        view('forecast/index', [
            'title'    => 'Prévisions',
            'forecast' => $forecast,
            'accounts' => $accounts,
            'hasItems' => (bool) Forecast::items(),
        ]);
    }

    public function smoothing(): void
    {
        view('forecast/smoothing', ['title' => 'Lissage', 'data' => Forecast::smoothing()]);
    }

    /** Dépenses moyennes sur 3, 6 et 12 mois par catégorie, comparées au plan et au mois en cours. */
    public function analysis(): void
    {
        $avg = [3 => Forecast::averageSpending(3), 6 => Forecast::averageSpending(6), 12 => Forecast::averageSpending(12)];
        $current = Finance::spendingByCategory(date('Y-m'));
        $plan = [];
        foreach (Forecast::items() as $item) {
            if ($item['type'] === 'expense') {
                $cid = (int) $item['category_id'];
                $plan[$cid] = ($plan[$cid] ?? 0) + $item['monthly_equivalent'];
            }
        }
        $rows = [];
        foreach (array_merge(all_categories('expense'), [['id' => 0, 'name' => 'Sans catégorie', 'color' => '#ced4da']]) as $c) {
            $id = (int) $c['id'];
            $row = ['category' => $c, 'a3' => $avg[3]['expense'][$id] ?? 0, 'a6' => $avg[6]['expense'][$id] ?? 0,
                'a12' => $avg[12]['expense'][$id] ?? 0, 'current' => $current[$id] ?? 0, 'plan' => $plan[$id] ?? 0];
            if ($row['a3'] || $row['a6'] || $row['a12'] || $row['current'] || $row['plan']) {
                $rows[] = $row;
            }
        }
        usort($rows, fn ($a, $b) => $b['a6'] <=> $a['a6']);
        $income = fn (int $n) => array_sum($avg[$n]['income']);

        $history = Finance::history(13);
        array_pop($history); // mois en cours incomplet
        view('forecast/analysis', [
            'title'   => 'Analyse des dépenses',
            'rows'    => $rows,
            'income'  => [3 => $income(3), 6 => $income(6), 12 => $income(12)],
            'history' => $history,
            'monthsOfHistory' => Forecast::monthsOfHistory(),
        ]);
    }

    /**
     * Crée un poste prévu mensuel pour chaque catégorie de dépenses qui n'en a aucun,
     * à partir de la moyenne réelle (alimentation, loisirs, habillement…).
     */
    public function planFromAverage(): void
    {
        $period = in_array((int) input('period'), [3, 6, 12], true) ? (int) input('period') : 6;
        $planned = array_flip(array_filter(array_map(fn ($i) => (int) $i['category_id'],
            array_merge(Forecast::items(false), \App\Subscriptions::asPlannedItems()))));
        $names = [];
        foreach (all_categories('expense') as $c) {
            $names[(int) $c['id']] = $c['name'];
        }
        $insert = db()->prepare("INSERT INTO planned_items (name, type, amount, months, day, category_id, notes)
            VALUES (?, 'expense', ?, '1,2,3,4,5,6,7,8,9,10,11,12', 1, ?, ?)");
        $count = 0;
        foreach (Forecast::averageSpending($period)['expense'] as $cid => $avg) {
            if ($cid && $avg >= 10 && !isset($planned[$cid]) && isset($names[$cid])) {
                $insert->execute([$names[$cid], ceil($avg / 10) * 10, $cid, "Moyenne réelle sur $period mois"]);
                $count++;
            }
        }
        flash('success', "$count poste(s) ajouté(s) au plan. Vérifiez-les : une dépense irrégulière comptée dans la moyenne doit plutôt être saisie à part.");
        redirect('planned');
    }

    /** Remplit le budget d'un mois avec les moyennes 3 / 6 / 12 mois (arrondies aux 10 CHF). */
    public function budgetFromAverage(): void
    {
        $period = in_array((int) input('period'), [3, 6, 12], true) ? (int) input('period') : 6;
        $month = valid_month((string) input('month'));
        $avg = Forecast::averageSpending($period)['expense'];
        unset($avg[0]);
        $stmt = db()->prepare('INSERT INTO budgets (category_id, month, amount) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount)');
        foreach ($avg as $cid => $amount) {
            if ($amount > 0) {
                $stmt->execute([$cid, $month, ceil($amount / 10) * 10]);
            }
        }
        flash('success', 'Budget de ' . month_label($month) . " rempli avec la moyenne sur $period mois. Ajustez-le si besoin.");
        redirect('budgets', ['month' => $month]);
    }
}
