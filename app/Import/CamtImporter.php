<?php
declare(strict_types=1);

namespace App\Import;

/** Import des relevés ISO 20022 camt.052 / camt.053 / camt.054 (XML), proposés par UBS, BCF et les autres banques suisses. */
final class CamtImporter
{
    public static function looksLikeCamt(string $content): bool
    {
        return str_contains(substr($content, 0, 2000), 'camt.05');
    }

    /** @return array{iban: ?string, rows: array} */
    public static function parse(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET)) {
            throw new \RuntimeException('Fichier XML illisible.');
        }
        $xp = new \DOMXPath($dom);
        $text = function (string $path, \DOMNode $ctx) use ($xp): string {
            $node = $xp->query($path, $ctx)->item(0);
            return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';
        };
        $l = fn (string $path) => implode('/', array_map(
            fn ($p) => $p === '' || $p === '.' || $p === '..' ? $p : "*[local-name()='$p']", explode('/', $path)));

        $ibanNode = $xp->query('//' . $l('Acct/Id/IBAN'))->item(0);
        $rows = [];
        foreach ($xp->query('//' . $l('Ntry')) as $entry) {
            $status = $text($l('Sts/Cd'), $entry) ?: $text($l('Sts'), $entry);
            if ($status !== '' && strtoupper($status) !== 'BOOK') {
                continue;
            }
            $amount = Parse::number($text($l('Amt'), $entry));
            $date = Parse::date($text($l('BookgDt/Dt'), $entry) ?: $text($l('BookgDt/DtTm'), $entry)
                ?: $text($l('ValDt/Dt'), $entry));
            if ($amount === null || !$date) {
                continue;
            }
            $isDebit = $text($l('CdtDbtInd'), $entry) === 'DBIT';
            $party = $isDebit ? 'Cdtr' : 'Dbtr';

            $parts = [];
            foreach ([
                ".//{$l("RltdPties/$party/Nm")}", ".//{$l("RltdPties/$party/Pty/Nm")}",
                './/' . $l('RmtInf/Ustrd'), $l('AddtlNtryInf'), './/' . $l('AddtlTxInf'),
            ] as $path) {
                $v = $text($path, $entry);
                if ($v !== '' && !in_array($v, $parts, true)) {
                    $parts[] = $v;
                }
            }
            $rows[] = [
                'date'        => $date,
                'amount'      => $isDebit ? -abs($amount) : abs($amount),
                'description' => mb_substr(implode(' – ', $parts) ?: '(sans libellé)', 0, 255),
                'ref'         => $text($l('AcctSvcrRef'), $entry) ?: ($text('.//' . $l('Refs/AcctSvcrRef'), $entry) ?: null),
            ];
        }
        return ['iban' => $ibanNode ? str_replace(' ', '', $ibanNode->textContent) : null, 'rows' => $rows];
    }

    /** Lit un fichier ZIP contenant un ou plusieurs relevés camt. */
    public static function parseZip(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("L'extension PHP zip n'est pas disponible : décompressez le fichier et importez le XML.");
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Archive ZIP illisible.');
        }
        $result = ['iban' => null, 'rows' => []];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }
            $parsed = self::parse((string) $zip->getFromIndex($i));
            $result['iban'] ??= $parsed['iban'];
            array_push($result['rows'], ...$parsed['rows']);
        }
        $zip->close();
        return $result;
    }
}
