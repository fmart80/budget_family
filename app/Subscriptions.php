<?php
declare(strict_types=1);

namespace App;

use App\Controllers\BillsController;
use App\Import\Parse;

/** Abonnements : calculs (coût mensuel, prochain prélèvement), détection dans les relevés, alertes de prix. */
final class Subscriptions
{
    public const CYCLES = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12];
    public const CYCLE_LABELS = ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'semiannual' => 'Semestriel', 'yearly' => 'Annuel'];

    /** Services connus : reconnus dans les libellés même avec peu d'historique. */
    private const KNOWN = ['netflix', 'spotify', 'disney', 'apple', 'icloud', 'google', 'youtube', 'amazon', 'prime video',
        'deezer', 'audible', 'dazn', 'blue tv', 'teleclub', 'zattoo', 'canal', 'sky', 'swisscom', 'sunrise', 'salt', 'wingo',
        'yallo', 'microsoft', 'xbox', 'playstation', 'nintendo', 'steam', 'adobe', 'dropbox', 'openai', 'chatgpt', 'anthropic',
        'claude', 'linkedin', 'patreon', 'twitch', 'tinder', 'duolingo', 'strava', 'fitnesspark', 'update fitness', 'basefit',
        'mobility', 'publibike', 'tamedia', 'ringier', 'nzz', 'le temps', 'heidi', 'blick', 'la liberte'];

    private const STOPWORDS = ['achat', 'carte', 'debit', 'credit', 'paiement', 'payment', 'twint', 'ebanking', 'banking',
        'ordre', 'prelevement', 'lsv', 'direct', 'www', 'com', 'https', 'http', 'sarl', 'gmbh', 'ltd', 'inc', 'the', 'and',
        'des', 'les', 'chez', 'online', 'card', 'visa', 'mastercard', 'maestro', 'purchase', 'transaction', 'montant', 'chf',
        'eur', 'usd', 'avec', 'pour', 'paypal', 'sumup', 'abonnement', 'facture', 'mensuel', 'monthly'];

    public static function nextDate(string $anchor, string $cycle, ?string $from = null): string
    {
        $from ??= date('Y-m-d');
        $step = self::CYCLES[$cycle] ?? 1;
        $date = $anchor;
        for ($i = 1; $date < $from && $i < 1000; $i++) {
            $date = BillsController::addMonths($anchor, $step * $i);
        }
        return $date;
    }

    /** Mois de l'année où le prélèvement tombe. */
    public static function monthList(string $anchor, string $cycle): array
    {
        $step = self::CYCLES[$cycle] ?? 1;
        $first = (int) date('n', strtotime($anchor));
        $months = [];
        for ($m = 0; $m < 12; $m += $step) {
            $months[] = (($first - 1 + $m) % 12) + 1;
        }
        sort($months);
        return $months;
    }

    public static function all(?string $status = null): array
    {
        $sql = 'SELECT s.*, a.name AS account_name, a.type AS account_type, c.name AS category_name, c.color AS category_color
                FROM subscriptions s LEFT JOIN accounts a ON a.id = s.account_id LEFT JOIN categories c ON c.id = s.category_id';
        $params = [];
        if ($status) {
            $sql .= ' WHERE s.status = ?';
            $params[] = $status;
        }
        $stmt = db()->prepare($sql . ' ORDER BY s.name');
        $stmt->execute($params);
        $subs = $stmt->fetchAll();
        foreach ($subs as &$s) {
            $s['amount'] = (float) $s['amount'];
            $per = self::CYCLES[$s['cycle']] ?? 1;
            $s['monthly'] = round($s['amount'] / $per, 2);
            $s['annual'] = round($s['amount'] * 12 / $per, 2);
            $s['next_date'] = self::nextDate($s['anchor_date'], $s['cycle']);
            $s['on_card'] = ($s['account_type'] ?? '') === 'carte';
        }
        return $subs;
    }

    /** Abonnements actifs au format des postes prévus, pour les prévisions et le lissage. */
    public static function asPlannedItems(): array
    {
        $items = [];
        foreach (self::all('active') as $s) {
            $months = self::monthList($s['anchor_date'], $s['cycle']);
            $items[] = [
                'id' => null, 'source' => 'subscription', 'edit_url' => url('subscriptions/edit', ['id' => $s['id']]),
                'name' => 'Abonnement : ' . $s['name'], 'type' => 'expense', 'amount' => $s['amount'],
                'months' => implode(',', $months), 'month_list' => $months, 'day' => (int) date('j', strtotime($s['anchor_date'])),
                'category_id' => $s['category_id'], 'category_name' => $s['category_name'], 'category_color' => $s['category_color'],
                'start_month' => substr($s['anchor_date'], 0, 7), 'end_month' => null, 'is_active' => 1, 'notes' => null,
                'annual' => $s['annual'], 'monthly_equivalent' => $s['monthly'], 'is_regular' => count($months) === 12,
            ];
        }
        return $items;
    }

    /** Clé de regroupement d'un libellé : service connu, sinon deux premiers mots significatifs. */
    public static function merchantKey(string $description): string
    {
        $text = ' ' . preg_replace('/[^a-z]+/', ' ', Parse::normalize($description)) . ' ';
        foreach (self::KNOWN as $known) {
            if (str_contains($text, ' ' . $known . ' ')) {
                return $known;
            }
        }
        $words = array_values(array_filter(explode(' ', $text),
            fn ($w) => strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true)));
        return implode(' ', array_slice($words, 0, 2));
    }

    private static function matches(array $sub, string $description): bool
    {
        $keyword = Parse::normalize((string) ($sub['keyword'] ?: $sub['name']));
        return $keyword !== '' && str_contains(Parse::normalize($description), $keyword);
    }

    /** Dépenses des 13 derniers mois (base de la détection et des alertes). */
    private static function recentExpenses(): array
    {
        $stmt = db()->prepare("SELECT id, date, amount, description, account_id FROM transactions
            WHERE type = 'expense' AND date >= ? ORDER BY date");
        $stmt->execute([date('Y-m-d', strtotime('-13 months'))]);
        return $stmt->fetchAll();
    }

    /**
     * Alertes sur les abonnements actifs ayant un mot-clé : montant prélevé différent (hausse de prix)
     * ou aucun prélèvement trouvé depuis plus d'une période.
     */
    public static function alerts(array $subs): array
    {
        $tx = self::recentExpenses();
        if (!$tx) {
            return [];
        }
        $alerts = [];
        foreach ($subs as $s) {
            if ($s['status'] !== 'active') {
                continue;
            }
            $last = null;
            foreach ($tx as $t) {
                if (self::matches($s, $t['description'])) {
                    $last = $t;
                }
            }
            if ($last && abs((float) $last['amount'] - $s['amount']) >= 0.05) {
                $alerts[] = ['type' => 'price', 'sub' => $s, 'charged' => (float) $last['amount'], 'date' => $last['date']];
            }
            $days = (self::CYCLES[$s['cycle']] ?? 1) * 31 + 15;
            if ($last && strtotime($last['date']) < strtotime("-$days days")) {
                $alerts[] = ['type' => 'missing', 'sub' => $s, 'date' => $last['date']];
            }
        }
        return $alerts;
    }

    /** Dépenses qui reviennent chaque mois avec un montant stable et qui ne sont pas encore suivies. */
    public static function detect(array $subs): array
    {
        $groups = [];
        foreach (self::recentExpenses() as $t) {
            foreach ($subs as $s) {
                if (self::matches($s, $t['description'])) {
                    continue 2;
                }
            }
            $key = self::merchantKey($t['description']);
            if ($key !== '') {
                $groups[$key][] = $t;
            }
        }

        $ignored = db()->query('SELECT keyword FROM subscription_ignored')->fetchAll(\PDO::FETCH_COLUMN);
        $out = [];
        foreach ($groups as $key => $list) {
            if (in_array($key, $ignored, true)) {
                continue;
            }
            $months = array_unique(array_map(fn ($t) => substr($t['date'], 0, 7), $list));
            $known = in_array($key, self::KNOWN, true);
            if (count($months) < ($known ? 2 : 3) || count($list) > count($months) * 1.3) {
                continue;
            }
            $amounts = array_map(fn ($t) => (float) $t['amount'], $list);
            sort($amounts);
            $median = $amounts[intdiv(count($amounts), 2)];
            $stable = array_filter($amounts, fn ($a) => abs($a - $median) <= max(0.5, $median * 0.03));
            if ($median <= 0 || count($stable) < count($amounts) * 0.75) {
                continue;
            }
            $last = end($list);
            $out[] = ['name' => ucwords($key), 'keyword' => $key, 'amount' => (float) $last['amount'], 'months' => count($months),
                'last_date' => $last['date'], 'account_id' => $last['account_id'], 'example' => $last['description']];
        }
        usort($out, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        return $out;
    }
}
