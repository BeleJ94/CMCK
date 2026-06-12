<?php

class Database
{
    private static $instance;
    private $pdo;

    private function __construct()
    {
        $config = config('database');

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

        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function connection()
    {
        return $this->pdo;
    }

    public function query($sql, array $params = [])
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize database singleton.');
    }
}
