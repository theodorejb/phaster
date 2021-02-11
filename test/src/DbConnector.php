<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test;

use Exception;
use mysqli;

class DbConnector
{
    private static ?mysqli $mysqlConn = null;

    /**
     * @var resource|null
     */
    private static $sqlsrvConn;

    /**
     * DB config settings
     * @var array
     */
    private static $config;

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function getConfig(): array
    {
        return self::$config;
    }

    public static function getMysqlConn(): mysqli
    {
        if (!self::$mysqlConn) {
            $mysql = self::$config['db']['mysql'];
            $dbPort = getenv('DB_PORT');

            if ($dbPort === false) {
                $dbPort = 3306;
            } else {
                $dbPort = (int) $dbPort;
            }

            self::$mysqlConn = new mysqli($mysql['host'], $mysql['username'], $mysql['password'], $mysql['database'], $dbPort);

            if (self::$mysqlConn->connect_errno) {
                throw new Exception('Failed to connect to MySQL: (' . self::$mysqlConn->connect_errno . ') ' . self::$mysqlConn->connect_error);
            }

            self::createMysqlTestTable(self::$mysqlConn);
        }

        return self::$mysqlConn;
    }

    /**
     * @return resource
     */
    public static function getSqlsrvConn()
    {
        if (!self::$sqlsrvConn) {
            $connInfo = self::$config['db']['sqlsrv']['connectionInfo'];
            self::$sqlsrvConn = sqlsrv_connect(self::$config['db']['sqlsrv']['serverName'], $connInfo);
            if (!self::$sqlsrvConn) {
                throw new Exception('Failed to connect to SQL server: ' . print_r(sqlsrv_errors(), true));
            }

            self::createSqlServerTestTable(self::$sqlsrvConn);
        }

        return self::$sqlsrvConn;
    }

    /**
     * @param resource $conn
     */
    private static function createSqlServerTestTable($conn): void
    {
        $sql = 'CREATE TABLE Users (
                    user_id INT PRIMARY KEY IDENTITY NOT NULL,
                    name VARCHAR(50) NOT NULL UNIQUE,
                    dob DATE NOT NULL,
                    weight FLOAT NOT NULL,
                    isDisabled BIT NOT NULL
                );';

        if (!sqlsrv_query($conn, $sql)) {
            throw new Exception('Failed to create SQL Server test table: ' . print_r(sqlsrv_errors(), true));
        }

        $sql = 'CREATE VIEW vUsers AS
                SELECT user_id AS u_id, name, dob, weight, isDisabled
                FROM Users;';

        if (!sqlsrv_query($conn, $sql)) {
            throw new Exception('Failed to create SQL Server test view: ' . print_r(sqlsrv_errors(), true));
        }

        $sql = 'CREATE TABLE UserThings (
                    thing_id INT PRIMARY KEY IDENTITY NOT NULL,
                    user_id INT NOT NULL FOREIGN KEY REFERENCES Users(user_id)
                );';

        if (!sqlsrv_query($conn, $sql)) {
            throw new Exception('Failed to create SQL Server UserThings table: ' . print_r(sqlsrv_errors(), true));
        }
    }

    private static function createMysqlTestTable(mysqli $conn): void
    {
        $sql = 'CREATE TABLE Users (
                    user_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                    name VARCHAR(50) NOT NULL UNIQUE,
                    dob DATE NOT NULL,
                    weight FLOAT NOT NULL,
                    isDisabled BOOLEAN NOT NULL
                );';

        if (!$conn->query($sql)) {
            throw new Exception('Failed to create MySQL test table: ' . print_r($conn->error_list, true));
        }

        $sql = 'CREATE VIEW vUsers AS
                SELECT user_id AS u_id, name, dob, weight, isDisabled
                FROM Users;';

        if (!$conn->query($sql)) {
            throw new Exception('Failed to create MySQL test view: ' . print_r($conn->error_list, true));
        }

        $sql = 'CREATE TABLE UserThings (
                    thing_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                    user_id INT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES Users(user_id)
                );';

        if (!$conn->query($sql)) {
            throw new Exception('Failed to create MySQL UserThings table: ' . print_r($conn->error_list, true));
        }
    }

    public static function deleteTestTables(): void
    {
        $sql = [
            'DROP TABLE UserThings',
            'DROP VIEW vUsers',
            'DROP TABLE Users',
        ];

        foreach ($sql as $query) {
            if (self::$mysqlConn) {
                if (!self::$mysqlConn->query($query)) {
                    throw new Exception('Failed to drop MySQL test table: ' . print_r(self::$mysqlConn->error_list, true));
                }
            }

            if (self::$sqlsrvConn) {
                if (!sqlsrv_query(self::$sqlsrvConn, $query)) {
                    throw new Exception('Failed to drop SQL Server test table: ' . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }
}
