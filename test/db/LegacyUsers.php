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
            'thing' => ['id' => 'ut.thing_id'],
        ];
    }

    protected function getBaseSelect(): string
    {
        return "SELECT u.user_id, u.name AS username, ut.thing_id
                FROM Users u
                LEFT JOIN UserThings ut ON ut.user_id = u.user_id";
    }

    protected function rowsToJson(\Generator $rows): array
    {
        $users = [];

        foreach ($rows as $row) {
            $users[] = [
                'id' => $row['user_id'],
                'name' => $row['username'],
                'thing' => ['id' => $row['thing_id']],
            ];
        }

        return $users;
    }
}
