# Phaster

Phaster is a library for easily creating RESTful API endpoints.
It works well with the Slim Framework, and supports PHP 7.4+.

## Installation

`composer require theodorejb/phaster`

## Usage

Create a class extending `theodorejb\Phaster\Entities` and implement the
`getMap()` method. By default, the table name will be inferred from the
class name, and all mapped columns in this table will be selected.

To join other tables and alter output values, implement the `getBaseQuery()`
and `getPropMap()` methods. Pass the callable returned by the route handling
functions to your Slim or other PSR-7 compatible framework.

```php
<?php

use theodorejb\Phaster\{Entities, Prop, QueryOptions};

class Users extends Entities
{
    protected function getMap(): array
    {
        // map properties to columns in Users table
        return [
            'username' => 'uname',
            'firstName' => 'fname',
            'lastName' => 'lname',
            'isDisabled' => 'disabled',
            'role' => [
                'id' => 'role_id',
            ],
        ];
    }

    protected function getSelectProps(): array
    {
        // map additional properties for selecting/filtering and set output options
        return [
            new Prop('id', col: 'u.user_id'),
            new Prop('isDisabled', col: 'u.disabled', type: 'bool'),
            new Prop('role.id', col: 'u.role_id'),
            new Prop('role.name', col: 'r.role_name'),
        ];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM Users u
                INNER JOIN Roles r ON r.role_id = u.role_id";
    }

    protected function getDefaultSort(): array
    {
        return ['username' => 'asc'];
    }
}
```

If it is necessary to bind parameters in the base query, use `getBaseSelect` instead:

```php
use PeachySQL\QueryBuilder\SqlParams;
// ...
protected function getBaseSelect(QueryOptions $options): SqlParams
{
    $sql = "WITH num_orders AS
            (
                SELECT user_id, COUNT(*) as orders
                FROM Orders
                WHERE category_id = ?
                GROUP BY user_id
            )
            SELECT {$options->getColumns()}
            FROM Users u
            INNER JOIN num_orders n ON n.user_id = u.user_id";

    return new SqlParams($sql, [321]);
}
// ...
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

### Example API request/response

GET https://example.com/api/users?q[firstName]=Ted&q[isDisabled]=0&fields=id,username,role

```json
{
    "offset": 0,
    "limit": 25,
    "lastPage": true,
    "data": [
        {
            "id": 5,
            "username": "Teddy01",
            "role": {
                "id": 1,
                "name": "Admin"
            }
        },
        {
            "id": 38,
            "username": "only_tj",
            "role": {
                "id": 2,
                "name": "Standard"
            }
        }
    ]
}
```

## Author

Theodore Brown  
<https://theodorejb.me>

## License

MIT
