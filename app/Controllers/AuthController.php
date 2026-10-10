<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

final class AuthController
{
    public function login(): void
    {
        if (Auth::user()) {
            redirect('dashboard');
        }
        $email = (string) input('email');
        $error = null;
        if (is_post()) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if (Auth::isLocked($ip)) {
                $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
            } elseif (Auth::attempt($email, (string) ($_POST['password'] ?? ''), $ip)) {
                redirect('dashboard');
            } else {
                $error = 'E-mail ou mot de passe incorrect.';
            }
        }
        view('auth/login', ['email' => $email, 'error' => $error, 'title' => 'Connexion'], 'layout/guest');
    }

    public function logout(): void
    {
        if (is_post()) {
            Auth::logout();
        }
        redirect('login');
    }

    public function profile(): void
    {
        $user = current_user();
        $errors = [];
        if (is_post()) {
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $stmt->fetchColumn())) {
                $errors[] = 'Le mot de passe actuel est incorrect.';
            }
            if (mb_strlen($new) < 10) {
                $errors[] = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
            }
            if ($new !== ($_POST['new_password_confirm'] ?? '')) {
                $errors[] = 'La confirmation ne correspond pas.';
            }
            if (!$errors) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                flash('success', 'Mot de passe modifié.');
                redirect('profile');
            }
        }
        view('auth/profile', ['user' => $user, 'errors' => $errors, 'title' => 'Mon profil']);
    }
}
