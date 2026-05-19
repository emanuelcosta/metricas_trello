<?php
declare(strict_types=1);

namespace App\Models;

class Database
{
    private static ?\PDO $instance = null;

    public static function connect(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dbFile = BASE_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'metricas_trello.sqlite';
        $isNew  = !is_file($dbFile);

        $pdo = new \PDO('sqlite:' . $dbFile, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');

        if ($isNew) {
            self::runSchema($pdo);
        }

        self::$instance = $pdo;
        return $pdo;
    }

    public static function isAvailable(): bool
    {
        try {
            self::connect();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function runSchema(\PDO $pdo): void
    {
        $schemaPath = BASE_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema_trello.sql';
        if (!is_file($schemaPath)) {
            return;
        }
        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            return;
        }
        // Execute each statement separately (PDO SQLite doesn't support multi-statement exec reliably)
        foreach (self::splitStatements($sql) as $stmt) {
            $pdo->exec($stmt);
        }
    }

    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            // Skip comments
            if (str_starts_with($trimmed, '--') || $trimmed === '') {
                continue;
            }
            $current .= ' ' . $trimmed;
            if (str_ends_with($trimmed, ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }
        return $statements;
    }
}
