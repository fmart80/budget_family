<?php
// Tests sans dépendance : php tests/run.php
declare(strict_types=1);

define('APP_PATH', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $class): void {
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (str_starts_with($class, 'App\\') && is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

use App\Controllers\BillsController;
use App\Controllers\SavingsController;
use App\Controllers\TaxesController;
use App\Finance;
use App\Forecast;
use App\Import\CamtImporter;
use App\Import\CsvImporter;
use App\Import\Parse;
use App\Subscriptions;

$failures = 0;
function eq(mixed $expected, mixed $actual, string $label): void
{
    global $failures;
    if ($expected === $actual) {
        echo "  ok   $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n       attendu : " . var_export($expected, true) . "\n       obtenu  : " . var_export($actual, true) . "\n";
    }
}

echo "Montants\n";
eq(1234.5, Parse::number("1'234.50"), "apostrophe suisse");
eq(-1234.5, Parse::number('-1 234,50'), 'virgule décimale');
eq(1234.5, Parse::number('1,234.50'), 'virgule milliers');
eq(-120.0, Parse::number('120.00-'), 'signe en fin');
eq(12.3, Parse::number('CHF 12.30'), 'préfixe devise');
eq(null, Parse::number(''), 'vide');

echo "Dates\n";
eq('2026-12-31', Parse::date('31.12.2026'), 'jj.mm.aaaa');
eq('2026-12-31', Parse::date('31.12.26'), 'jj.mm.aa');
eq('2026-12-31', Parse::date('2026-12-31T10:00:00'), 'ISO');
eq(null, Parse::date('31.02.2026'), 'date impossible');

echo "CSV UBS\n";
$t = CsvImporter::read((string) file_get_contents(__DIR__ . '/fixtures/ubs_fr.csv'));
$map = CsvImporter::guessMapping($t['header']);
eq(2, $map['date'], 'colonne date de comptabilisation');
eq([5, 6], [$map['debit'], $map['credit']], 'colonnes débit / crédit');
eq([10, 11, 12], $map['description'], 'colonnes description');
$rows = CsvImporter::apply($t['rows'], $map);
eq(4, count($rows), 'sous-lignes d’ordre groupé ignorées');
eq(-1280.4, $rows[2]['amount'], 'ordre groupé');
eq(6500.0, $rows[1]['amount'], 'crédit');

echo "CSV BCF (Latin-1)\n";
$t = CsvImporter::read((string) file_get_contents(__DIR__ . '/fixtures/bcf_latin1.csv'));
$rows = CsvImporter::apply($t['rows'], CsvImporter::guessMapping($t['header']));
eq(3, count($rows), 'nombre de lignes');
eq(['2026-09-10', -1850.0, 'Ordre permanent loyer'], [$rows[2]['date'], $rows[2]['amount'], $rows[2]['description']], 'ligne loyer');

echo "camt.053\n";
$c = CamtImporter::parse((string) file_get_contents(__DIR__ . '/fixtures/camt053.xml'));
eq('CH9300768011623852957', $c['iban'], 'IBAN');
eq(2, count($c['rows']), 'écriture non comptabilisée ignorée');
eq(-1850.0, $c['rows'][0]['amount'], 'débit');
eq('Régie Immobilière SA – Loyer août – Ordre permanent', $c['rows'][0]['description'], 'libellé');
eq('REF-002', $c['rows'][1]['ref'], 'référence bancaire');

echo "Échéances et dettes\n";
eq('2026-02-28', BillsController::addMonths('2026-01-31', 1), '31.01 + 1 mois');
eq('2027-01-15', BillsController::nextDueDate('2026-10-15', 'quarterly'), 'trimestrielle');
eq(null, BillsController::nextDueDate('2026-10-15', 'none'), 'unique');
eq(10, Finance::monthsToPayOff(1000, 0, 100), 'sans intérêts');
eq(null, Finance::monthsToPayOff(10000, 12, 100), 'mensualité insuffisante');
eq(47, Finance::monthsToPayOff(18000, 3.9, 420), 'leasing 3.9 %');

echo "Remboursement anticipé (carte de crédit 4'500.- à 12 %)\n";
eq(36, Finance::payoffSummary(4500, 12, 150)['months'], '150.-/mois : 36 mois');
eq(876.93, Finance::payoffSummary(4500, 12, 150)['interest'], '150.-/mois : intérêts');
eq(8, Finance::payoffSummary(4500, 12, 600)['months'], '600.-/mois : 8 mois');
eq(null, Finance::payoffSummary(4500, 12, 40), 'mensualité < intérêts : jamais remboursée');
$withExtras = Finance::amortization(4500, 12, 150, 600, [6 => 1000, 12 => 1000]);
eq(1150.0, array_values(array_filter($withExtras, fn ($r) => str_ends_with($r['month'], '-12')))[0]['payment'], 'versement extra en décembre');
eq(399.85, Finance::paymentForMonths(4500, 12, 12), 'mensualité pour finir en 12 mois');
eq(100.0, Finance::paymentForMonths(1200, 0, 12), 'mensualité sans intérêts');

echo "Postes prévus et lissage\n";
eq([6, 12], Forecast::parseMonths('12,6,13,0,6'), 'mois triés et filtrés');
eq('juin, déc', Forecast::describeMonths([6, 12]), 'libellé des mois');
eq('tous les mois', Forecast::describeMonths(range(1, 12)), 'libellé tous les mois');
$item = ['month_list' => [6, 12], 'start_month' => '2027-01', 'end_month' => null];
eq(false, Forecast::occursIn($item, '2026-12'), 'avant la date de début');
eq(true, Forecast::occursIn($item, '2027-06'), 'mois concerné');
eq(false, Forecast::occursIn($item, '2027-07'), 'mois non concerné');

echo "Abonnements\n";
eq('2026-11-08', Subscriptions::nextDate('2026-01-08', 'monthly', '2026-10-09'), 'prochain prélèvement mensuel');
eq('2026-10-08', Subscriptions::nextDate('2026-01-08', 'monthly', '2026-10-08'), 'prélèvement le jour même');
eq('2027-03-31', Subscriptions::nextDate('2025-03-31', 'yearly', '2026-04-01'), 'renouvellement annuel');
eq('2026-11-30', Subscriptions::nextDate('2026-01-31', 'monthly', '2026-11-01'), 'fin de mois conservée');
eq([2, 5, 8, 11], Subscriptions::monthList('2026-11-20', 'quarterly'), 'mois trimestriels');
eq('netflix', Subscriptions::merchantKey('NETFLIX.COM 1001 AMSTERDAM'), 'service connu');
eq('spotify', Subscriptions::merchantKey('Spotify P3000 Stockholm'), 'service connu avec référence');
eq('ecole musique', Subscriptions::merchantKey('LSV Ecole de Musique Bulle 4512'), 'libellé générique');

echo "Impôts\n";
$t = TaxesController::estimate(100000, 12000, 105000, 9000);
eq(12.0, round($t['rate'], 2), 'taux réel = impôts / revenus');
eq(12600.0, $t['estimate'], 'estimation = taux × revenus attendus');
eq(300.0, $t['monthly_gap'], 'manque mensuel à prévoir');
eq(null, TaxesController::estimate(0, 12000, 105000, 0), 'sans avis de taxation');

echo "Objectifs d'épargne\n";
eq(date('Y-m', strtotime('+10 months', strtotime(date('Y-m-01')))), SavingsController::eta(['target_amount' => 3000], 1000, 200), 'date d\'arrivée au rythme actuel');
eq(null, SavingsController::eta(['target_amount' => 3000], 1000, 0), 'sans versement : pas de date');
eq(date('Y-m'), SavingsController::eta(['target_amount' => 3000], 3500, 0), 'objectif déjà atteint');

echo "Sécurité PHP\n";
eq('danger', php_security_status('8.2.34', '2026-10-22')['level'] ?? null, 'PHP 8.2.34 vulnérable (PHP2Shell)');
eq('warning', php_security_status('8.2.35', '2026-10-22')['level'] ?? null, 'PHP 8.2.35 corrigé mais fin de support proche');
eq(null, php_security_status('8.4.27', '2026-10-22'), 'PHP 8.4.27 à jour');
eq('danger', php_security_status('8.3.35', '2026-10-22')['level'] ?? null, 'PHP 8.3.35 vulnérable');
eq('danger', php_security_status('8.1.33', '2026-10-22')['level'] ?? null, 'PHP 8.1 plus maintenu');
eq('danger', php_security_status('8.2.40', '2027-01-15')['level'] ?? null, 'PHP 8.2 après sa fin de support');

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
