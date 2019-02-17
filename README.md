# Phaster

[![Build Status](https://travis-ci.org/theodorejb/phaster.svg?branch=master)](https://travis-ci.org/theodorejb/phaster)

Phaster is a library for easily creating RESTful API endpoints.
It works well with the Slim Framework, and supports PHP 7.1+.

## Installation

`composer require theodorejb/phaster`

## Usage

Create a class extending theodorejb\Phaster\Entities. Implement the `getMap()`
and `getDefaultSort()` methods. By default, the table name will be inferred
from the class name, and all mapped columns in this table will be selected.

To join other tables and alter output values, implement the `getBaseQuery()`
and `getPropMap()` methods.  Pass the callable returned by the route handling
functions to your Slim or other PSR-7 compatible framework.

```php
<?php

use theodorejb\Phaster\{Entities, QueryOptions};

class Users extends Entities
{
    protected function getMap(): array
    {
        // map columns in Users table
        return [
            'username' => 'uname',
            'firstName' => 'fname',
            'lastName' => 'lname',
            'office' => [
                'id' => 'office_id',
            ],
        ];
    }

    protected function getPropMap(): array
    {
        return [
            // valid options can be found in the Prop class
            'id' => ['col' => 'u.user_id'],
            'office.name' => ['col' => 'o.office_name'],
        ];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM Users u
                INNER JOIN Offices o ON o.OfficeID = u.OfficeID";
    }

    protected function getDefaultSort(): array
    {
        return ['username' => 'asc'];
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
<https://theodorejb.me>

## License

MIT
