<?php

class Database
{
    private static $pdo;

    public static function config()
    {
        return require __DIR__ . '/../config/database.php';
    }

    public static function pdo($withDatabase = true)
    {
        if ($withDatabase && self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::config();
        $database = $withDatabase ? ';dbname=' . $config['database'] : '';
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . $database . ';charset=' . $config['charset'];

        $pdo = new PDO($dsn, $config['username'], $config['password'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));

        if ($withDatabase) {
            self::$pdo = $pdo;
        }

        return $pdo;
    }

    public static function fetchAll($sql, $params = array())
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function fetch($sql, $params = array())
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    public static function execute($sql, $params = array())
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
