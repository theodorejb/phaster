<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test;

use theodorejb\Phaster\Entities;

class Users extends Entities
{
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
            'isDisabled' => ['type' => 'bool'],
        ];
    }

    protected function getDefaultValues(): array
    {
        return [
            'isDisabled' => false,
        ];
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
