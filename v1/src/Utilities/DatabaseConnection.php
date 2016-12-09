<?php
/**
 * Created by PhpStorm.
 * User: iamcaptaincode
 */

namespace CS3620_Final\Utilities;


class DatabaseConnection
{
    private static $instance = null;
    private static $host = "localhost";
    private static $dbname = "W01206777";
    private static $user = "W01206777";
    private static $pass = "Nathancs!";

    private function __construct()
    {

    }

    public static function getInstance()
    {
        if (!static::$instance === null) {
            return static::$instance;
        } else {
            try {
                $connectionString = "mysql:host=".static::$host.";dbname=".static::$dbname;
                static::$instance = new \PDO($connectionString, static::$user, static::$pass);
                static::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                return static::$instance;
            } catch (PDOException $e) {
                echo "Unable to connect to the database: " . $e->getMessage();
                die();
            }
        }
    }
}