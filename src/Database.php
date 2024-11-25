<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

use PDO;
use PDOStatement;

/**
 * The Database class provides a simple interface for interacting with a MySQL database using PDO. It supports common
 * operations like querying, inserting, updating, and deleting records, as well as checking record existence and counts.
 * The class uses the Singleton pattern for a single database connection instance and the Injection trait for dependency
 * injection.
 *
 * @package Enicore\Maris
 */
class Database
{
    protected PDO $pdo;

    /**
     * Constructor to initialize the PDO instance with provided settings.
     *
     * @param string $host
     * @param string|int $port
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array $options
     */
    public function __construct(string $host, string|int $port, string $database, string $username, string $password,
                                array $options = [])
    {
        $this->pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password, $options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("SET CHARACTER SET utf8;SET NAMES utf8;");
    }

    /**
     * Executes a query with optional parameters.
     *
     * @param string $query SQL query string
     * @param mixed $parameters Optional parameters for the query
     * @return bool|PDOStatement Returns false if the query fails, otherwise PDOStatement
     */
    public function query(string $query, mixed $parameters = false): bool|PDOStatement
    {
        if (!$parameters) {
            $parameters = [];
        } else if (!is_array($parameters)) {
            $parameters = [$parameters];
        }

        if (($st = $this->pdo->prepare($query)) && $st->execute($parameters)) {
            return $st;
        }

        return false;
    }

    /**
     * Fetches all rows resulting from the query.
     *
     * @param string $query SQL query string
     * @param mixed $parameters Optional parameters for the query
     * @return array Array of results as associative arrays
     */
    public function all(string $query, mixed $parameters = false): array
    {
        if ($st = $this->query($query, $parameters)) {
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }

    /**
     * Fetches a single row resulting from the query.
     *
     * @param string $query SQL query string
     * @param mixed $parameters Optional parameters for the query
     * @return array|false Array of the result or false if no rows are found
     */
    public function row(string $query, mixed $parameters = false): array|false
    {
        if ($st = $this->query($query, $parameters)) {
            if ($array = $st->fetch(PDO::FETCH_ASSOC)) {
                return $array;
            }
        }

        return false;
    }

    /**
     * Fetches all rows from a specified table based on provided conditions.
     *
     * @param string $table Table name
     * @param array|string $selectFields Fields to select, default is "*"
     * @param array $whereParams Conditions to apply
     * @param string|null $orderBy Field to order by
     * @param string $orderType ASC or DESC
     * @param int|null $limit Limit number of results
     * @param int|null $offset Offset for pagination
     * @return bool|array Array of results or false if no results
     */
    public function getAll(string $table, array|string $selectFields = "*", array $whereParams = [],
                           string $orderBy = null, string $orderType = "ASC", int $limit = null,
                           int $offset = null): bool|array
    {
        is_array($selectFields) || ($selectFields = [$selectFields]);
        $params = [];
        $where = [];

        foreach ($whereParams as $key => $val) {
            $where[] = "$key=:$key";
            $params[":$key"] = $val;
        }

        $fields = implode(",", $selectFields);
        $where = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $order = $orderBy ? "ORDER BY $orderBy $orderType" : "";
        $limit = $limit ? "LIMIT $limit" . ($offset ? ",$offset" : "") : "";

        if (($st = $this->query("SELECT $fields FROM `$table` $where $order $limit", $params)) &&
            ($array = $st->fetchAll(PDO::FETCH_ASSOC))
        ) {
            return $array;
        }

        return false;
    }

    /**
     * Fetches the first row from a specified table based on conditions.
     *
     * @param string $table Table name
     * @param array|string $selectFields Fields to select, default is "*"
     * @param array $whereParams Conditions to apply
     * @param false|string $orderBy Field to order by
     * @param string $orderType ASC or DESC
     * @return array|false Array of the first row or false if not found
     */
    public function getFirst(string $table, array|string $selectFields = "*", array $whereParams = [],
                             false|string $orderBy = false, string $orderType = "ASC"): array|false
    {
        is_array($selectFields) || ($selectFields = [$selectFields]);
        $params = [];
        $where = [];

        foreach ($whereParams as $key => $val) {
            $where[] = "$key=:$key";
            $params[":$key"] = $val;
        }

        $fields = implode(",", $selectFields);
        $where = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $order = $orderBy ? "ORDER BY $orderBy $orderType" : "";

        if (($st = $this->query("SELECT $fields FROM `$table` $where $order LIMIT 1", $params)) &&
            ($array = $st->fetch(PDO::FETCH_ASSOC))
        ) {
            return $array;
        }

        return false;
    }

    /**
     * Retrieves the last inserted record ID.
     *
     * @return string|false Last inserted ID or false on failure
     */
    public function getLastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Retrieves the last error message from the database query.
     *
     * @return string Error message
     */
    public function getLastError(): string
    {
        $array = $this->pdo->errorInfo();
        return $array[2];
    }

    /**
     * Checks if a record exists in a table based on conditions.
     *
     * @param string $table Table name
     * @param string $whereQuery Optional WHERE clause
     * @param array $whereParameters Parameters for the WHERE clause
     * @return bool True if record exists, false otherwise
     */
    public function exists(string $table, string $whereQuery = "", array $whereParameters = []): bool
    {
        if ($whereQuery) {
            $whereQuery = "WHERE " . $whereQuery;
        }

        return ($st = $this->query("SELECT * FROM `$table` $whereQuery", $whereParameters)) &&
            $st->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * Counts the number of records in a table based on conditions.
     *
     * @param string $table Table name
     * @param string $whereQuery Optional WHERE clause
     * @param array $whereParameters Parameters for the WHERE clause
     * @return int|false False if query fails, otherwise the count
     */
    public function count(string $table, string $whereQuery = "", array $whereParameters = []): int|false
    {
        if ($whereQuery) {
            $whereQuery = "WHERE " . $whereQuery;
        }

        if ($st = $this->query("SELECT COUNT(*) FROM `$table` $whereQuery", $whereParameters)) {
            return (int)$st->fetch(PDO::FETCH_COLUMN);
        }

        return false;
    }

    /**
     * Inserts a new record into a specified table.
     *
     * @param string $table Table name
     * @param array $data Data to insert as key-value pairs
     * @return bool|PDOStatement Returns false on failure, otherwise PDOStatement
     */
    public function insert(string $table, array $data): bool|PDOStatement
    {
        $keys = [];
        $values = [];
        $params = [];

        foreach ($data as $key => $val) {
            $keys[] = $key;
            $values[] = ":$key";
            $params[":$key"] = $val;
        }

        return $this->query(
            "INSERT INTO `$table` (" . implode(",", $keys) . ") VALUES (" . implode(",", $values) . ")",
            $params
        );
    }

    /**
     * Updates a record in a specified table based on conditions.
     *
     * @param string $table Table name
     * @param array $data Data to update as key-value pairs
     * @param array $whereParams Conditions for the WHERE clause
     * @return bool|PDOStatement Returns false on failure, otherwise PDOStatement
     */
    public function update(string $table, array $data, array $whereParams): bool|PDOStatement
    {
        $values = [];
        $params = [];
        $where = [];

        foreach ($data as $key => $val) {
            $values[] = "$key=:$key";
            $params[":" . $key] = $val;
        }

        foreach ($whereParams as $key => $val) {
            $where[] = "$key=:$key";
            $params[":$key"] = $val;
        }

        return $this->query(
            "UPDATE `$table` SET " . implode(",", $values) . " WHERE " . implode(" AND ", $where),
            $params
        );
    }

    /**
     * Deletes a record from a specified table based on conditions.
     *
     * @param string $table Table name
     * @param array $whereParams Conditions for the WHERE clause
     * @return bool|PDOStatement Returns false on failure, otherwise PDOStatement
     */
    public function delete(string $table, array $whereParams): bool|PDOStatement
    {
        $params = [];
        $where = [];

        foreach ($whereParams as $key => $val) {
            $where[] = "$key=:$key";
            $params[":$key"] = $val;
        }

        return $this->query("DELETE FROM `$table` WHERE " . implode(" AND ", $where), $params);
    }
}
