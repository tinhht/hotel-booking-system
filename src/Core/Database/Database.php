<?php

namespace App\Core\Database;

use PDO;
use PDOException;

/**
 * Database Connection using Singleton Pattern
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config;
    private array $queryCache = [];
    private int $cacheMaxSize = 100;

    /**
     * @throws \Exception
     */
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(array $config = []): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Connect to database
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Execute a query with prepared statement caching
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            // Cache prepared statements to avoid repeated preparation
            $cacheKey = md5($sql);
            
            if (!isset($this->queryCache[$cacheKey])) {
                // Limit cache size to prevent memory issues
                if (count($this->queryCache) >= $this->cacheMaxSize) {
                    // Remove oldest cached statement
                    array_shift($this->queryCache);
                }
                $this->queryCache[$cacheKey] = $this->connection->prepare($sql);
            }
            
            $stmt = $this->queryCache[$cacheKey];
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

