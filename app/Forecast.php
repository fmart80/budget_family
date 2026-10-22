<?php
declare(strict_types=1);

namespace App;

/**
 * Prévisions de trésorerie et lissage des dépenses irrégulières,
 * à partir des postes prévus (planned_items) et des mensualités de dettes.
 */
final class Forecast
{
    public static function monthNames(bool $short = false): array
    {
        return $short
            ? [1 => 'jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'aoû', 'sep', 'oct', 'nov', 'déc']
            : [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    }

    /** "6,12" → [6, 12] */
    public static function parseMonths(string $months): array
    {
        $out = array_values(array_unique(array_filter(array_map('intval', explode(',', $months)),
            fn ($m) => $m >= 1 && $m <= 12)));
        sort($out);
        return $out;
    }

    /** Texte lisible : « tous les mois », « juin, déc », « trimestriel »… */
    public static function describeMonths(array $months): string
    {
        $n = count($months);
        if ($n === 12) {
            return 'tous les mois';
        }
        $names = self::monthNames(true);
        return implode(', ', array_map(fn ($m) => $names[$m], $months));
    }

    /**
     * Postes prévus (+ abonnements actifs quand $activeOnly, car ils comptent dans les prévisions).
     * La liste complète (actifs et inactifs, sans abonnements) sert à l'écran des postes prévus.
     */
    public static function items(bool $activeOnly = true): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, c.color AS category_color FROM planned_items p
                LEFT JOIN categories c ON c.id = p.category_id'
            . ($activeOnly ? ' WHERE p.is_active = 1' : '')
            . ' ORDER BY p.type DESC, p.name';
        $items = db()->query($sql)->fetchAll();
        foreach ($items as &$item) {
            $item['month_list'] = self::parseMonths($item['months']);
            $item['amount'] = (float) $item['amount'];
            $item['annual'] = $item['amount'] * count($item['month_list']);
            $item['monthly_equivalent'] = round($item['annual'] / 12, 2);
            $item['is_regular'] = count($item['month_list']) === 12;
            $item['source'] = 'planned';
            $item['edit_url'] = url('planned/edit', ['id' => $item['id']]);
        }
        return $activeOnly ? array_merge($items, Subscriptions::asPlannedItems()) : $items;
    }

    /** Le poste tombe-t-il ce mois-ci (« AAAA-MM ») ? */
    public static function occursIn(array $item, string $month): bool
    {
        if (!in_array((int) substr($month, 5, 2), $item['month_list'], true)) {
            return false;
        }
        if ($item['start_month'] && $month < $item['start_month']) {
            return false;
        }
        return !($item['end_month'] && $month > $item['end_month']);
    }

    /** Comptes dont le solde sert de point de départ (par défaut : tout sauf l'épargne). */
    public static function defaultAccountIds(): array
    {
        return array_map(fn ($a) => (int) $a['id'],
            array_filter(all_accounts(), fn ($a) => $a['type'] !== 'epargne'));
    }

    /**
     * Projection mois par mois.
     * Le mois en cours ne compte que les postes dont le jour n'est pas encore passé.
     */
    public static function build(int $count = 12, ?array $accountIds = null): array
    {
        $accountIds ??= self::defaultAccountIds();
        $balances = Finance::balances();
        $balance = array_sum(array_map(fn ($id) => $balances[$id] ?? 0, $accountIds));
        $startBalance = $balance;

        $items = self::items();
        $debtSchedules = self::debtSchedules();
        $today = (int) date('j');
        $current = date('Y-m');
        $months = [];

        for ($i = 0; $i < $count; $i++) {
            $month = date('Y-m', strtotime("+$i months", strtotime(date('Y-m-01'))));
            $row = ['month' => $month, 'incomes' => [], 'expenses' => [], 'debts' => [],
                'income' => 0.0, 'expense' => 0.0, 'debt' => 0.0];
            foreach ($items as $item) {
                if (!self::occursIn($item, $month) || ($month === $current && (int) $item['day'] < $today)) {
                    continue;
                }
                $key = $item['type'] === 'income' ? 'incomes' : 'expenses';
                $row[$key][] = $item;
                $row[$item['type']] += $item['amount'];
            }
            foreach ($debtSchedules as $d) {
                if (isset($d['schedule'][$month])) {
                    $row['debts'][] = ['name' => $d['name'], 'amount' => $d['schedule'][$month]];
                    $row['debt'] += $d['schedule'][$month];
                }
            }
            $row['net'] = $row['income'] - $row['expense'] - $row['debt'];
            $balance += $row['net'];
            $row['balance'] = $balance;
            $months[] = $row;
        }

        return [
            'start_balance' => $startBalance,
            'account_ids'   => $accountIds,
            'months'        => $months,
            'lowest'        => min($startBalance, ...array_column($months, 'balance')),
        ];
    }

    /** Mensualités futures de chaque dette ouverte : [['name', 'schedule' => ['AAAA-MM' => montant]]]. */
    public static function debtSchedules(): array
    {
        $remaining = Finance::debtRemaining();
        $out = [];
        foreach (db()->query('SELECT * FROM debts WHERE is_closed = 0') as $d) {
            $rest = $remaining[(int) $d['id']] ?? 0;
            if ($rest <= 0 || $d['monthly_payment'] <= 0) {
                continue;
            }
            $schedule = [];
            foreach (Finance::amortization($rest, (float) $d['interest_rate'], (float) $d['monthly_payment']) as $row) {
                $schedule[$row['month']] = $row['payment'];
            }
            $out[] = ['name' => $d['name'], 'schedule' => $schedule, 'day' => (int) date('j', strtotime($d['start_date']))];
        }
        return $out;
    }

    /**
     * Lissage : sépare les postes réguliers (12 mois) des irréguliers,
     * calcule la provision mensuelle et simule un compte réserve sur 12 mois.
     */
    public static function smoothing(): array
    {
        $items = self::items();
        $regular = ['income' => 0.0, 'expense' => 0.0];
        $irregular = ['income' => [], 'expense' => []];
        $irregularAnnual = ['income' => 0.0, 'expense' => 0.0];
        foreach ($items as $item) {
            if ($item['is_regular']) {
                $regular[$item['type']] += $item['amount'];
            } else {
                $irregular[$item['type']][] = $item;
                $irregularAnnual[$item['type']] += $item['annual'];
            }
        }

        $debtMonthly = 0.0;
        $remaining = Finance::debtRemaining();
        foreach (db()->query('SELECT id, monthly_payment FROM debts WHERE is_closed = 0') as $d) {
            if (($remaining[(int) $d['id']] ?? 0) > 0) {
                $debtMonthly += (float) $d['monthly_payment'];
            }
        }

        $provision = round($irregularAnnual['expense'] / 12, 2);
        $incomeSmoothed = round($irregularAnnual['income'] / 12, 2);
        // Compte réserve : reçoit les revenus irréguliers (13e…) + un virement mensuel,
        // paie les dépenses irrégulières. Virement = (dépenses irr. - revenus irr.) / 12.
        $transfer = round(($irregularAnnual['expense'] - $irregularAnnual['income']) / 12, 2);
        $reserve = [];
        $balance = 0.0;
        $lowest = 0.0;
        for ($i = 1; $i <= 12; $i++) {
            $month = date('Y-m', strtotime("+$i months", strtotime(date('Y-m-01'))));
            $in = 0.0;
            $out = 0.0;
            foreach ($irregular['income'] as $item) {
                $in += self::occursIn($item, $month) ? $item['amount'] : 0;
            }
            foreach ($irregular['expense'] as $item) {
                $out += self::occursIn($item, $month) ? $item['amount'] : 0;
            }
            $balance += $transfer + $in - $out;
            $lowest = min($lowest, $balance);
            $reserve[] = ['month' => $month, 'transfer' => $transfer, 'in' => $in, 'out' => $out, 'balance' => $balance];
        }
        $initial = round(max(0, -$lowest), 2);
        foreach ($reserve as &$r) {
            $r['balance'] += $initial;
        }

        return [
            'regular'          => $regular,
            'irregular'        => $irregular,
            'irregular_annual' => $irregularAnnual,
            'provision'        => $provision,
            'income_smoothed'  => $incomeSmoothed,
            'debt_monthly'     => $debtMonthly,
            'transfer'         => $transfer,
            'reserve'          => $reserve,
            'reserve_initial'  => $initial,
            'margin'           => round($regular['income'] + $incomeSmoothed - $regular['expense'] - $provision - $debtMonthly, 2),
        ];
    }

    /** Dépenses moyennes par catégorie sur les N derniers mois complets : [category_id => moyenne]. */
    public static function averageSpending(int $months): array
    {
        $end = date('Y-m-d', strtotime('last day of previous month'));
        $start = date('Y-m-01', strtotime('-' . $months . ' months', strtotime(date('Y-m-01'))));
        $stmt = db()->prepare("SELECT COALESCE(category_id, 0) AS cid, SUM(amount) AS total
            FROM transactions WHERE type = ? AND date BETWEEN ? AND ? GROUP BY cid");
        $out = ['expense' => [], 'income' => []];
        foreach (['expense', 'income'] as $type) {
            $stmt->execute([$type, $start, $end]);
            foreach ($stmt as $row) {
                $out[$type][(int) $row['cid']] = round((float) $row['total'] / $months, 2);
            }
        }
        return $out;
    }

    /** Nombre de mois complets disposant de transactions (pour savoir si les moyennes sont fiables). */
    public static function monthsOfHistory(): int
    {
        $first = db()->query('SELECT MIN(date) FROM transactions')->fetchColumn();
        if (!$first) {
            return 0;
        }
        $a = new \DateTimeImmutable(date('Y-m-01', strtotime($first)));
        $b = new \DateTimeImmutable(date('Y-m-01'));
        $diff = $a->diff($b);
        return $diff->y * 12 + $diff->m;
    }
}
