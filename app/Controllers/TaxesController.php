<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Forecast;
use App\Score;

/**
 * Estimation des impôts à partir du dernier avis de taxation (taux effectif),
 * comparaison avec ce qui est prévu, économie possible avec le 3a et liste des déductions.
 */
final class TaxesController
{
    public const DEDUCTIONS = [
        'pillar3a'   => ['3ᵉ pilier A', "Attestation annuelle de la banque ou de l'assurance. Déductible jusqu'au plafond légal."],
        'lpp'        => ['Rachats LPP', 'Rachats volontaires dans la caisse de pension (attestation de la caisse). Souvent très avantageux fiscalement.'],
        'commute'    => ['Trajets domicile-travail', 'Abonnement de transports publics, ou frais de voiture si les transports publics ne sont pas raisonnables (plafonné).'],
        'meals'      => ['Repas hors du domicile', "Si vous ne pouvez pas rentrer manger à midi (forfait par jour de travail)."],
        'work'       => ['Autres frais professionnels', 'Forfait (ordinateur, vêtements, outils) ou frais effectifs justifiés.'],
        'training'   => ['Formation continue', 'Cours et formations liés à la profession, avec justificatifs.'],
        'childcare'  => ['Frais de garde des enfants', 'Crèche, maman de jour, accueil extrascolaire (factures), pour les enfants jusqu’à 14 ans.'],
        'insurance'  => ['Primes d’assurances', 'LAMal, assurance-vie et intérêts d’épargne : forfait selon la situation familiale.'],
        'medical'    => ['Frais médicaux non remboursés', 'Franchise, quote-part, dentiste, lunettes : déductibles au-delà d’un seuil (en % du revenu).'],
        'interest'   => ['Intérêts passifs', "Intérêts hypothécaires, mais aussi intérêts d'un crédit privé ou de la carte de crédit (relevés annuels)."],
        'donations'  => ['Dons', "Dons à des institutions d'utilité publique (reçus), au-delà d'un montant minimal."],
        'alimony'    => ['Pensions alimentaires versées', 'Pour l’ex-conjoint et les enfants mineurs (jugement et preuves de paiement).'],
        'dualincome' => ['Double revenu', 'Déduction pour les couples mariés dont les deux conjoints travaillent.'],
        'property'   => ['Entretien d’immeuble', 'Propriétaires : forfait ou frais effectifs d’entretien.'],
    ];

    /** Impôts prévus par an : postes de la catégorie « Impôts » ou dont le nom contient « impôt ». */
    private function plannedTaxes(): float
    {
        $cid = Finance::categoryIdByName('Impôts');
        $isTax = fn (array $i) => (int) $i['category_id'] === $cid || preg_match('/imp[oô]t|steuer/iu', $i['name']);
        return array_sum(array_map(fn ($i) => $i['type'] === 'expense' && $isTax($i) ? $i['annual'] : 0, Forecast::items()));
    }

    private function expectedIncome(): array
    {
        $planned = array_sum(array_map(fn ($i) => $i['type'] === 'income' ? $i['annual'] : 0, Forecast::items()));
        $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income' AND date >= ?");
        $stmt->execute([date('Y-m-d', strtotime('-12 months'))]);
        $actual = (float) $stmt->fetchColumn();
        return ['planned' => $planned, 'actual' => $actual, 'used' => $planned > 0 ? $planned : $actual,
            'source' => $planned > 0 ? 'selon le plan annuel' : 'selon les 12 derniers mois'];
    }

    public static function estimate(float $refIncome, float $refTax, float $income, float $planned): ?array
    {
        if ($refIncome <= 0 || $refTax <= 0 || $income <= 0) {
            return null;
        }
        $rate = $refTax / $refIncome;
        $estimate = round($rate * $income, 2);
        return ['rate' => $rate * 100, 'estimate' => $estimate, 'gap' => round($estimate - $planned, 2),
            'monthly_gap' => round(($estimate - $planned) / 12, 2)];
    }

    public function index(): void
    {
        $refIncome = (float) setting('tax_ref_income', 0);
        $refTax = (float) setting('tax_ref_paid', 0);
        $income = $this->expectedIncome();
        $planned = $this->plannedTaxes();
        $pillar = Score::pillar3aAnnual();
        $max3a = (float) setting('pillar3a_max', '7258');
        $marginal = (float) setting('tax_marginal_rate', 0);
        $estimate = self::estimate($refIncome, $refTax, $income['used'], $planned);
        $rateFor3a = $marginal > 0 ? $marginal : ($estimate['rate'] ?? 0);

        view('taxes/index', [
            'title'     => 'Impôts',
            'refYear'   => setting('tax_ref_year', (string) ((int) date('Y') - 1)),
            'refIncome' => $refIncome,
            'refTax'    => $refTax,
            'marginal'  => $marginal,
            'max3a'     => $max3a,
            'income'    => $income,
            'planned'   => $planned,
            'estimate'  => $estimate,
            'pillar'    => $pillar,
            'saving3a'  => round(max(0, $max3a - $pillar) * $rateFor3a / 100, 2),
            'rateFor3a' => $rateFor3a,
            'checked'   => json_decode((string) setting('tax_deductions_checked', '[]'), true) ?: [],
        ]);
    }

    public function save(): void
    {
        $year = (string) input('tax_ref_year');
        save_setting('tax_ref_year', preg_match('/^(19|20)\d\d$/', $year) ? $year : null);
        foreach (['tax_ref_income', 'tax_ref_paid', 'tax_marginal_rate', 'pillar3a_max'] as $key) {
            $value = abs(parse_amount(input($key)));
            save_setting($key, $value > 0 ? (string) $value : null);
        }
        flash('success', 'Données fiscales enregistrées.');
        redirect('taxes');
    }

    public function deductions(): void
    {
        $checked = array_values(array_intersect(array_keys(self::DEDUCTIONS), (array) ($_POST['deductions'] ?? [])));
        save_setting('tax_deductions_checked', json_encode($checked));
        flash('success', 'Liste des justificatifs mise à jour.');
        redirect('taxes');
    }
}
