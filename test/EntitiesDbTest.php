<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test;

use PeachySQL\PeachySql;
use PHPUnit\Framework\TestCase;
use theodorejb\Phaster\Entities;
use theodorejb\Phaster\Test\src\{DbConnector, Users, LegacyUsers, ModernUsers};

class EntitiesDbTest extends TestCase
{
    /**
     * @return list<array{0: PeachySql}>
     */
    public static function dbProvider(): array
    {
        $config = DbConnector::getConfig();
        $databases = [];

        if ($config->testMysql()) {
            $databases[] = [DbConnector::getMysqlConn()];
        }

        if ($config->testSqlsrv()) {
            $databases[] = [DbConnector::getSqlsrvConn()];
        }

        return $databases;
    }

    /**
     * @return list<array{0: Users}>
     */
    public static function entitiesProvider(): array
    {
        $list = [];

        foreach (self::dbProvider() as $db) {
            $list[] = [new Users($db[0])];
        }

        return $list;
    }

    /**
     * @return list<array{0: LegacyUsers}>
     */
    public static function legacyUsersProvider(): array
    {
        $list = [];

        foreach (self::dbProvider() as $db) {
            $list[] = [new LegacyUsers($db[0])];
        }

        return $list;
    }

    /**
     * @return list<array{0: ModernUsers, 1: PeachySql}>
     */
    public static function modernUsersProvider(): array
    {
        $list = [];

        foreach (self::dbProvider() as $db) {
            $list[] = [new ModernUsers($db[0]), $db[0]];
        }

        return $list;
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testAddEntities(Entities $entities): void
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
    public function testUpdateById(Entities $entities): void
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
    public function testPatchByIds(Entities $entities): void
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
    public function testEmptyQueries(Entities $entities): void
    {
        $ids = $entities->addEntities([]);
        $this->assertSame([], $entities->getEntitiesByIds($ids));
        $this->assertSame(0, $entities->patchByIds($ids, ['weight' => 10]));
        $this->assertSame(0, $entities->deleteByIds($ids));
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testDuplicateError(Entities $entities): void
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
            $this->assertStringContainsStringIgnoringCase(' duplicate ', $e->getMessage());
        }
    }

    /**
     * @dataProvider dbProvider
     */
    public function testConstraintError(PeachySql $db): void
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
            $this->assertStringContainsStringIgnoringCase(' constraint ', $e->getMessage());
        }
    }

    /**
     * @dataProvider entitiesProvider
     */
    public function testGetEntities(Entities $entities): void
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

        $actual = array_map(function (array $u): array {
            unset($u['id']);
            return $u;
        }, $entities->getEntities(['weight' => ['gt' => 250]], [], ['weight' => 'desc'], 10, 5));

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

    /**
     * @dataProvider entitiesProvider
     */
    public function testCountEntities(Entities $entities): void
    {
        $users = [];

        for ($i = 1; $i <= 20; $i++) {
            $users[] = [
                'name' => "Count user {$i}",
                'birthday' => '2000-03-04',
                'weight' => $i * 10,
            ];
        }

        $entities->addEntities($users);
        $actual = $entities->countEntities(['weight' => ['le' => 100], 'name' => ['lk' => 'Count user %']]);
        $this->assertSame(10, $actual);
    }

    /**
     * @dataProvider legacyUsersProvider
     */
    public function testLegacyUsers(Entities $entities): void
    {
        $users = [];

        for ($i = 1; $i <= 20; $i++) {
            $users[] = [
                'name' => "Legacy user {$i}",
                'birthday' => '2000-02-19',
                'isDisabled' => true,
                'weight' => $i * 10,
            ];
        }

        $ids = $entities->addEntities($users);
        $actual = $entities->getEntities(['id' => [$ids[3], $ids[4]], 'customFilter' => $ids[3]]);

        $expected = [
            [
                'id' => $ids[4],
                'name' => 'Legacy user 5',
                'birthday' => '2001-03-20',
                'thing' => ['id' => null],
            ],
        ];

        $this->assertSame($expected, $actual);

        $actual = $entities->getEntities(['id' => $ids[4]], ['name']);
        $this->assertSame([['name' => 'Legacy user 5']], $actual);
    }

    /**
     * @dataProvider modernUsersProvider
     */
    public function testModernUsers(Entities $entities, PeachySql $db): void
    {
        $users = [];

        for ($i = 1; $i <= 10; $i++) {
            $users[] = [
                'name' => "Modern user {$i}",
                'birthday' => '2000-02-20',
                'isDisabled' => false,
                'weight' => $i * 10,
            ];
        }

        $ids = $entities->addEntities($users);
        $this->assertSame(-42, $ids[1]); // manually set ID in processValues

        $db->insertRow('UserThings', ['user_id' => $ids[3]]);

        $actual = $entities->getEntitiesByIds([$ids[2], $ids[3]], ['id', 'name', 'isDisabled', 'computed', 'weight', 'thing.uid']);

        $expected = [
            [
                'id' => $ids[3],
                'name' => 'Modern user 4',
                'isDisabled' => false,
                'computed' => 41.0,
                'weight' => 40.0,
                'thing' => [
                    'uid' => $ids[3],
                ],
            ],
            [
                'id' => $ids[2],
                'name' => 'Modern user 3 modified',
                'isDisabled' => false,
                'computed' => 31.0,
                'weight' => 30.0,
                'thing' => null,
            ],
        ];

        $this->assertSame($expected, $actual);
    }
}
