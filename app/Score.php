<?php
declare(strict_types=1);

namespace App;

/**
 * Score financier sur 100, calculé à partir des données de l'application.
 * Les critères sans données suffisantes sont mis de côté et le total est ramené sur 100.
 */
final class Score
{
    /** Revenus, dépenses et taux d'épargne moyens des N derniers mois complets. */
    public static function savingsRate(int $months = 3): array
    {
        $start = date('Y-m-01', strtotime("-$months months", strtotime(date('Y-m-01'))));
        $end = date('Y-m-d', strtotime('last day of previous month'));
        $stmt = db()->prepare("SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS expense
            FROM transactions WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        $row = $stmt->fetch();
        $income = (float) $row['income'] / $months;
        $expense = (float) $row['expense'] / $months;
        return ['income' => $income, 'expense' => $expense,
            'rate' => $income > 0 ? ($income - $expense) / $income * 100 : null];
    }

    /** Taux d'épargne d'un mois donné (null sans revenus). */
    public static function monthRate(string $month): ?float
    {
        $t = Finance::monthTotals($month);
        return $t['income'] > 0 ? $t['net'] / $t['income'] * 100 : null;
    }

    /** Interpolation linéaire bornée : $bad → 0 point, $good → $max points. */
    private static function scale(float $value, float $bad, float $good, int $max): int
    {
        $ratio = ($value - $bad) / ($good - $bad);
        return (int) round(max(0, min(1, $ratio)) * $max);
    }

    public static function compute(): array
    {
        $avg = self::savingsRate(3);
        $hasHistory = $avg['income'] > 0;
        $monthlyIncome = $hasHistory ? $avg['income'] : self::plannedMonthlyIncome();
        $monthlyExpense = $hasHistory ? $avg['expense'] : 0.0;
        $criteria = [];

        // 1. Taux d'épargne (20 pts) : 20 % ou plus = maximum.
        $criteria[] = $avg['rate'] === null
            ? self::na('savings', 'Taux d\'épargne', 20, 'Importez au moins un mois complet de relevés (salaires compris).')
            : self::criterion('savings', 'Taux d\'épargne', 20, self::scale($avg['rate'], 0, 20, 20),
                number_format($avg['rate'], 1, '.', "'") . ' % des revenus (moyenne 3 mois)',
                'Objectif : mettre de côté au moins 10 %, idéalement 20 % de ce qui rentre.');

        // 2. Fonds d'urgence (15 pts) : 3 mois de dépenses sur les comptes épargne.
        $balances = Finance::balances();
        $reserve = 0.0;
        foreach (all_accounts() as $a) {
            if ($a['type'] === 'epargne') {
                $reserve += max(0, $balances[$a['id']] ?? 0);
            }
        }
        $criteria[] = $monthlyExpense <= 0
            ? self::na('emergency', 'Fonds d\'urgence', 15, 'Il faut des dépenses importées pour calculer combien de mois votre épargne couvre.')
            : self::criterion('emergency', 'Fonds d\'urgence', 15, self::scale($reserve / $monthlyExpense, 0, 3, 15),
                number_format($reserve / $monthlyExpense, 1, '.', "'") . ' mois de dépenses sur les comptes épargne',
                'Objectif : 3 à 6 mois de dépenses sur un compte épargne séparé.');

        // 3. Poids des dettes (15 pts) : mensualités / revenus, 0 % = maximum, 20 % ou plus = 0.
        $debtMonthly = 0.0;
        $remaining = Finance::debtRemaining();
        foreach (db()->query('SELECT id, monthly_payment FROM debts WHERE is_closed = 0') as $d) {
            if (($remaining[(int) $d['id']] ?? 0) > 0) {
                $debtMonthly += (float) $d['monthly_payment'];
            }
        }
        $criteria[] = $monthlyIncome <= 0
            ? self::na('debts', 'Poids des dettes', 15, 'Saisissez vos revenus (postes prévus ou relevés).')
            : self::criterion('debts', 'Poids des dettes', 15, self::scale($debtMonthly / $monthlyIncome * 100, 20, 0, 15),
                $debtMonthly > 0 ? number_format($debtMonthly / $monthlyIncome * 100, 1, '.', "'") . ' % des revenus en mensualités' : 'aucune dette en cours',
                'Remboursez en priorité les dettes au taux le plus élevé (carte de crédit, petit crédit).');

        // 4. Logement (10 pts) : loyer ou hypothèque / revenus, 25 % ou moins = maximum, 40 % = 0.
        $housing = self::housingMonthly();
        $criteria[] = $monthlyIncome <= 0 || $housing === null
            ? self::na('housing', 'Coût du logement', 10, 'Classez le loyer dans la catégorie « Loyer / Hypothèque » ou ajoutez-le aux postes prévus.')
            : self::criterion('housing', 'Coût du logement', 10, self::scale($housing / $monthlyIncome * 100, 40, 25, 10),
                number_format($housing / $monthlyIncome * 100, 1, '.', "'") . ' % des revenus',
                'Repère courant en Suisse : le logement ne devrait pas dépasser un quart à un tiers des revenus.');

        // 5. Budget respecté le mois dernier (10 pts).
        $last = date('Y-m', strtotime('first day of previous month'));
        $stmt = db()->prepare('SELECT category_id, amount FROM budgets WHERE month = ?');
        $stmt->execute([$last]);
        $budgets = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (!$budgets) {
            $criteria[] = self::na('budget', 'Budget respecté', 10, 'Définissez un budget mensuel (menu Budget → Budget du mois).');
        } else {
            $spent = Finance::spendingByCategory($last);
            $ok = count(array_filter(array_keys($budgets), fn ($cid) => ($spent[$cid] ?? 0) <= $budgets[$cid] * 1.05));
            $criteria[] = self::criterion('budget', 'Budget respecté', 10, (int) round($ok / count($budgets) * 10),
                "$ok catégorie(s) sur " . count($budgets) . ' dans le budget en ' . month_label($last),
                'Ajustez les enveloppes irréalistes plutôt que de les dépasser chaque mois.');
        }

        // 6. Factures à jour (10 pts) : -5 par facture en retard.
        $overdue = (int) db()->query("SELECT COUNT(*) FROM bills WHERE status = 'due' AND due_date < CURDATE()")->fetchColumn();
        $hasBills = (bool) db()->query('SELECT 1 FROM bills LIMIT 1')->fetchColumn();
        $criteria[] = !$hasBills
            ? self::na('bills', 'Factures à jour', 10, 'Saisissez vos factures pour suivre leurs échéances.')
            : self::criterion('bills', 'Factures à jour', 10, max(0, 10 - 5 * $overdue),
                $overdue ? "$overdue facture(s) en retard" : 'aucune facture en retard',
                'Payez ou planifiez les factures en retard : les rappels coûtent des frais.');

        // 7. Prévisions positives sur 12 mois (10 pts).
        if (!Forecast::items()) {
            $criteria[] = self::na('forecast', 'Prévisions sur 12 mois', 10, 'Saisissez votre plan annuel (Postes prévus) pour anticiper les mois difficiles.');
        } else {
            $f = Forecast::build(12);
            $negative = count(array_filter($f['months'], fn ($m) => $m['balance'] < 0));
            $criteria[] = self::criterion('forecast', 'Prévisions sur 12 mois', 10, max(0, 10 - 2 * $negative),
                $negative ? "solde prévu négatif pendant $negative mois" : 'solde prévu toujours positif',
                'Mettez en place le lissage (compte réserve) pour absorber les grosses factures.');
        }

        // 8. Prévoyance : versements 3e pilier A (5 pts).
        $pillar = self::pillar3aAnnual();
        $max3a = (float) setting('pillar3a_max', '7258');
        $criteria[] = $monthlyIncome <= 0
            ? self::na('pillar', '3ᵉ pilier A', 5, 'Saisissez vos revenus (postes prévus ou relevés).')
            : self::criterion('pillar', '3ᵉ pilier A', 5, self::scale($pillar, 0, $max3a, 5),
            $pillar > 0 ? money($pillar) . ' par an (plafond ' . money($max3a, false) . ')' : 'aucun versement 3a détecté',
            'Le 3a réduit vos impôts et prépare la retraite. Nommez le poste prévu ou l\'objectif « 3a » pour qu\'il soit reconnu.');

        // 9. Suivi : transactions classées (5 pts).
        $stmt = db()->prepare("SELECT COUNT(*) AS n, SUM(category_id IS NULL) AS missing FROM transactions
            WHERE type <> 'transfer' AND date >= ?");
        $stmt->execute([date('Y-m-d', strtotime('-90 days'))]);
        $row = $stmt->fetch();
        $criteria[] = (int) $row['n'] === 0
            ? self::na('tracking', 'Suivi des dépenses', 5, 'Importez vos relevés pour suivre vos dépenses.')
            : self::criterion('tracking', 'Suivi des dépenses', 5,
                self::scale(100 - (int) $row['missing'] / (int) $row['n'] * 100, 70, 95, 5),
                round(100 - (int) $row['missing'] / (int) $row['n'] * 100) . ' % des opérations classées (90 jours)',
                'Classez les opérations « à classer » et ajoutez des règles pour qu\'elles se classent seules.');

        $evaluated = array_filter($criteria, fn ($c) => $c['score'] !== null);
        $max = array_sum(array_column($evaluated, 'max'));
        $points = array_sum(array_column($evaluated, 'score'));
        // Au moins 3 critères évalués pour qu'une note ait du sens.
        $total = count($evaluated) >= 3 && $max > 0 ? (int) round($points / $max * 100) : null;

        return ['score' => $total, 'criteria' => $criteria, 'evaluated' => count($evaluated), 'savings' => $avg];
    }

    private static function criterion(string $key, string $label, int $max, int $score, string $value, string $tip): array
    {
        return compact('key', 'label', 'max', 'score', 'value', 'tip');
    }

    private static function na(string $key, string $label, int $max, string $tip): array
    {
        return ['key' => $key, 'label' => $label, 'max' => $max, 'score' => null, 'value' => 'pas encore de données', 'tip' => $tip];
    }

    private static function plannedMonthlyIncome(): float
    {
        return array_sum(array_map(fn ($i) => $i['type'] === 'income' ? $i['monthly_equivalent'] : 0, Forecast::items()));
    }

    /** Coût mensuel du logement : moyenne 3 mois de la catégorie, sinon le plan. */
    private static function housingMonthly(): ?float
    {
        $cid = Finance::categoryIdByName('Loyer / Hypothèque');
        if (!$cid) {
            return null;
        }
        $avg = Forecast::averageSpending(3)['expense'][$cid] ?? 0;
        if ($avg > 0) {
            return $avg;
        }
        $plan = array_sum(array_map(fn ($i) => (int) $i['category_id'] === $cid ? $i['monthly_equivalent'] : 0, Forecast::items()));
        return $plan > 0 ? $plan : null;
    }

    /** Versements 3a annuels : postes prévus / objectifs nommés « 3a » ou « pilier », sinon transactions des 12 derniers mois. */
    public static function pillar3aAnnual(): float
    {
        $is3a = fn (string $name) => (bool) preg_match('/\b3\s?a\b|pilier|säule/i', $name);
        $planned = array_sum(array_map(fn ($i) => $i['type'] === 'expense' && $is3a($i['name']) ? $i['annual'] : 0, Forecast::items()));
        if ($planned > 0) {
            return $planned;
        }
        $stmt = db()->prepare("SELECT c.amount, g.name FROM savings_contributions c JOIN savings_goals g ON g.id = c.goal_id WHERE c.date >= ?");
        $stmt->execute([date('Y-m-d', strtotime('-12 months'))]);
        $fromGoals = 0.0;
        foreach ($stmt as $row) {
            $fromGoals += $is3a($row['name']) ? (float) $row['amount'] : 0;
        }
        if ($fromGoals > 0) {
            return $fromGoals;
        }
        $stmt = db()->prepare("SELECT amount, description FROM transactions WHERE type IN ('expense', 'transfer') AND date >= ?");
        $stmt->execute([date('Y-m-d', strtotime('-12 months'))]);
        $fromTx = 0.0;
        foreach ($stmt as $row) {
            $fromTx += $is3a($row['description']) ? (float) $row['amount'] : 0;
        }
        return $fromTx;
    }

    /** Enregistre le score du mois et renvoie l'historique (12 derniers mois). */
    public static function recordAndHistory(?int $score, array $criteria): array
    {
        if ($score !== null) {
            $details = json_encode(array_map(fn ($c) => [$c['key'], $c['score'], $c['max']], $criteria));
            db()->prepare('INSERT INTO score_history (month, score, details) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE score = VALUES(score), details = VALUES(details)')
                ->execute([date('Y-m'), $score, $details]);
        }
        $rows = db()->query('SELECT month, score FROM score_history ORDER BY month DESC LIMIT 12')->fetchAll();
        return array_reverse($rows);
    }

    public static function label(?int $score): array
    {
        return match (true) {
            $score === null => ['Pas encore calculé', 'secondary'],
            $score >= 80    => ['Excellent', 'success'],
            $score >= 60    => ['Solide', 'primary'],
            $score >= 40    => ['À consolider', 'warning'],
            default         => ['Fragile', 'danger'],
        };
    }
}
