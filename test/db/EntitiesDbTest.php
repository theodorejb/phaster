<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use PeachySQL\{Mysql, PeachySql, SqlServer};
use PHPUnit\Framework\TestCase;

class EntitiesDbTest extends TestCase
{
    public static function tearDownAfterClass()
    {
        TestDbConnector::deleteTestTables();
    }

    public function dbProvider(): array
    {
        $config = TestDbConnector::getConfig();
        $databases = [];

        if ($config['testWith']['mysql']) {
            $databases[] = [new Mysql(TestDbConnector::getMysqlConn())];
        }

        if ($config['testWith']['sqlsrv']) {
            $databases[] = [new SqlServer(TestDbConnector::getSqlsrvConn())];
        }

        return $databases;
    }

    public function entitiesProvider(): array
    {
        $mapper = function (array $db) { return [new Users($db[0])]; };
        return array_map($mapper, $this->dbProvider());
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testAddEntities(Entities $entities)
    {
        try {
            $entities->addEntities([[
                'name' => 'My name',
                'birthday' => '2017-03-05',
            ]]);

            throw new \Exception('Failed to throw exception for missing required property');
        } catch (\Exception $e) {
            $this->assertSame('Missing required weight property', $e->getMessage());
        }

        $users = [
            [
                'name' => 'My Name',
                'birthday' => '2017-03-05',
                'weight' => 130.0,
                // leave out isDisabled since it's optional
            ],
            [
                'name' => 'Another Name',
                'birthday' => '2016-04-06',
                'weight' => 200.0,
                'isDisabled' => true,
            ],
        ];

        $ids = $entities->addEntities($users);

        $users[0] = array_merge(['id' => $ids[0]], $users[0], ['isDisabled' => false]);
        $users[1] = array_merge(['id' => $ids[1]], $users[1]);

        $this->assertSame($users, $entities->getEntitiesByIds($ids));
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testUpdateById(Entities $entities)
    {
        try {
            // optional properties should still be required when replacing an entity
            $entities->updateById(0, [
                'name' => 'My Name',
                'birthday' => '2017-03-05',
                'weight' => 130.0,
            ]);

            throw new \Exception('Failed to throw exception for missing property');
        } catch (\Exception $e) {
            $this->assertSame('Missing required isDisabled property', $e->getMessage());
        }

        $user = [
            'name' => 'Wrong Name',
            'birthday' => '2015-04-06',
            'weight' => 250.0,
            'isDisabled' => true,
        ];

        $id = $entities->addEntities([$user])[0];

        $newUser = [
            'id' => $id,
            'name' => 'Right Name',
            'birthday' => '2016-05-07',
            'weight' => 215.0,
            'isDisabled' => false,
        ];

        $entities->updateById($id, $newUser);
        $this->assertSame($newUser, $entities->getEntityById($id));
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testPatchByIds(Entities $entities)
    {
        $users = [
            [
                'name' => 'Name 1',
                'birthday' => '2010-11-12',
                'weight' => 100.0,
            ],
            [
                'name' => 'Name 2',
                'birthday' => '2011-12-13',
                'weight' => 123.0,
            ],
        ];

        $ids = $entities->addEntities($users);

        $expected = array_map(function ($u, $i) {
            return array_merge(['id' => $i], $u, ['isDisabled' => false]);
        }, $users, $ids);

        $this->assertSame($expected, $entities->getEntitiesByIds($ids));

        $entities->patchByIds($ids, [
            'weight' => 125.0,
        ]);

        $newExpected = array_map(function ($u) {
            return array_merge($u, ['weight' => 125.0]);
        }, $expected);

        $this->assertSame($newExpected, $entities->getEntitiesByIds($ids));

        $entities->deleteByIds($ids);
        $this->assertSame([], $entities->getEntitiesByIds($ids));
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testEmptyQueries(Entities $entities)
    {
        $ids = $entities->addEntities([]);
        $this->assertSame([], $entities->getEntitiesByIds($ids));
        $this->assertSame(0, $entities->patchByIds($ids, ['weight' => 10]));
        $this->assertSame(0, $entities->deleteByIds($ids));
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testGetDuplicateError(Entities $entities)
    {
        $users = [
            [
                'name' => 'Conflicting Name',
                'birthday' => '2010-11-12',
                'weight' => 111.0,
            ],
            [
                'name' => 'Conflicting Name',
                'birthday' => '2011-12-13',
                'weight' => 121.0,
            ],
        ];

        try {
            $entities->addEntities($users);
            throw new \Exception('Failed to throw duplicate name exception');
        } catch (\Exception $e) {
            $this->assertSame('A user with this name already exists', $e->getMessage());
        }
    }

    /**
     * @dataProvider dbProvider
     */
    public function testGetConstraintError(PeachySql $db)
    {
        $user = [
            'name' => 'Some Name',
            'birthday' => '2001-06-17',
            'weight' => 156,
        ];

        $entities = new Users($db);
        $id = $entities->addEntities([$user])[0];
        $db->insertRow('UserThings', ['user_id' => $id]);

        try {
            $entities->deleteByIds([$id]);
            throw new \Exception('Failed to throw constraint violation exception');
        } catch (\Exception $e) {
            $this->assertSame('Failed to delete user: it still has things referencing it', $e->getMessage());
        }
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testGetEntities(Entities $entities)
    {
        $users = [];

        for ($i = 1; $i <= 50; $i++) {
            $users[] = [
                'name' => "User {$i}",
                'birthday' => '2000-02-04',
                'weight' => $i * 10,
            ];
        }

        $entities->addEntities($users);

        $actual = array_map(function ($u) {
            unset($u['id']);
            return $u;
        }, $entities->getEntities(['weight' => ['gt' => 250]], ['weight' => 'desc'], 10, 5));

        $expected = [];

        for ($j = 40; $j > 35; $j--) {
            $expected[] = [
                'name' => "User {$j}",
                'birthday' => '2000-02-04',
                'weight' => $j * 10.0,
                'isDisabled' => false,
            ];
        }

        $this->assertSame($expected, $actual);
    }
}
