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

    // Dynamically return the value of attribute
    // Example: echo $model -> attr
    function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }


    // Check if the local fields and database fields are
    // equal or not
    public function save(): bool
    {
        if (empty(static::$table)) {
            throw new Exception("Database table is not set.\n");
        }

        if (empty($this->attributes)) {
            throw new Exception('No Local Fields found to update');
        }

        try {
            // Fetch columns from database
            $stmt = self::$pdo->query("SHOW COLUMNS FROM " . static::$table);
            $dbFields = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Validate fields
            if (!$this->validateFields($dbFields)) {
                // If there are mismatches
                if (isset($this->attributes['id'])) {
                    $this->notifyAboutMismatch(true);
                } else {
                    $this->notifyAboutMismatch();
                }
            }

            // Prepare valid fields for the query
            $validFields = array_intersect(array_keys($this->attributes), $dbFields);

            if (empty($validFields)) {
                throw new Exception("No valid fields to save.");
            }

            // If ID exists, update the record
            if (isset($this->attributes['id'])) {
                // Prepare SET part of UPDATE statement
                $setParts = [];
                foreach ($validFields as $field) {
                    if ($field !== 'id') { // Skip ID in SET clause
                        $setParts[] = "$field = :$field";
                    }
                }

                if (empty($setParts)) {
                    throw new Exception("No fields to update.");
                }

                $sql = "UPDATE " . static::$table . " SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = self::$pdo->prepare($sql);

                // Bind values
                foreach ($validFields as $field) {
                    $stmt->bindValue(":$field", $this->attributes[$field]);
                }

                // Bind the id
                $stmt->bindValue(":id", $this->attributes['id']);
                // Run the query
                $stmt->execute();

                // Check if the query is successfully
                if($stmt->rowCount() != 0)
                    echo "Record updated successfully\n";
                else
                    echo "No rows affected. Check if the id is correct.\n";
            } // Otherwise insert a new record
            else {
                $columns = implode(', ', $validFields);
                $placeholders = implode(', ', array_map(function ($field) {
                    return ":$field";
                }, $validFields));

                $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
                $stmt = self::$pdo->prepare($sql);

                // Bind values
                foreach ($validFields as $field) {
                    $stmt->bindValue(":$field", $this->attributes[$field]);
                }

                $stmt->execute();
                $this->attributes['id'] = self::$pdo->lastInsertId();
                echo "Record inserted successfully. ID: " . $this->attributes['id'] . "\n";
            }
        } catch (PDOException $e) {
            echo "Database error : " . $e->getMessage() . "\n";
            return false;
        } catch (Exception $e) {
            echo "Error : " . $e->getMessage() . "\n";
            return false;
        }
        return true;
    }

$model = new Model();