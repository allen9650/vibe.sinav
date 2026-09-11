<?php
/**
 * Database Connection Singleton
 * PDO wrapper with secure defaults
 */

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get PDO instance (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require CONFIG_PATH . '/database.php';

            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw new RuntimeException('Database connection failed: ' . $e->getMessage());
                }
                throw new RuntimeException('Database connection failed. Please check your configuration.');
            }
        }

        return self::$instance;
    }

    /**
     * Get PDO instance without database selected (for installation)
     */
    public static function getInstanceWithoutDB(): PDO
    {
        $config = require CONFIG_PATH . '/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['charset']
        );

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options']
        );
    }

    /**
     * Execute a query with parameters
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single column value
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    /**
     * Insert a row and return the last insert ID
     */
    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn($col) => "`$col`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        self::query($sql, array_values($data));

        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Update rows
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE $where";

        $stmt = self::query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete rows
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    private static int $transactionDepth = 0;

    /**
     * Begin transaction (supports nested transaction calls)
     */
    public static function beginTransaction(): void
    {
        if (self::$transactionDepth === 0 || !self::getInstance()->inTransaction()) {
            self::getInstance()->beginTransaction();
        }
        self::$transactionDepth++;
    }

    /**
     * Commit transaction
     */
    public static function commit(): void
    {
        self::$transactionDepth--;
        if (self::$transactionDepth <= 0) {
            self::$transactionDepth = 0;
            if (self::getInstance()->inTransaction()) {
                self::getInstance()->commit();
            }
        }
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): void
    {
        self::$transactionDepth = 0;
        if (self::getInstance()->inTransaction()) {
            self::getInstance()->rollBack();
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}
}
