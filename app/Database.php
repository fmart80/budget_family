<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function isInstalled(): bool
    {
        try {
            self::get()->query('SELECT 1 FROM savings_contributions LIMIT 1');
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /** Exécute un fichier SQL composé de requêtes séparées par des « ; ». */
    public static function runSqlFile(string $file): void
    {
        $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            self::get()->exec($statement);
        }
    }
}
