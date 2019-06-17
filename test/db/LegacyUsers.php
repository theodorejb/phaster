<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class LegacyUsers extends Entities
{
    protected function getTableName(): string
    {
        return 'Users';
    }

    protected function getMap(): array
    {
        return [
            'name' => 'name',
            'birthday' => 'dob',
            'weight' => 'weight',
            'isDisabled' => 'isDisabled',
        ];
    }

    protected function getSelectMap(): array
    {
        return [
            'id' => 'u.user_id',
            'name' => 'u.name',
            'birthday' => 'u.dob',
            'thing' => ['id' => 'ut.thing_id'],
        ];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM Users u
                LEFT JOIN UserThings ut ON ut.user_id = u.user_id";
    }

    protected function processRow(array $row, array $ids): array
    {
        if (count($ids) === 0) {
            $row['dob'] = '2001-03-20';
        }

        return $row;
    }
}
