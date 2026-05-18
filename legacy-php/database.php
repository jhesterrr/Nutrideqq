<?php
// Set default timezone for the application
date_default_timezone_set('Asia/Manila');

class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port;
    private static $connection = null;

    public function __construct()
    {
        // Try to get the connection string first (Railway/Render standard)
        $urlString = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: getenv('MYSQLURL');
        
        if ($urlString) {
            $url = parse_url($urlString);
            $this->host = $url['host'] ?? 'localhost';
            $this->dbname = ltrim($url['path'] ?? 'nutrideq', '/');
            $this->username = $url['user'] ?? 'root';
            $this->password = $url['pass'] ?? '';
            $this->port = $url['port'] ?? '3306';
        } else {
            // Fallback to individual environment variables
            $this->host = getenv('MYSQLHOST') ?: 'localhost';
            $this->dbname = getenv('MYSQLDATABASE') ?: 'nutrideq';
            $this->username = getenv('MYSQLUSER') ?: 'root';
            $this->password = getenv('MYSQLPASSWORD') ?: '';
            $this->port = getenv('MYSQLPORT') ?: '3306';
        }
    }

    public function getConnection()
    {
        // Use existing connection if available to save time
        if (self::$connection !== null) {
            return self::$connection;
        }

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->dbname . ";charset=utf8mb4";
            self::$connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_PERSISTENT => true, // Keep connection open and reuse pools
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            return self::$connection;
        } catch (PDOException $exception) {
            throw new Exception('Database connection failed: ' . $exception->getMessage());
        }
    }
}
?>