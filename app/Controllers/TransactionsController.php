<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class TransactionsController
{
    private const LIMIT = 500;

    /** Construit la clause WHERE à partir des filtres de la liste. */
    private function filters(): array
    {
        $f = [
            'month'    => array_key_exists('month', $_GET) ? (string) $_GET['month'] : date('Y-m'),
            'account'  => int_input('account'),
            'category' => (string) input('category'),
            'type'     => (string) input('type'),
            'q'        => (string) input('q'),
        ];
        if ($f['month'] !== '') {
            $f['month'] = valid_month($f['month']);
        }
        $where = ['1=1'];
        $params = [];
        if ($f['month'] !== '') {
            [$start, $end] = Finance::monthRange($f['month']);
            $where[] = 't.date BETWEEN ? AND ?';
            array_push($params, $start, $end);
        }
        if ($f['account']) {
            $where[] = '(t.account_id = ? OR t.to_account_id = ?)';
            array_push($params, $f['account'], $f['account']);
        }
        if ($f['category'] === 'none') {
            $where[] = "t.category_id IS NULL AND t.type <> 'transfer'";
        } elseif (ctype_digit($f['category'])) {
            $where[] = 't.category_id = ?';
            $params[] = (int) $f['category'];
        }
        if (in_array($f['type'], ['income', 'expense', 'transfer'], true)) {
            $where[] = 't.type = ?';
            $params[] = $f['type'];
        }
        if ($f['q'] !== '') {
            $where[] = '(t.description LIKE ? OR t.notes LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        return [$f, implode(' AND ', $where), $params];
    }

    private function query(string $where, array $params, int $limit): array
    {
        $stmt = db()->prepare("SELECT t.*, a.name AS account_name, ta.name AS to_account_name,
                c.name AS category_name, c.color AS category_color, u.name AS user_name
            FROM transactions t
            JOIN accounts a ON a.id = t.account_id
            LEFT JOIN accounts ta ON ta.id = t.to_account_id
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE $where ORDER BY t.date DESC, t.id DESC LIMIT $limit");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function index(): void
    {
        [$f, $where, $params] = $this->filters();
        $stmt = db()->prepare("SELECT
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount END), 0) AS income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount END), 0) AS expense,
                COUNT(*) AS n
            FROM transactions t WHERE $where");
        $stmt->execute($params);

        view('transactions/index', [
            'title'        => 'Transactions',
            'filters'      => $f,
            'transactions' => $this->query($where, $params, self::LIMIT),
            'totals'       => $stmt->fetch(),
            'limit'        => self::LIMIT,
            'accounts'     => all_accounts(false),
            'categories'   => all_categories(),
        ]);
    }

    public function edit(): void
    {
        $tx = ['id' => null, 'account_id' => int_input('account'), 'to_account_id' => null, 'category_id' => null,
            'type' => in_array(input('type'), ['income', 'transfer'], true) ? input('type') : 'expense',
            'amount' => '', 'date' => date('Y-m-d'), 'description' => '', 'notes' => ''];
        if ($id = int_input('id')) {
            $stmt = db()->prepare('SELECT * FROM transactions WHERE id = ?');
            $stmt->execute([$id]);
            $tx = $stmt->fetch() ?: abort(404);
        }
        $accounts = all_accounts();
        if (!$accounts) {
            flash('info', "Créez d'abord un compte.");
            redirect('accounts/edit');
        }
        view('transactions/edit', [
            'title' => 'Transaction', 'tx' => $tx, 'accounts' => $accounts, 'categories' => all_categories(),
            'back' => $_SERVER['HTTP_REFERER'] ?? url('transactions'),
        ]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $type = in_array(input('type'), ['income', 'expense', 'transfer'], true) ? input('type') : 'expense';
        $amount = abs(parse_amount(input('amount')));
        $date = valid_date((string) input('date'));
        $accountId = int_input('account_id');
        $toAccountId = $type === 'transfer' ? int_input('to_account_id') : null;
        $description = (string) input('description');

        $error = match (true) {
            $amount <= 0                                   => 'Le montant doit être supérieur à zéro.',
            $date === null                                 => 'Date invalide.',
            !$accountId                                    => 'Choisissez un compte.',
            $type === 'transfer' && (!$toAccountId || $toAccountId === $accountId) => 'Choisissez un compte de destination différent.',
            $description === ''                            => 'Le libellé est obligatoire.',
            default                                        => null,
        };
        if ($error) {
            flash('danger', $error);
            redirect('transactions/edit', $id ? ['id' => $id] : ['type' => $type]);
        }

        $categoryId = $type === 'transfer' ? null : int_input('category_id');
        $notes = input('notes') ?: null;
        if ($id) {
            db()->prepare('UPDATE transactions SET account_id=?, to_account_id=?, category_id=?, type=?, amount=?, date=?, description=?, notes=? WHERE id=?')
                ->execute([$accountId, $toAccountId, $categoryId, $type, $amount, $date, $description, $notes, $id]);
        } else {
            Finance::addTransaction(['account_id' => $accountId, 'to_account_id' => $toAccountId, 'category_id' => $categoryId,
                'type' => $type, 'amount' => $amount, 'date' => $date, 'description' => $description, 'notes' => $notes]);
        }
        flash('success', 'Transaction enregistrée.');
        if (isset($_POST['save_new'])) {
            redirect('transactions/edit', ['type' => $type, 'account' => $accountId]);
        }
        redirect('transactions', ['month' => substr($date, 0, 7)]);
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM transactions WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Transaction supprimée.');
        redirect('transactions', ['month' => valid_month((string) input('month'))]);
    }

    /** Actions groupées : changer la catégorie ou supprimer plusieurs transactions. */
    public function bulk(): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $back = (string) ($_POST['back'] ?? url('transactions'));
        if (!str_starts_with($back, 'index.php')) {
            $back = url('transactions');
        }
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            if (input('action') === 'delete') {
                db()->prepare("DELETE FROM transactions WHERE id IN ($in)")->execute($ids);
                flash('success', count($ids) . ' transaction(s) supprimée(s).');
            } elseif ($cid = int_input('category_id')) {
                db()->prepare("UPDATE transactions t JOIN categories c ON c.id = ?
                    SET t.category_id = c.id WHERE t.id IN ($in) AND t.type = c.type")->execute([$cid, ...$ids]);
                flash('success', 'Catégorie appliquée (aux transactions du même type).');
            }
        }
        header('Location: ' . $back);
        exit;
    }

    /** Export CSV (compatible Excel) des transactions filtrées. */
    public function export(): void
    {
        [$f, $where, $params] = $this->filters();
        $rows = $this->query($where, $params, 100000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions-' . ($f['month'] ?: 'toutes') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Type', 'Compte', 'Vers compte', 'Catégorie', 'Libellé', 'Montant', 'Notes'], ';');
        $types = ['income' => 'Revenu', 'expense' => 'Dépense', 'transfer' => 'Virement'];
        foreach ($rows as $r) {
            $signed = $r['type'] === 'expense' ? -$r['amount'] : $r['amount'];
            fputcsv($out, [date_ch($r['date']), $types[$r['type']], $r['account_name'], $r['to_account_name'],
                $r['category_name'], $r['description'], number_format((float) $signed, 2, '.', ''), $r['notes']], ';');
        }
        fclose($out);
        exit;
    }
}
