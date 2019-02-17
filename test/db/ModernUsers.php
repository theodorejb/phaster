<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class ModernUsers extends Entities
{
    protected function getTableName(): string
    {
        return 'Users';
    }

    protected function getMap(): array
    {
        return [
            'id' => 'user_id',
            'name' => 'name',
            'birthday' => 'dob',
            'weight' => 'weight',
            'isDisabled' => 'isDisabled',
        ];
    }

    protected function getPropMap(): array
    {
        return [
            'id' => ['col' => 'u.u_id'],
            'name' => ['alias' => 'username'],
            'isDisabled' => ['type' => 'bool'],
            'thing.id' => ['col' => 'ut.thing_id', 'nullGroup' => true],
            'thing.uid' => ['col' => 'ut.user_id', 'alias' => 'thing_user'],
        ];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM vUsers u
                LEFT JOIN UserThings ut ON ut.user_id = u.u_id";
    }
}
