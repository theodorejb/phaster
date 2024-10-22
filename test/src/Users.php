<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test\src;

use theodorejb\Phaster\Entities;
use theodorejb\Phaster\Prop;

class Users extends Entities
{
    protected function getMap(): array
    {
        return [
            'id' => 'user_id',
            'name' => 'name',
            'birthday' => 'dob',
            'weight' => 'weight',
            'isDisabled' => 'is_disabled',
        ];
    }

    protected function getSelectProps(): array
    {
        return [
            new Prop('isDisabled', 'is_disabled', type: 'bool'),
        ];
    }

    protected function getDefaultValues(): array
    {
        return [
            'isDisabled' => false,
        ];
    }
}
