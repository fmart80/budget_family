<?php
declare(strict_types=1);

namespace App\Import;

/**
 * Import CSV générique avec détection automatique des colonnes.
 * Testé avec les exports e-banking UBS (FR/DE, ancien et nouveau format) ;
 * les autres banques (BCF…) passent par la même détection, avec correction manuelle possible.
 */
final class CsvImporter
{
    private const DATE_KEYS = ['date de comptabilisation', 'buchungsdatum', 'date comptable', 'booking date',
        'date de transaction', 'abschlussdatum', 'transaction date', 'date', 'datum', 'valuta', 'valeur', 'value date'];
    private const AMOUNT_KEYS = ['montant', 'betrag', 'amount'];
    private const DEBIT_KEYS = ['debit', 'belastung', 'sortie', 'retrait'];
    private const CREDIT_KEYS = ['credit', 'gutschrift', 'entree', 'versement'];
    private const DESC_KEYS = ['description', 'beschreibung', 'libelle', 'texte', 'text', 'motif', 'communication',
        'details', 'designation', 'mitteilung', 'avis', 'beneficiaire', 'empfanger', 'auftraggeber', 'contrepartie'];
    private const IGNORE_KEYS = ['sous-montant', 'einzelbetrag', 'solde', 'saldo', 'balance', 'fussnote', 'notes de bas'];

    /** @return array{header: string[], rows: string[][]} */
    public static function read(string $content): array
    {
        $content = Parse::toUtf8($content);
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = array_values(array_filter($lines, fn ($l) => trim($l, " \t;,") !== ''));
        if (!$lines) {
            return ['header' => [], 'rows' => []];
        }

        $delimiter = self::detectDelimiter(array_slice($lines, 0, 30));
        $table = array_map(fn ($l) => array_map('trim', str_getcsv($l, $delimiter, '"', '')), $lines);

        $headerIndex = 0;
        foreach (array_slice($table, 0, 40, true) as $i => $row) {
            $filled = array_filter($row, fn ($c) => $c !== '');
            if (count($filled) < 3) {
                continue;
            }
            $joined = Parse::normalize(implode(' ', $row));
            if ((str_contains($joined, 'date') || str_contains($joined, 'datum') || str_contains($joined, 'valuta'))
                && !Parse::date($row[0] ?? '')) {
                $headerIndex = $i;
                break;
            }
        }
        $header = $table[$headerIndex];
        $rows = array_slice($table, $headerIndex + 1);
        return ['header' => $header, 'rows' => $rows];
    }

    private static function detectDelimiter(array $lines): string
    {
        $best = ';';
        $bestScore = 0;
        foreach ([';', ',', "\t", '|'] as $d) {
            $counts = array_map(fn ($l) => count(str_getcsv($l, $d, '"', '')), $lines);
            sort($counts);
            $median = $counts[intdiv(count($counts), 2)];
            if ($median > $bestScore) {
                $best = $d;
                $bestScore = $median;
            }
        }
        return $best;
    }

    /** Devine les colonnes date / montant / débit / crédit / libellé à partir des en-têtes. */
    public static function guessMapping(array $header): array
    {
        $norm = array_map([Parse::class, 'normalize'], $header);
        $ignored = fn (string $h) => (bool) array_filter(self::IGNORE_KEYS, fn ($k) => str_contains($h, $k));
        $find = function (array $keys) use ($norm, $ignored): ?int {
            foreach ($keys as $key) {
                foreach ($norm as $i => $h) {
                    if ($h !== '' && str_contains($h, $key) && !$ignored($h)) {
                        return $i;
                    }
                }
            }
            return null;
        };

        $map = ['date' => $find(self::DATE_KEYS), 'amount' => null, 'debit' => $find(self::DEBIT_KEYS),
            'credit' => $find(self::CREDIT_KEYS), 'description' => []];
        if ($map['debit'] === null || $map['credit'] === null) {
            $map['debit'] = $map['credit'] = null;
            $map['amount'] = $find(self::AMOUNT_KEYS);
        }
        $used = array_filter([$map['date'], $map['amount'], $map['debit'], $map['credit']], fn ($v) => $v !== null);
        foreach ($norm as $i => $h) {
            if (in_array($i, $used, true) || $ignored($h)) {
                continue;
            }
            foreach (self::DESC_KEYS as $key) {
                if (str_contains($h, $key)) {
                    $map['description'][] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    /** Transforme les lignes brutes en opérations normalisées selon le mapping. */
    public static function apply(array $rows, array $map): array
    {
        $out = [];
        foreach ($rows as $row) {
            $date = Parse::date($row[$map['date'] ?? -1] ?? '');
            if (!$date) {
                continue;
            }
            if (($map['amount'] ?? null) !== null) {
                $amount = Parse::number($row[$map['amount']] ?? '');
            } else {
                $debit = Parse::number($row[$map['debit'] ?? -1] ?? '');
                $credit = Parse::number($row[$map['credit'] ?? -1] ?? '');
                $amount = ($debit === null && $credit === null) ? null : abs($credit ?? 0) - abs($debit ?? 0);
            }
            if ($amount === null || $amount == 0.0) {
                continue;
            }
            $parts = [];
            foreach ((array) ($map['description'] ?? []) as $i) {
                $v = trim(preg_replace('/\s+/', ' ', (string) ($row[$i] ?? '')));
                if ($v !== '' && !in_array($v, $parts, true)) {
                    $parts[] = $v;
                }
            }
            $out[] = ['date' => $date, 'amount' => $amount, 'description' => implode(' – ', $parts) ?: '(sans libellé)', 'ref' => null];
        }
        return $out;
    }
}
