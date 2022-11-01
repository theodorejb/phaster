<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use PHPUnit\Framework\TestCase;
use Teapot\HttpException;

class EntitiesTest extends TestCase
{
    private array $propertyMap = [
        'name' => 'UserName',
        'client' => [
            'id' => 'ClientID',
            'name' => 'ClientName',
            'isDisabled' => 'isDisabled',
        ],
        'group' => [
            'type' => [
                'id' => 'GroupTypeID',
                'name' => 'GroupName',
            ],
        ],
    ];

    public function testSelectMapToPropMap(): void
    {
        $expected = [
            'name' => ['col' => 'UserName'],
            'client.id' => ['col' => 'ClientID'],
            'client.name' => ['col' => 'ClientName'],
            'client.isDisabled' => ['col' => 'isDisabled'],
            'group.type.id' => ['col' => 'GroupTypeID'],
            'group.type.name' => ['col' => 'GroupName'],
        ];

        $this->assertSame($expected, Entities::selectMapToPropMap($this->propertyMap));
    }

    public function testPropMapToSelectMap(): void
    {
        $props = [
            new Prop('name', 'UserName'),
            new Prop('client.id', 'ClientID'),
            new Prop('client.name', 'ClientName'),
            new Prop('client.isDisabled', 'isDisabled'),
            new Prop('group.type.id', 'GroupTypeID'),
            new Prop('group.type.name', 'GroupName'),
        ];

        $propMap = Helpers::propListToPropMap($props);
        $this->assertSame($this->propertyMap, Entities::propMapToSelectMap($propMap));
    }

    public function testPropMapToAliasMap(): void
    {
        $utc = new \DateTimeZone('UTC');

        $rawPropMap = [
            'id' => ['col' => 'UserID'],
            'username' => ['col' => 'a.UserName'],
            'client.isDisabled' => ['col' => 'c.isDisabled', 'alias' => 'isClientDisabled', 'type' => 'bool'],
            'dateCreated' => ['col' => 'DateCreatedUTC', 'timeZone' => $utc],
        ];

        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);

        $expected = [
            'UserID' => $propMap['id'],
            'UserName' => $propMap['username'],
            'isClientDisabled' => $propMap['client.isDisabled'],
            'DateCreatedUTC' => $propMap['dateCreated'],
        ];

        $this->assertSame($expected, Entities::propMapToAliasMap($propMap));
    }

    public function testMapRows(): void
    {
        /** @psalm-suppress MixedInferredReturnType, MixedReturnStatement */
        $usernameMapper = fn(array $row): string => ($row['UserID'] === 1) ? 'testUser' : $row['UserName'];

        $rawPropMap = [
            'id' => ['col' => 'UserID'],
            'username' => ['col' => 'a.UserName', 'getValue' => $usernameMapper, 'dependsOn' => ['id']],
            'client.id' => ['col' => 'a.ClientID'],
            'client.isDisabled' => ['col' => 'c.isDisabled', 'alias' => 'Disabled', 'type' => 'bool'],
            'client.type.id' => ['col' => 'TypeID', 'nullGroup' => true],
            'dateCreated' => ['col' => 'DateCreatedUTC', 'timeZone' => new \DateTimeZone('UTC')],
        ];

        // test mapping all fields with nullable client.type group
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'Disabled' => 0, 'TypeID' => 2, 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith',  'ClientID' => 2, 'Disabled' => 1, 'TypeID' => null, 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test',  'ClientID' => null, 'Disabled' => null, 'TypeID' => null, 'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'client' => ['id' => 1, 'isDisabled' => false, 'type' => ['id' => 2]], 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'client' => ['id' => 2, 'isDisabled' => true, 'type' => null], 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'client' => ['id' => null, 'isDisabled' => false, 'type' => null], 'dateCreated' => null],
        ];

        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);
        $this->assertSame($expected, Entities::mapRows($generator(), $propMap));

        // test mapping non-client fields
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith', 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test',  'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'dateCreated' => null],
        ];

        $fieldProps = Entities::getFieldPropMap(['id', 'username', 'dateCreated'], $propMap);
        $this->assertSame($expected, Entities::mapRows($generator(), $fieldProps));

        // test nullable client group when selecting client.isDisabled child property
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'Disabled' => 0, 'DateCreatedUTC' => '2019-02-18 09:01:35'];
            yield ['UserID' => 42, 'UserName' => 'jsmith',  'ClientID' => 2, 'Disabled' => 1, 'DateCreatedUTC' => '2018-05-20 23:22:40'];
            yield ['UserID' => 1, 'UserName' => 'test',  'ClientID' => null, 'Disabled' => null, 'DateCreatedUTC' => null];
        };

        $expected = [
            ['id' => 5, 'username' => 'theodoreb', 'client' => ['isDisabled' => false], 'dateCreated' => '2019-02-18T09:01:35+00:00'],
            ['id' => 42, 'username' => 'jsmith', 'client' => ['isDisabled' => true], 'dateCreated' => '2018-05-20T23:22:40+00:00'],
            ['id' => 1, 'username' => 'testUser', 'client' => null, 'dateCreated' => null],
        ];

        $rawPropMap['client.id']['nullGroup'] = true;
        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);
        $fieldProps = Entities::getFieldPropMap(['id', 'username', 'client.isDisabled', 'dateCreated'], $propMap);
        $this->assertSame($expected, Entities::mapRows($generator(), $fieldProps));

        // test nullable client group when selecting client.type.id grandchild property
        $generator = function (): \Generator {
            yield ['UserID' => 5, 'UserName' => 'theodoreb', 'ClientID' => 1, 'TypeID' => 2];
            yield ['UserID' => 42, 'UserName' => 'jsmith',  'ClientID' => 2, 'TypeID' => null];
            yield ['UserID' => 1, 'UserName' => 'test',  'ClientID' => null, 'TypeID' => null];
        };

        $expected = [
            ['username' => 'theodoreb', 'client' => ['type' => ['id' => 2]]],
            ['username' => 'jsmith', 'client' => ['type' => null]],
            ['username' => 'testUser', 'client' => null],
        ];

        $fieldProps = Entities::getFieldPropMap(['username', 'client.type.id'], $propMap);
        $this->assertSame($expected, Entities::mapRows($generator(), $fieldProps));
    }

    public function testGetFieldPropMap(): void
    {
        $rawPropMap = [
            'username' => ['col' => 'UserName', 'notDefault' => true],
            'client.id' => ['col' => 'ClientID', 'nullGroup' => true],
            'client.name' => ['col' => 'Company', 'alias' => 'ClientName'],
            'client.isDisabled' => ['col' => 'isDisabled'],
            'group.type.id' => ['col' => 'GroupTypeID'],
            'group.type.name' => ['col' => 'GroupName'],
            'groupName' => ['col' => 'DiffGroupName'],
        ];

        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);
        $expected = $propMap;
        unset($expected['username']);
        $this->assertSame($expected, Entities::getFieldPropMap([], $propMap));

        $expected = [
            'username' => $propMap['username'],
            'group.type.id' => $propMap['group.type.id'],
            'group.type.name' => $propMap['group.type.name'],
        ];

        $this->assertSame($expected, Entities::getFieldPropMap(['username', 'group'], $propMap));

        $expected = ['client.isDisabled', 'groupName', 'client.id'];
        $actual = Entities::getFieldPropMap(['client.isDisabled', 'groupName'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertFalse($actual['groupName']->noOutput);
        $this->assertTrue($actual['client.id']->noOutput);

        $expected = [
            'client.id' => $propMap['client.id'],
            'client.name' => $propMap['client.name'],
            'client.isDisabled' => $propMap['client.isDisabled'],
            'group.type.id' => $propMap['group.type.id'],
            'group.type.name' => $propMap['group.type.name'],
        ];

        $this->assertSame($expected, Entities::getFieldPropMap(['client', 'group.type'], $propMap));

        // test selection when dependant field is in a nullable group
        $valueGetter = fn(array $_row): string => '';
        $rawPropMap['groupName']['dependsOn'] = ['client.name'];
        $rawPropMap['groupName']['getValue'] = $valueGetter;
        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);

        $expected = ['groupName', 'client.name'];
        $actual = Entities::getFieldPropMap(['groupName'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertFalse($actual['groupName']->noOutput);
        $this->assertTrue($actual['client.name']->noOutput);

        try {
            Entities::getFieldPropMap(['group.test'], $propMap);
            $this->fail('Failed to throw HttpException for invalid field');
        } catch (HttpException $e) {
            $this->assertSame("'group.test' is not a valid field", $e->getMessage());
        }

        try {
            Entities::getFieldPropMap([' username'], $propMap);
            $this->fail('Failed to throw HttpException for invalid username field');
        } catch (HttpException $e) {
            $this->assertSame("' username' is not a valid field", $e->getMessage());
        }

        // test dependent field marked as not default
        $rawPropMap = [
            'username' => ['col' => 'UserName', 'notDefault' => true],
            'client.id' => ['col' => 'ClientID', 'nullGroup' => true, 'getValue' => $valueGetter, 'dependsOn' => ['username']],
            'client.name' => ['col' => 'Company', 'alias' => 'ClientName'],
        ];

        $props = Entities::rawPropMapToProps($rawPropMap);
        $propMap = Helpers::propListToPropMap($props);

        // don't select dependent username field since client.id isn't output anyway
        $expected = ['client.name', 'client.id'];
        $actual = Entities::getFieldPropMap(['client.name'], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertFalse($actual['client.name']->noOutput);
        $this->assertTrue($actual['client.id']->noOutput);

        // username should be selected even though it isn't default since client.id is marked as dependent on it
        $expected = ['client.id', 'client.name', 'username'];
        $actual = Entities::getFieldPropMap([], $propMap);
        $this->assertSame($expected, array_keys($actual));
        $this->assertFalse($actual['client.id']->noOutput);
        $this->assertFalse($actual['client.name']->noOutput);
        $this->assertTrue($actual['username']->noOutput);
    }

    public function testPropertiesToColumns(): void
    {
        $q = [
            'name' => 'testUser',
            'client' => [
                'name' => [
                    'lk' => 'test%',
                    'nl' => 'test1%',
                ],
                'isDisabled' => '0',
            ],
            'group' => [
                'type' => [
                    'name' => ['group 1', 'group 2'],
                ],
            ],
        ];

        $expected = [
            'UserName' => 'testUser',
            'ClientName' => [
                'lk' => 'test%',
                'nl' => 'test1%',
            ],
            'isDisabled' => '0',
            'GroupName' => ['group 1', 'group 2'],
        ];

        $this->assertSame($expected, Entities::propertiesToColumns($this->propertyMap, $q));

        $sort = [
            'client' => [
                'name' => 'asc',
                'isDisabled' => 'desc',
            ],
            'name' => 'asc',
        ];

        $sortExpectation = ['ClientName' => 'asc', 'isDisabled' => 'desc', 'UserName' => 'asc'];
        $this->assertSame($sortExpectation, Entities::propertiesToColumns($this->propertyMap, $sort));
        $this->assertSame([], Entities::propertiesToColumns($this->propertyMap, []));

        $binaryMap = [
            'prop' => 'SomeColumn',
            'binaryProp' => 'BinaryColumn',
        ];

        $binaryObj = [
            'prop' => 'val',
            'binaryProp' => ['abc123', 1],
        ];

        $binaryExpectation = [
            'SomeColumn' => 'val',
            'BinaryColumn' => ['abc123', 1],
        ];

        $this->assertSame($binaryExpectation, Entities::allPropertiesToColumns($binaryMap, $binaryObj));
    }

    public function testInvalidMap(): void
    {
        $invalidTypes = [null, true, false, 10, 1.5, new \stdClass()];

        foreach ($invalidTypes as $type) {
            try {
                Entities::propertiesToColumns(['prop' => $type], ['prop' => true]);
                $this->fail('Failed to throw exception for invalid map value');
            } catch (\Exception $e) {
                $this->assertSame("Map values must be arrays or strings, found " . gettype($type) . " for prop property", $e->getMessage());
            }
        }

        $duplicateColumns = [
            'prop1' => 'TestCol',
            'prop2' => [
                'subProp' => 'TestCol'
            ]
        ];

        $properties = [
            'prop1' => 'Hello',
            'prop2' => [
                'subProp' => 'World'
            ]
        ];

        try {
            Entities::allPropertiesToColumns($duplicateColumns, $properties);
            $this->fail('Failed to throw exception for duplicate map column');
        } catch (\Exception $e) {
            $this->assertSame("Column 'TestCol' is mapped to more than one property (prop2.subProp)", $e->getMessage());
        }
    }

    public function testInvalidPropertiesToColumns(): void
    {
        try {
            $badQ = [
                'client' => [
                    'lk' => 'foo%',
                ],
            ];

            Entities::propertiesToColumns($this->propertyMap, $badQ);
            $this->fail('Failed to throw exception for invalid property');
        } catch (HttpException $e) {
            $this->assertSame("Invalid client.lk property", $e->getMessage());
        }

        try {
            $badData = [ 'client' => null ];
            Entities::propertiesToColumns($this->propertyMap, $badData);
            $this->fail('Failed to throw exception for null property');
        } catch (HttpException $e) {
            $this->assertSame("Expected client property to be an object, got NULL", $e->getMessage());
        }

        try {
            $badData = [ 'client' => 'some string' ];
            Entities::propertiesToColumns($this->propertyMap, $badData);
            $this->fail('Failed to throw exception for invalid string property');
        } catch (\Exception $e) {
            $this->assertSame("Expected client property to be an object, got string", $e->getMessage());
        }
    }

    public function testPartialPropertiesToColumns(): void
    {
        $map =  [
            'name' => 'UserName',
            'client' => [
                'id' => 'ClientID',
            ],
            'group' => [
                'type' => [
                    'id' => 'GroupTypeID',
                ],
            ],
        ];

        $partialProperties = [
            // doesn't have all properties in map
            'name' => 'Test Name',
            'group' => ['type' => ['id' => 321]],
            'extra' => 'extra properties are valid',
        ];

        $expected = [
            'UserName' => 'Test Name',
            'GroupTypeID' => 321,
        ];

        $this->assertSame($expected, Entities::propertiesToColumns($map, $partialProperties, true));
    }

    public function testAllPropertiesToColumns(): void
    {
        $map =  [
            'name' => 'UserName',
            'client' => [
                'id' => 'ClientID',
            ],
            'group' => [
                'type' => [
                    'id' => 'GroupTypeID',
                ],
            ],
        ];

        try {
            Entities::allPropertiesToColumns($map, []);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required name property", $e->getMessage());
        }

        try {
            $missingClientId =  [
                'name' => 'Test Name',
                'client' => [],
            ];

            Entities::allPropertiesToColumns($map, $missingClientId);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required client.id property", $e->getMessage());
        }

        try {
            $missingGroupTypeId =  [
                'name' => 'Test Name',
                'client' => ['id' => null],
                'group' => ['type' => []],
            ];

            Entities::allPropertiesToColumns($map, $missingGroupTypeId);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required group.type.id property", $e->getMessage());
        }

        $valid = [
            'name' => 'Test Name',
            'client' => ['id' => null],
            'group' => ['type' => ['id' => 234]],
            'extra' => 'extra properties are valid when full map is required',
        ];

        $this->assertSame([], Entities::allPropertiesToColumns([], $valid));

        $expected = [
            'UserName' => 'Test Name',
            'ClientID' => null,
            'GroupTypeID' => 234
        ];

        $this->assertSame($expected, Entities::allPropertiesToColumns($map, $valid));
    }
}
