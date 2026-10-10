<?php
declare(strict_types=1);

namespace App;

/** Requêtes de calcul réutilisées par plusieurs écrans. */
final class Finance
{
    /** @return array<int, float> solde courant par compte */
    public static function balances(): array
    {
        $sql = "SELECT a.id,
                    a.opening_balance
                    + COALESCE((SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END)
                                FROM transactions t WHERE t.account_id = a.id), 0)
                    + COALESCE((SELECT SUM(t.amount) FROM transactions t
                                WHERE t.to_account_id = a.id AND t.type = 'transfer'), 0) AS balance
                FROM accounts a";
        $out = [];
        foreach (db()->query($sql) as $row) {
            $out[(int) $row['id']] = (float) $row['balance'];
        }
        return $out;
    }

    public static function monthRange(string $month): array
    {
        $start = $month . '-01';
        return [$start, date('Y-m-t', strtotime($start))];
    }

    /** Revenus / dépenses d'un mois (hors virements internes). */
    public static function monthTotals(string $month): array
    {
        [$start, $end] = self::monthRange($month);
        $stmt = db()->prepare("SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense
            FROM transactions WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        $row = $stmt->fetch();
        return ['income' => (float) $row['income'], 'expense' => (float) $row['expense'],
            'net' => (float) $row['income'] - (float) $row['expense']];
    }

    /** Dépenses par catégorie pour un mois : [category_id => total]. */
    public static function spendingByCategory(string $month): array
    {
        [$start, $end] = self::monthRange($month);
        $stmt = db()->prepare("SELECT COALESCE(category_id, 0) AS cid, SUM(amount) AS total
            FROM transactions WHERE type = 'expense' AND date BETWEEN ? AND ? GROUP BY cid");
        $stmt->execute([$start, $end]);
        $out = [];
        foreach ($stmt as $row) {
            $out[(int) $row['cid']] = (float) $row['total'];
        }
        return $out;
    }

    /** Revenus et dépenses des N derniers mois (pour graphiques). */
    public static function history(int $months = 12): array
    {
        $first = date('Y-m-01', strtotime('-' . ($months - 1) . ' months', strtotime(date('Y-m-01'))));
        $stmt = db()->prepare("SELECT DATE_FORMAT(date, '%Y-%m') AS m,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense
            FROM transactions WHERE date >= ? GROUP BY m");
        $stmt->execute([$first]);
        $data = [];
        foreach ($stmt as $row) {
            $data[$row['m']] = $row;
        }
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = date('Y-m', strtotime("+$i months", strtotime($first)));
            $out[] = ['month' => $m, 'income' => (float) ($data[$m]['income'] ?? 0),
                'expense' => (float) ($data[$m]['expense'] ?? 0)];
        }
        return $out;
    }

    /** Solde restant de chaque dette : [debt_id => restant]. */
    public static function debtRemaining(): array
    {
        $sql = 'SELECT d.id, d.initial_amount - COALESCE(SUM(p.amount), 0) AS remaining
                FROM debts d LEFT JOIN debt_payments p ON p.debt_id = d.id GROUP BY d.id, d.initial_amount';
        $out = [];
        foreach (db()->query($sql) as $row) {
            $out[(int) $row['id']] = max(0.0, (float) $row['remaining']);
        }
        return $out;
    }

    /**
     * Nombre de mois pour rembourser un solde avec une mensualité et un taux annuel donnés.
     * Retourne null si la mensualité ne couvre pas les intérêts.
     */
    public static function monthsToPayOff(float $balance, float $annualRate, float $payment): ?int
    {
        if ($balance <= 0) {
            return 0;
        }
        if ($payment <= 0) {
            return null;
        }
        $r = $annualRate / 100 / 12;
        if ($r == 0.0) {
            return (int) ceil($balance / $payment);
        }
        if ($payment <= $balance * $r) {
            return null;
        }
        return (int) ceil(-log(1 - $r * $balance / $payment) / log(1 + $r));
    }

    /** Échéancier prévisionnel (mois, intérêts, capital, solde). */
    public static function amortization(float $balance, float $annualRate, float $payment, int $maxRows = 600): array
    {
        $rows = [];
        $r = $annualRate / 100 / 12;
        $date = strtotime(date('Y-m-01'));
        for ($i = 1; $balance > 0.005 && $i <= $maxRows && $payment > 0; $i++) {
            $interest = round($balance * $r, 2);
            $pay = min($payment, $balance + $interest);
            if ($pay <= $interest) {
                break;
            }
            $principal = $pay - $interest;
            $balance = round($balance - $principal, 2);
            $rows[] = ['month' => date('Y-m', strtotime("+$i months", $date)), 'payment' => $pay,
                'interest' => $interest, 'principal' => $principal, 'balance' => max(0, $balance)];
        }
        return $rows;
    }

    /** Montant épargné par objectif : [goal_id => total]. */
    public static function savedByGoal(): array
    {
        $out = [];
        foreach (db()->query('SELECT goal_id, SUM(amount) AS total FROM savings_contributions GROUP BY goal_id') as $row) {
            $out[(int) $row['goal_id']] = (float) $row['total'];
        }
        return $out;
    }

    /** Applique les règles mot-clé → catégorie à un libellé. */
    public static function guessCategory(string $description, string $type, ?array $rules = null): ?int
    {
        $rules ??= self::rules();
        $haystack = mb_strtolower($description);
        foreach ($rules as $rule) {
            if ($rule['category_type'] === $type && str_contains($haystack, mb_strtolower($rule['keyword']))) {
                return (int) $rule['category_id'];
            }
        }
        return null;
    }

    public static function rules(): array
    {
        return db()->query('SELECT r.*, c.type AS category_type FROM category_rules r
            JOIN categories c ON c.id = r.category_id ORDER BY CHAR_LENGTH(r.keyword) DESC')->fetchAll();
    }

    public static function categoryIdByName(string $name): ?int
    {
        $stmt = db()->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function addTransaction(array $t): int
    {
        $stmt = db()->prepare('INSERT INTO transactions
            (account_id, to_account_id, category_id, type, amount, date, description, notes, user_id, import_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $t['account_id'], $t['to_account_id'] ?? null, $t['category_id'] ?? null, $t['type'],
            $t['amount'], $t['date'], mb_substr($t['description'], 0, 255), $t['notes'] ?? null,
            $t['user_id'] ?? (current_user()['id'] ?? null), $t['import_hash'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }
}
