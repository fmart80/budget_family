<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Import\CamtImporter;
use App\Import\CsvImporter;

/** Import des relevés bancaires (CSV UBS / BCF ou XML camt.053) en trois étapes : envoi, aperçu, validation. */
final class ImportController
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    public function index(): void
    {
        $accounts = all_accounts();
        if (!$accounts) {
            flash('info', "Créez d'abord le compte sur lequel importer les opérations.");
            redirect('accounts/edit');
        }
        view('import/index', ['title' => 'Import bancaire', 'accounts' => $accounts]);
    }

    public function upload(): void
    {
        $file = $_FILES['file'] ?? null;
        $accountId = int_input('account_id');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !$accountId) {
            flash('danger', 'Choisissez un compte et un fichier.');
            redirect('import');
        }
        if ($file['size'] > self::MAX_SIZE) {
            flash('danger', 'Fichier trop volumineux (5 Mo maximum).');
            redirect('import');
        }
        $content = (string) file_get_contents($file['tmp_name']);
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        try {
            if ($ext === 'zip' || str_starts_with($content, "PK\x03\x04")) {
                $parsed = CamtImporter::parseZip($file['tmp_name']);
                $import = ['format' => 'camt', 'rows' => $parsed['rows'], 'iban' => $parsed['iban']];
            } elseif ($ext === 'xml' || CamtImporter::looksLikeCamt($content)) {
                $parsed = CamtImporter::parse($content);
                $import = ['format' => 'camt', 'rows' => $parsed['rows'], 'iban' => $parsed['iban']];
            } else {
                $table = CsvImporter::read($content);
                $import = ['format' => 'csv', 'header' => $table['header'], 'raw' => $table['rows'],
                    'mapping' => CsvImporter::guessMapping($table['header'])];
            }
        } catch (\Throwable $ex) {
            flash('danger', 'Lecture impossible : ' . $ex->getMessage());
            redirect('import');
        }

        $import['account_id'] = $accountId;
        $import['filename'] = basename((string) $file['name']);
        $_SESSION['import'] = $import;
        redirect('import/preview');
    }

    /** Opérations normalisées de l'import en cours, avec détection des doublons et catégorie proposée. */
    private function rows(array $import): array
    {
        $rows = $import['format'] === 'csv' ? CsvImporter::apply($import['raw'], $import['mapping']) : $import['rows'];
        $rules = Finance::rules();
        $seen = [];
        $check = db()->prepare('SELECT COUNT(*) FROM transactions WHERE import_hash = ?');
        foreach ($rows as &$r) {
            $key = $r['ref'] ? 'ref|' . $r['ref'] : implode('|', [$r['date'], number_format($r['amount'], 2, '.', ''), $r['description']]);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $r['hash'] = sha1($import['account_id'] . '|' . $key . '|' . $seen[$key]);
            $check->execute([$r['hash']]);
            $r['duplicate'] = (int) $check->fetchColumn() > 0;
            $r['type'] = $r['amount'] < 0 ? 'expense' : 'income';
            $r['category_id'] = Finance::guessCategory($r['description'], $r['type'], $rules);
        }
        return $rows;
    }

    public function preview(): void
    {
        $import = $_SESSION['import'] ?? redirect('import');

        if ($import['format'] === 'csv' && is_post()) {
            $col = fn (string $k) => ($v = input($k, '')) === '' ? null : (int) $v;
            $import['mapping'] = [
                'date' => $col('map_date'), 'amount' => $col('map_amount'),
                'debit' => $col('map_debit'), 'credit' => $col('map_credit'),
                'description' => array_map('intval', (array) ($_POST['map_description'] ?? [])),
            ];
            $_SESSION['import'] = $import;
        }

        $accounts = all_accounts();
        $ibanWarning = null;
        if (!empty($import['iban'])) {
            foreach ($accounts as $a) {
                if ($a['iban'] && $a['iban'] !== $import['iban'] && (int) $a['id'] === (int) $import['account_id']) {
                    $ibanWarning = "Attention : le relevé concerne l'IBAN {$import['iban']}, différent de celui du compte choisi.";
                }
            }
        }

        view('import/preview', [
            'title'       => 'Aperçu de l\'import',
            'import'      => $import,
            'rows'        => $this->rows($import),
            'account'     => array_values(array_filter($accounts, fn ($a) => (int) $a['id'] === (int) $import['account_id']))[0] ?? null,
            'categories'  => all_categories(),
            'ibanWarning' => $ibanWarning,
        ]);
    }

    public function confirm(): void
    {
        $import = $_SESSION['import'] ?? redirect('import');
        $rows = $this->rows($import);
        $include = array_flip(array_map('intval', (array) ($_POST['include'] ?? [])));
        $chosen = (array) ($_POST['category'] ?? []);
        $types = [];
        foreach (all_categories() as $c) {
            $types[(int) $c['id']] = $c['type'];
        }

        $imported = 0;
        db()->beginTransaction();
        foreach ($rows as $i => $r) {
            if (!isset($include[$i]) || $r['duplicate']) {
                continue;
            }
            $cid = isset($chosen[$i]) && ctype_digit((string) $chosen[$i]) ? (int) $chosen[$i] : null;
            if ($cid && ($types[$cid] ?? null) !== $r['type']) {
                $cid = null;
            }
            Finance::addTransaction(['account_id' => $import['account_id'], 'category_id' => $cid, 'type' => $r['type'],
                'amount' => abs($r['amount']), 'date' => $r['date'], 'description' => $r['description'],
                'import_hash' => $r['hash']]);
            $imported++;
        }
        db()->commit();
        unset($_SESSION['import']);

        flash('success', "$imported opération(s) importée(s).");
        redirect('transactions', ['month' => '', 'account' => $import['account_id']]);
    }
}
