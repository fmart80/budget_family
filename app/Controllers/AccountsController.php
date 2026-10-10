<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class AccountsController
{
    public function index(): void
    {
        view('accounts/index', [
            'title'    => 'Comptes',
            'accounts' => all_accounts(false),
            'balances' => Finance::balances(),
        ]);
    }

    public function edit(): void
    {
        $account = ['id' => null, 'name' => '', 'type' => 'courant', 'bank' => '', 'iban' => '',
            'opening_balance' => '0.00', 'is_active' => 1];
        if ($id = int_input('id')) {
            $stmt = db()->prepare('SELECT * FROM accounts WHERE id = ?');
            $stmt->execute([$id]);
            $account = $stmt->fetch() ?: abort(404);
        }
        view('accounts/edit', ['title' => 'Compte', 'account' => $account]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $type = (string) input('type');
        $data = [
            (string) input('name'),
            array_key_exists($type, account_types()) ? $type : 'autre',
            input('bank') ?: null,
            strtoupper(str_replace(' ', '', (string) input('iban'))) ?: null,
            parse_amount(input('opening_balance')),
            isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($data[0] === '') {
            flash('danger', 'Le nom du compte est obligatoire.');
            redirect('accounts/edit', $id ? ['id' => $id] : []);
        }
        if ($id) {
            db()->prepare('UPDATE accounts SET name=?, type=?, bank=?, iban=?, opening_balance=?, is_active=? WHERE id=?')
                ->execute([...$data, $id]);
        } else {
            db()->prepare('INSERT INTO accounts (name, type, bank, iban, opening_balance, is_active) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute($data);
        }
        flash('success', 'Compte enregistré.');
        redirect('accounts');
    }

    public function delete(): void
    {
        $id = int_input('id');
        $stmt = db()->prepare('SELECT COUNT(*) FROM transactions WHERE account_id = ? OR to_account_id = ?');
        $stmt->execute([$id, $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('warning', 'Ce compte contient des transactions : désactivez-le plutôt que de le supprimer.');
        } else {
            db()->prepare('DELETE FROM accounts WHERE id = ?')->execute([$id]);
            flash('success', 'Compte supprimé.');
        }
        redirect('accounts');
    }
}
