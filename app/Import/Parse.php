<?php
declare(strict_types=1);

namespace App\Import;

/** Fonctions de conversion communes aux formats d'import. */
final class Parse
{
    /** "1'234.50", "-1 234,50", "1,234.50", "120.00-", "CHF 12.30" → float|null */
    public static function number(?string $value): ?float
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/[^\d,.\-+]/u', '', str_replace(['−', '–'], '-', $s));
        $negative = str_contains($s, '-');
        $s = str_replace(['-', '+'], '', $s);
        if ($s === '') {
            return null;
        }
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $thousands = $decimal === '.' ? ',' : '.';
            $s = str_replace([$thousands, $decimal], ['', '.'], $s);
        } elseif ($lastComma !== false) {
            $s = preg_match('/,\d{1,2}$/', $s) && substr_count($s, ',') === 1
                ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }
        if (!is_numeric($s)) {
            return null;
        }
        return round(($negative ? -1 : 1) * (float) $s, 2);
    }

    /** "31.12.2026", "31.12.26", "2026-12-31", "31/12/2026", "2026-12-31T10:00:00" → "2026-12-31"|null */
    public static function date(?string $value): ?string
    {
        $s = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $s, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += 2000;
            }
        } else {
            return null;
        }
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** Minuscules sans accents, pour comparer les en-têtes de colonnes. */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c'];
        return strtr($s, $map);
    }

    /** Convertit le contenu d'un fichier en UTF-8 (UTF-16, Windows-1252 / ISO-8859-1 acceptés). */
    public static function toUtf8(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($content, "\xFE\xFF")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
}
