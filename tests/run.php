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

use App\Controllers\BillsController;
use App\Finance;
use App\Forecast;
use App\Import\CamtImporter;
use App\Import\CsvImporter;
use App\Import\Parse;

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

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
