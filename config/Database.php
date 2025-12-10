<?php
class Database {
    private $conn;

    public function getConnection(): PDO {
        if ($this->conn) return $this->conn;
        $host = '127.0.0.1';
        $port = '8889';
        $db   = 'recruitment_app';
        $user = 'root';
        $pass = 'root';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->conn = new PDO($dsn, $user, $pass, $options);
        $this->conn->exec("SET time_zone = '+00:00'");
        return $this->conn;
    }
}

