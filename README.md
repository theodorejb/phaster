# Phaster

[![Build Status](https://travis-ci.org/theodorejb/phaster.svg?branch=master)](https://travis-ci.org/theodorejb/phaster)

Phaster is a library for easily creating RESTful API endpoints.
It works well with the Slim Framework, and is tested on PHP 7+ as well as HHVM.

## Installation

`composer require theodorejb/phaster`

## Usage

Create a class extending Phaster\Entities. Implement the required methods (`getMap`,
`getIdColumn`, and `rowsToJson`). By default, the table name will be inferred from the
class name and all columns in the table will be selected, but this can be overridden
using the `getTableName` and `getBaseSelect` methods.

Pass the callable returned by the route handling functions to your Slim or other PSR-7
compatible framework.

```php
<?php

use Phaster\Entities;

class Users extends Entities
{
    protected function getIdColumn(): string
    {
        return "UserID";
    }

    protected function getMap(): array
    {
        return [
            'username' => 'uname',
            'firstName' => 'fname',
            'lastName' => 'lname',
            'office' => [
                'id' => 'OfficeID'
            ]
        ];
    }

    protected function getSearchMap(): array
    {
        return array_merge_recursive($this->getMap(), [
            'office' => [
                'name' => 'OfficeName'
            ]
        ]);
    }

    protected function getDefaultSort(): array
    {
        return ['username'];
    }

    protected function rowsToJson(Generator $rows): array
    {
        $json = [];

        foreach ($rows as $row) {
            $json[] = new Entity($row);
        }

        return $json;
    }
}
```

```php
<?php
use My\DatabaseFactory;
use Phaster\{Entities, EntitiesFactory, RouteHandler};

class MyEntitiesFactory implements EntitiesFactory
{
    private $dbName;

    public function __construct(string $dbName)
    {
        $this->dbName = $dbName;
    }

    public function createEntities($class): Entities
    {
        return new $class(DatabaseFactory::getDb($this->dbName));
    }
}

$phaster = new RouteHandler(new MyEntitiesFactory('MainDB'));

$app->get('/users', $phaster->search(Users::class));
```

## Author

Theodore Brown  
<http://theodorejb.me>

## License

MIT
