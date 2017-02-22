<?php

namespace Phaster;

use PHPUnit\Framework\TestCase;
use Teapot\HttpException;

class EntitiesTest extends TestCase
{
    private $propertyMap = [
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

    public function testPropertiesToColumns()
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
        ];

        $sortExpectation = ['ClientName' => 'asc', 'isDisabled' => 'desc'];
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

        $this->assertSame($binaryExpectation, Entities::propertiesToColumns($binaryMap, $binaryObj, true));
    }

    public function testInvalidMap()
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
            Entities::propertiesToColumns($duplicateColumns, $properties, true);
            $this->fail('Failed to throw exception for duplicate map column');
        } catch (\Exception $e) {
            $this->assertSame("Column 'TestCol' is mapped to more than one property (prop2.subProp)", $e->getMessage());
        }
    }

    public function testInvalidPropertiesToColumns()
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

    public function testRequireFullMap()
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
            Entities::propertiesToColumns($map, [], true);
            $this->fail('Failed to throw exception for missing property');
        } catch (HttpException $e) {
            $this->assertSame("Missing required name property", $e->getMessage());
        }

        try {
            $missingClientId =  [
                'name' => 'Test Name',
                'client' => [],
            ];

            Entities::propertiesToColumns($map, $missingClientId, true);
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

            Entities::propertiesToColumns($map, $missingGroupTypeId, true);
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

        $this->assertSame([], Entities::propertiesToColumns([], $valid, true));

        $expected = [
            'UserName' => 'Test Name',
            'ClientID' => null,
            'GroupTypeID' => 234
        ];

        $this->assertSame($expected, Entities::propertiesToColumns($map, $valid, true));
    }
}
