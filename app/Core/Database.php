<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
            // Zona waktu sesi MySQL disamakan dengan Asia/Jakarta (jalan di semua versi PHP).
            $this->connection->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new \RuntimeException('Koneksi database gagal.');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}