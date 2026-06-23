<?php

/**
 * Database Singleton — Reuse connection efficiently
 * Gunakan: $db = Database::getInstance();
 */
class Database {
    
    private static $instance = null;
    private $connection = null;

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create connection
     */
    private function connect() {
        $this->connection = new mysqli(
            getenv('MYSQLHOST') ?: 'localhost',
            getenv('MYSQLUSER') ?: 'root',
            getenv('MYSQLPASSWORD') ?: '',
            getenv('MYSQLDATABASE') ?: 'db_absensi',
            getenv('MYSQLPORT') ?: 3306
        );

        if ($this->connection->connect_error) {
            Response::serverError('Koneksi database gagal: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset('utf8mb4');
    }

    /**
     * Get raw mysqli connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Prepare statement (shorthand)
     */
    public function prepare($query) {
        return $this->connection->prepare($query);
    }

    /**
     * Execute query and fetch all results
     */
    public function fetchAll($query, $params = [], $types = '') {
        $stmt = $this->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    /**
     * Execute query and fetch single row
     */
    public function fetchOne($query, $params = [], $types = '') {
        $stmt = $this->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Execute insert/update/delete
     */
    public function execute($query, $params = [], $types = '') {
        $stmt = $this->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $insertId = $stmt->insert_id;
        $stmt->close();
        return ['affected' => $affected, 'insertId' => $insertId];
    }

    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialize
     */
    public function __wakeup() {
        throw new Exception('Tidak bisa unserialize Database instance');
    }
}
