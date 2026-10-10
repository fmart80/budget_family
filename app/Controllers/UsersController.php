<?php
declare(strict_types=1);

namespace App\Controllers;

final class UsersController
{
    public function index(): void
    {
        view('users/index', ['title' => 'Utilisateurs',
            'users' => db()->query('SELECT * FROM users ORDER BY name')->fetchAll()]);
    }

    public function edit(): void
    {
        $user = ['id' => null, 'name' => '', 'email' => '', 'role' => 'member'];
        if ($id = int_input('id')) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch() ?: abort(404);
        }
        view('users/edit', ['title' => 'Utilisateur', 'user' => $user]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $name = (string) input('name');
        $email = mb_strtolower((string) input('email'));
        $role = input('role') === 'admin' ? 'admin' : 'member';
        $password = (string) ($_POST['password'] ?? '');
        $back = fn () => redirect('users/edit', $id ? ['id' => $id] : []);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Nom et e-mail valide obligatoires.');
            $back();
        }
        if ((!$id || $password !== '') && mb_strlen($password) < 10) {
            flash('danger', 'Le mot de passe doit contenir au moins 10 caractères.');
            $back();
        }
        if ($id === (int) current_user()['id'] && $role !== 'admin') {
            flash('danger', 'Vous ne pouvez pas retirer vos propres droits administrateur.');
            $back();
        }
        try {
            if ($id) {
                db()->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?')->execute([$name, $email, $role, $id]);
                if ($password !== '') {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            }
        } catch (\PDOException) {
            flash('danger', 'Cette adresse e-mail est déjà utilisée.');
            $back();
        }
        flash('success', 'Utilisateur enregistré.');
        redirect('users');
    }

    public function toggle(): void
    {
        $id = int_input('id');
        if ($id === (int) current_user()['id']) {
            flash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
        } else {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('success', 'Statut modifié.');
        }
        redirect('users');
    }
}
