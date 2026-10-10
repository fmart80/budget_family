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

    /**
     * Applique les fichiers database/migrations/*.sql pas encore exécutés.
     * Permet de mettre à jour une installation existante simplement en envoyant les nouveaux fichiers.
     */
    public static function migrate(): void
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        if (!$files) {
            return;
        }
        $pdo = self::get();
        try {
            $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $done = [];
        }
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            self::runSqlFile($file);
            $pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)')
                ->execute([$name, date('Y-m-d H:i:s')]);
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
