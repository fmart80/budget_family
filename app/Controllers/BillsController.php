<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class BillsController
{
    private const ALLOWED = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    private function find(?int $id): array
    {
        $stmt = db()->prepare('SELECT * FROM bills WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: abort(404, 'Facture introuvable');
    }

    /** Ajoute N mois en restant sur le dernier jour du mois si nécessaire (31.01 → 28.02). */
    public static function addMonths(string $date, int $months): string
    {
        $d = new \DateTimeImmutable($date);
        $target = $d->modify('first day of this month')->modify("+$months months");
        $day = min((int) $d->format('d'), (int) $target->format('t'));
        return $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $day)->format('Y-m-d');
    }

    public static function nextDueDate(string $date, string $recurrence): ?string
    {
        $months = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12][$recurrence] ?? null;
        return $months ? self::addMonths($date, $months) : null;
    }

    public function index(): void
    {
        $tab = input('tab') === 'paid' ? 'paid' : 'due';
        $sql = 'SELECT b.*, c.name AS category_name, c.color AS category_color, a.name AS account_name
            FROM bills b LEFT JOIN categories c ON c.id = b.category_id LEFT JOIN accounts a ON a.id = b.account_id ';
        $sql .= $tab === 'paid'
            ? "WHERE b.status = 'paid' ORDER BY b.paid_at DESC, b.id DESC LIMIT 200"
            : "WHERE b.status = 'due' ORDER BY b.due_date, b.id";
        $today = date('Y-m-d');
        $summary = db()->prepare("SELECT
                COALESCE(SUM(CASE WHEN due_date < ? THEN amount END), 0) AS overdue,
                COUNT(CASE WHEN due_date < ? THEN 1 END) AS overdue_count,
                COALESCE(SUM(CASE WHEN due_date BETWEEN ? AND ? THEN amount END), 0) AS next30,
                COALESCE(SUM(amount), 0) AS total
            FROM bills WHERE status = 'due'");
        $summary->execute([$today, $today, $today, date('Y-m-d', strtotime('+30 days'))]);

        view('bills/index', [
            'title'   => 'Factures',
            'tab'     => $tab,
            'bills'   => db()->query($sql)->fetchAll(),
            'summary' => $summary->fetch(),
        ]);
    }

    public function edit(): void
    {
        $bill = ['id' => null, 'name' => '', 'payee' => '', 'category_id' => null, 'account_id' => null,
            'amount' => '', 'due_date' => date('Y-m-d', strtotime('+30 days')), 'recurrence' => 'none',
            'reference' => '', 'notes' => '', 'attachment' => null, 'attachment_name' => null, 'status' => 'due'];
        if ($id = int_input('id')) {
            $bill = $this->find($id);
        }
        view('bills/edit', ['title' => 'Facture', 'bill' => $bill,
            'categories' => all_categories('expense'), 'accounts' => all_accounts()]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $bill = $id ? $this->find($id) : null;
        $recurrence = array_key_exists((string) input('recurrence'), recurrence_labels()) ? input('recurrence') : 'none';
        $data = [
            'name'        => (string) input('name'),
            'payee'       => input('payee') ?: null,
            'category_id' => int_input('category_id'),
            'account_id'  => int_input('account_id'),
            'amount'      => abs(parse_amount(input('amount'))),
            'due_date'    => valid_date((string) input('due_date')),
            'recurrence'  => $recurrence,
            'reference'   => input('reference') ?: null,
            'notes'       => input('notes') ?: null,
        ];
        if ($data['name'] === '' || $data['amount'] <= 0 || !$data['due_date']) {
            flash('danger', 'Nom, montant et échéance sont obligatoires.');
            redirect('bills/edit', $id ? ['id' => $id] : []);
        }

        $upload = $this->handleUpload();
        if ($upload === false) {
            redirect('bills/edit', $id ? ['id' => $id] : []);
        }
        if ($upload) {
            if ($bill && $bill['attachment']) {
                @unlink(STORAGE_PATH . '/uploads/' . $bill['attachment']);
            }
            [$data['attachment'], $data['attachment_name']] = $upload;
        } elseif ($bill && isset($_POST['remove_attachment']) && $bill['attachment']) {
            @unlink(STORAGE_PATH . '/uploads/' . $bill['attachment']);
            $data['attachment'] = $data['attachment_name'] = null;
        }

        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn ($c) => "$c = ?", $cols));
            db()->prepare("UPDATE bills SET $set WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            $place = implode(', ', array_fill(0, count($cols), '?'));
            db()->prepare('INSERT INTO bills (' . implode(', ', $cols) . ") VALUES ($place)")->execute(array_values($data));
        }
        flash('success', 'Facture enregistrée.');
        redirect('bills');
    }

    /** @return array{0:string,1:string}|null|false null = pas de fichier, false = erreur */
    private function handleUpload(): array|null|false
    {
        $file = $_FILES['attachment'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('danger', "Échec de l'envoi du fichier (code {$file['error']}). Fichier trop volumineux ?");
            return false;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            flash('danger', 'Formats acceptés : PDF, JPG, PNG.');
            return false;
        }
        if ($file['size'] > (int) config('max_upload_size', 10 * 1024 * 1024)) {
            flash('danger', 'Fichier trop volumineux.');
            return false;
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], STORAGE_PATH . '/uploads/' . $stored)) {
            flash('danger', "Impossible d'enregistrer le fichier : vérifiez les droits d'écriture du dossier storage/uploads.");
            return false;
        }
        return [$stored, mb_substr(basename((string) $file['name']), 0, 255)];
    }

    public function file(): void
    {
        $bill = $this->find(int_input('id'));
        $path = STORAGE_PATH . '/uploads/' . basename((string) $bill['attachment']);
        if (!$bill['attachment'] || !is_file($path)) {
            abort(404, 'Fichier introuvable');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        header('Content-Type: ' . (self::ALLOWED[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $bill['attachment_name'] ?: basename($path)) . '"');
        readfile($path);
        exit;
    }

    public function delete(): void
    {
        $bill = $this->find(int_input('id'));
        if ($bill['attachment']) {
            @unlink(STORAGE_PATH . '/uploads/' . $bill['attachment']);
        }
        db()->prepare('DELETE FROM bills WHERE id = ?')->execute([$bill['id']]);
        flash('success', 'Facture supprimée.');
        redirect('bills');
    }

    /** GET : formulaire de paiement ; POST : enregistre le paiement et crée l'échéance suivante. */
    public function pay(): void
    {
        $bill = $this->find(int_input('id'));
        if ($bill['status'] === 'paid') {
            flash('info', 'Cette facture est déjà payée.');
            redirect('bills');
        }
        if (!is_post()) {
            view('bills/pay', ['title' => 'Payer une facture', 'bill' => $bill, 'accounts' => all_accounts()]);
            return;
        }

        $date = valid_date((string) input('date')) ?? date('Y-m-d');
        $amount = abs(parse_amount(input('amount'))) ?: (float) $bill['amount'];
        $accountId = int_input('account_id');

        db()->beginTransaction();
        $txId = null;
        if ($accountId) {
            $txId = Finance::addTransaction(['account_id' => $accountId, 'category_id' => $bill['category_id'],
                'type' => 'expense', 'amount' => $amount, 'date' => $date,
                'description' => 'Facture : ' . $bill['name'] . ($bill['payee'] ? ' (' . $bill['payee'] . ')' : '')]);
        }
        db()->prepare("UPDATE bills SET status = 'paid', paid_at = ?, amount = ?, transaction_id = ? WHERE id = ?")
            ->execute([$date, $amount, $txId, $bill['id']]);

        $message = 'Facture marquée comme payée.';
        if ($next = self::nextDueDate($bill['due_date'], $bill['recurrence'])) {
            $exists = db()->prepare('SELECT COUNT(*) FROM bills WHERE name = ? AND due_date = ?');
            $exists->execute([$bill['name'], $next]);
            if (!(int) $exists->fetchColumn()) {
                db()->prepare("INSERT INTO bills (name, payee, category_id, account_id, amount, due_date, recurrence, reference, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$bill['name'], $bill['payee'], $bill['category_id'], $bill['account_id'], $bill['amount'],
                        $next, $bill['recurrence'], $bill['reference'], $bill['notes']]);
                $message .= ' Prochaine échéance créée pour le ' . date_ch($next) . '.';
            }
        }
        db()->commit();
        flash('success', $message);
        redirect('bills');
    }

    /** Annule un paiement (supprime la transaction associée). */
    public function unpay(): void
    {
        $bill = $this->find(int_input('id'));
        db()->beginTransaction();
        if ($bill['transaction_id']) {
            db()->prepare('DELETE FROM transactions WHERE id = ?')->execute([$bill['transaction_id']]);
        }
        db()->prepare("UPDATE bills SET status = 'due', paid_at = NULL, transaction_id = NULL WHERE id = ?")->execute([$bill['id']]);
        db()->commit();
        flash('success', 'Paiement annulé.');
        redirect('bills');
    }
}
