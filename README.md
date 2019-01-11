# Phaster

[![Build Status](https://travis-ci.org/theodorejb/phaster.svg?branch=master)](https://travis-ci.org/theodorejb/phaster)

Phaster is a library for easily creating RESTful API endpoints.
It works well with the Slim Framework, and supports PHP 7.1+.

## Installation

`composer require theodorejb/phaster`

## Usage

Create a class extending theodorejb\Phaster\Entities. Implement the required methods (`getMap`,
`getIdColumn`, and `rowsToJson`). By default, the table name will be inferred from the
class name and all columns in the table will be selected, but this can be overridden
using the `getTableName` and `getBaseSelect` methods.

Pass the callable returned by the route handling functions to your Slim or other PSR-7
compatible framework.

```php
<?php

use theodorejb\Phaster\Entities;

class Users extends Entities
{
    protected function getIdColumn(): string
    {
        return "user_id";
    }

    protected function getMap(): array
    {
        // map columns in Users table
        return [
            'username' => 'uname',
            'firstName' => 'fname',
            'lastName' => 'lname',
            'office' => [
                'id' => 'office_id'
            ]
        ];
    }

    protected function getBaseSelect(): string
    {
        return 'SELECT u.user_id, u.uname, u.fname, u.lname, u.office_id, o.office_name
            FROM Users u
            INNER JOIN Offices o ON o.OfficeID = u.OfficeID';
    }

    protected function getSearchMap(): array
    {
        return array_merge_recursive($this->getMap(), [
            'office' => [
                'name' => 'office_name'
            ]
        ]);
    }

    protected function getDefaultSort(): array
    {
        return ['username' => 'asc'];
    }

    protected function rowsToJson(Generator $rows): array
    {
        $json = [];

        foreach ($rows as $row) {
            $json[] = [
                'id' => $row['user_id'],
                'username' => $row['uname'],
                'firstName' => $row['fname'],
                'lastName' => $row['lname'],
                'office' => [
                    'id' => $row['office_id'],
                    'name' => $row['office_name'],
                ],
            ];
        }

        return $json;
    }
}
```

```php
<?php

use My\DatabaseFactory;
use theodorejb\Phaster\{Entities, EntitiesFactory};

class MyEntitiesFactory implements EntitiesFactory
{
    public function createEntities($class): Entities
    {
        return new $class(DatabaseFactory::getDb());
    }
}
```

```php
<?php

use theodorejb\Phaster\RouteHandler;

$phaster = new RouteHandler(new MyEntitiesFactory());

$app->get('/users', $phaster->search(Users::class));
$app->get('/users/{id}', $phaster->getById(Users::class));
$app->post('/users', $phaster->insert(Users::class));
$app->put('/users/{id}', $phaster->update(Users::class));
$app->patch('/users/{id}', $phaster->patch(Users::class));
$app->delete('/users/{id}', $phaster->delete(Users::class));
```

## Author

Theodore Brown  
<http://theodorejb.me>

## License

MIT
