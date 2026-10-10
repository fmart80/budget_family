<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;

/** Assistant d'installation : création des tables puis du premier administrateur. */
final class SetupController
{
    public function hasUsers(): bool
    {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public function run(): void
    {
        $errors = [];
        $setupKey = (string) config('setup_key', '');

        if ($setupKey === '' || $setupKey === 'changez-moi') {
            view('setup/no_key', [], 'layout/guest');
            return;
        }

        if (is_post()) {
            verify_csrf();
            if (!hash_equals($setupKey, (string) ($_POST['setup_key'] ?? ''))) {
                $errors[] = "La clé d'installation est incorrecte.";
            }
        }

        if (!Database::isInstalled()) {
            if (is_post() && !$errors) {
                Database::runSqlFile(BASE_PATH . '/database/schema.sql');
                Database::runSqlFile(BASE_PATH . '/database/seed.sql');
                flash('success', 'Base de données installée. Créez maintenant le compte administrateur.');
                redirect('setup');
            }
            view('setup/install', ['errors' => $errors], 'layout/guest');
            return;
        }

        $data = ['name' => input('name'), 'email' => mb_strtolower((string) input('email'))];
        if (is_post() && !$errors) {
            $password = (string) ($_POST['password'] ?? '');
            if ($data['name'] === '') {
                $errors[] = 'Le nom est obligatoire.';
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse e-mail invalide.';
            }
            if (mb_strlen($password) < 10) {
                $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
            }
            if (!$errors && !$this->hasUsers()) {
                db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
                    ->execute([$data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT)]);
                Auth::login((int) db()->lastInsertId());
                flash('success', 'Bienvenue ! Commencez par créer vos comptes bancaires.');
                redirect('accounts');
            }
        }
        view('setup/admin', ['errors' => $errors, 'data' => $data], 'layout/guest');
    }
}
