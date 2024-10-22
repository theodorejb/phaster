<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test\src;

use Exception;
use mysqli;
use PeachySQL\Mysql;
use PeachySQL\PeachySql;
use PeachySQL\SqlServer;

class DbConnector
{
    private static Config $config;

    private static ?PeachySql $mssqlDb = null;
    private static ?PeachySql $mysqlDb = null;

    public static function setConfig(Config $config): void
    {
        self::$config = $config;
    }

    public static function getConfig(): Config
    {
        return self::$config;
    }

    public static function getMysqlConn(): PeachySql
    {
        if (!self::$mysqlDb) {
            $c = self::getConfig();
            $conn = new mysqli($c->getMysqlHost(), $c->getMysqlUser(), $c->getMysqlPassword(), $c->getMysqlDatabase());

            if ($conn->connect_error !== null) {
                throw new Exception('Failed to connect to MySQL: ' . $conn->connect_error);
            }

            self::$mysqlDb = new Mysql($conn);
            self::createMysqlTestTable(self::$mysqlDb);
        }

        return self::$mysqlDb;
    }

    public static function getSqlsrvConn(): PeachySql
    {
        if (!self::$mssqlDb) {
            $c = self::getConfig();
            $conn = sqlsrv_connect($c->getSqlsrvServer(), $c->getSqlsrvConnInfo());

            if (!$conn) {
                throw new Exception('Failed to connect to SQL server: ' . print_r(sqlsrv_errors(), true));
            }

            self::$mssqlDb = new SqlServer($conn);
            self::createSqlServerTestTable(self::$mssqlDb);
        }

        return self::$mssqlDb;
    }

    private static function createSqlServerTestTable(PeachySql $db): void
    {
        self::deleteTestTables($db);

        $sql = "
            CREATE TABLE Users (
                user_id INT PRIMARY KEY IDENTITY NOT NULL,
                name NVARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight FLOAT NOT NULL,
                isDisabled BIT NOT NULL
            )";

        $db->query($sql);

        $sql = "
            CREATE TABLE UserThings (
                thing_id INT PRIMARY KEY IDENTITY NOT NULL,
                user_id INT NOT NULL FOREIGN KEY REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }

    private static function createMysqlTestTable(PeachySql $db): void
    {
        self::deleteTestTables($db);

        $sql = "
            CREATE TABLE Users (
                user_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight DOUBLE NOT NULL,
                isDisabled BOOLEAN NOT NULL
            )";

        $db->query($sql);

        $sql = "
            CREATE TABLE UserThings (
                thing_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }

    public static function deleteTestTables(PeachySql $db): void
    {
        $sql = [
            'DROP TABLE IF EXISTS UserThings',
            'DROP TABLE IF EXISTS Users',
        ];

        foreach ($sql as $query) {
            $db->query($query);
        }
    }
}
