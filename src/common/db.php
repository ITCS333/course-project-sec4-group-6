<?php

if (!function_exists('getDBConnection')) {

    function getDBConnection(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {

            // Default local settings (XAMPP)
            $host = 'localhost';
            $db   = 'course';
            $user = 'admin';
            $pass = 'password123';

            // Replit environment variables
            if (getenv('DB_HOST')) {
                $host = getenv('DB_HOST');
                $db   = getenv('DB_NAME');
                $user = getenv('DB_USER');
                $pass = getenv('DB_PASS');
            }

            $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

            $pdo = new PDO($dsn, $user, $pass);

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return $pdo;
    }
}