<?php

namespace theodorejb\Phaster\Test;

use PeachySQL\Mysql;
use PeachySQL\PeachySql;
use theodorejb\Phaster\Test\src\App;

/**
 * @group mysql
 */
class MysqlDbTest extends DbTestCase
{
    private static ?PeachySql $db = null;

    public static function dbProvider(): PeachySql
    {
        if (!self::$db) {
            $c = App::$config;
            $conn = new \mysqli($c->getMysqlHost(), $c->getMysqlUser(), $c->getMysqlPassword(), $c->getMysqlDatabase());

            if ($conn->connect_error !== null) {
                throw new \Exception('Failed to connect to MySQL: ' . $conn->connect_error);
            }

            self::$db = new Mysql($conn);
            self::createTestTable(self::$db);
        }

        return self::$db;
    }

    private static function createTestTable(PeachySql $db): void
    {
        self::tearDownAfterClass();

        $sql = "
            CREATE TABLE Users (
                user_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL UNIQUE,
                dob DATE NOT NULL,
                weight DOUBLE NOT NULL,
                is_disabled BOOLEAN NOT NULL
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
}
