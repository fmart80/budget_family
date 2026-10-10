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

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
