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
            $dbColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove the auto increment values value
            $dbColumns = $this->removeAutoIncrement($dbColumns);

            // Validate fields
            if (!$this->validateFields($dbColumns)) {
                // If there are mismatches
                if (isset($this->attributes['id'])) {
                    $this->notifyAboutMismatch(true);
                } else {
                    $this->notifyAboutMismatch();
                }
            }

            // Prepare valid fields for the query
            $validColumns = array_intersect(array_keys($this->attributes), $dbColumns);

            if (empty($validColumns)) {
                throw new Exception("No valid fields to save.");
            }

            // If ID exists, update the record
            if (isset($this->attributes['id'])) {
                // Prepare SET part of UPDATE statement
                $setParts = [];
                foreach ($validColumns as $column) {
                    if ($column !== 'id') { // Skip ID in SET clause
                        $setParts[] = "$column = :$column";
                    }
                }

                if (empty($setParts)) {
                    throw new Exception("No fields to update.");
                }

                $sql = "UPDATE " . static::$table . " SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = self::$pdo->prepare($sql);

                // Bind values
                foreach ($validColumns as $column) {
                    $stmt->bindValue(":column", $this->attributes[$column]);
                }

                // Bind the id
                $stmt->bindValue(":id", $this->attributes['id']);
                // Run the query
                $stmt->execute();

                // Check if the query is successfully
                if ($stmt->rowCount() != 0)
                    echo "Record updated successfully\n";
                else
                    echo "No rows affected. Check if the id is correct.\n";
            } // Otherwise insert a new record
            else {
                $columns = implode(', ', $validColumns);
                $placeholders = implode(', ', array_map(function ($field) {
                    return ":$field";
                }, $validColumns));

                $sql = "INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)";
                $stmt = self::$pdo->prepare($sql);

                // Bind values
                foreach ($validColumns as $column) {
                    $stmt->bindValue(":$column", $this->attributes[$column]);
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

    // Validate fields against database columns
    protected function validateFields($dbFields): bool
    {
        // Extract local field names
        $localFields = array_keys($this->attributes);

        // Check for missing Database fields that are not in local
        $missingInLocal = array_diff($dbFields, $localFields);

        // Check for Local fields that are not in DB
        $inLocal = array_diff($localFields, $dbFields);

        $this->missingFields = [
            'missingInLocal' => $missingInLocal,
            'inLocal' => $inLocal
        ];

        // Return true if there are no mismatches
        return empty($missingInLocal) && empty($inLocal);
    }

    // Notify user about mismatches
    protected function notifyAboutMismatch($isUpdate = false): void
    {
        if (!empty($this->missingFields['missingInLocal']) && !$isUpdate) {
            $fields = implode(', ', $this->missingFields['missingInLocal']);
            throw new Exception('Fields are missing. Add following fields: ' . $fields);
        }

        if (!empty($this->missingFields['inLocal'])) {
            $fields = implode(', ', $this->missingFields['inLocal']);
            throw new Exception('Fields are not in database. Remove following fields: ' . $fields);
        }
    }

    // Remove column if it has auto increment
    protected function removeAutoIncrement($dbColumns)
    {
        try {
            // Using a query that doesn't need placeholders for table names
            $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '" . static::$table . "' AND EXTRA LIKE '%auto_increment%'";

            $stmt = self::$pdo->query($sql);
            $autoIncrementColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        $dbColumns = array_filter($dbColumns, function($column) use ($autoIncrementColumns) {
            return !in_array($column, $autoIncrementColumns);
        });
        return $dbColumns;
    }
}
