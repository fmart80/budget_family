<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user === null && !empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            self::$user = $stmt->fetch() ?: null;
            if (self::$user === null) {
                unset($_SESSION['user_id']);
            }
        }
        return self::$user;
    }

    public static function isLocked(string $ip): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
        $stmt->execute([$ip, date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60)]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([mb_strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')
                ->execute([$ip, date('Y-m-d H:i:s')]);
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        self::login((int) $user['id']);
        return true;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        self::$user = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
    }
}
