<?php

namespace theodorejb\Phaster\Test;

use PeachySQL\PeachySql;
use PeachySQL\SqlServer;
use theodorejb\Phaster\Test\src\App;

/**
 * @group mssql
 */
class MssqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;
            $conn = sqlsrv_connect($c->getSqlsrvServer(), $c->getSqlsrvConnInfo());

            if (!$conn) {
                throw new \Exception('Failed to connect to SQL server: ' . print_r(sqlsrv_errors(), true));
            }

            self::$db = new SqlServer($conn);
            self::createTestTable(self::$db);
        }

        return self::$db;
    }

    private static function createTestTable(PeachySql $db): void
    {
        self::tearDownAfterClass();

        $sql = "
            CREATE TABLE Users (
                user_id INT PRIMARY KEY IDENTITY NOT NULL,
                name NVARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight FLOAT NOT NULL,
                isDisabled BIT NOT NULL
            );
            CREATE TABLE UserThings (
                thing_id INT PRIMARY KEY IDENTITY NOT NULL,
                user_id INT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES Users(user_id)
            )";

        $db->query($sql);
    }
}
