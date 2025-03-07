<?php

namespace Model;

use Dotenv\Dotenv;
use Exception;
use PDO;


class Model
{
    protected static $table;
    protected static $pdo;
    protected $attributes = [];
    protected $missingFields = [];

    public function __construct($data = [])
    {
        // Loading the env file here for now. Will change later.
        try {

            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
            echo "ENV File load successful\n";
        } catch (Exception $e) {
            echo "ENV File load error " . $e->getMessage();
        }


        // Database variables
        $dbHost = $_ENV['DB_HOST'];
        $dbUser = $_ENV['DB_USER'];
        $dbPassword = $_ENV['DB_PASSWORD'];
        $dbName = $_ENV['DB_NAME'];

        // Check if any pdo connection available. If not then create a new
        // pdo connection.
        if (!self::$pdo) {
            try {
                $dsn = "mysql:
                    host=$dbHost;
                    dbname=$dbName";
                self::$pdo = new PDO($dsn, $dbUser, $dbPassword);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "Database Connection successful\n";
            } catch (Exception $e) {
                echo "Database connection error " . $e->getMessage() . "\n";
            }


        }
        // Assign the given parameters
        $this->attributes = $data;
    }

    // Dynamically add the attributes.
    // Example: $model -> attr = value
    function __get(string $name)
    {
        if (isset($this->attributes[$name]))
            return $this->attributes[$name];
        else {
            throw Exception("Key Not found. : Key = " . $name . "\n");
            return null;
        }
    }
}

$model = new Model();