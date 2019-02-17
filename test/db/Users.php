<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class Users extends Entities
{
    protected function getIdColumn(): string
    {
        return 'user_id';
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

    protected function getDefaultValues(): array
    {
        return [
            'isDisabled' => false,
        ];
    }

    protected function rowsToJson(\Generator $rows): array
    {
        $users = [];

        foreach ($rows as $row) {
            $users[] = [
                'id' => $row['user_id'],
                'name' => $row['name'],
                'birthday' => $row['dob'],
                'weight' => $row['weight'],
                'isDisabled' => (bool)$row['isDisabled'],
            ];
        }

        return $users;
    }

    protected function getDuplicateError(): string
    {
        return 'A user with this name already exists';
    }

    protected function getConstraintError(): string
    {
        return 'Failed to delete user: it still has things referencing it';
    }
}
