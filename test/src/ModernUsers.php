<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test;

use theodorejb\Phaster\{Entities, Prop, QueryOptions};

/**
 * @psalm-import-type PropArray from Entities
 * https://github.com/vimeo/psalm/issues/8645
 */
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

    protected function getSelectProps(): array
    {
        return [
            new Prop('id', 'u.u_id'),
            new Prop('name', 'name', false, true, 'username'),
            new Prop('isDisabled', 'isDisabled', false, true, '', 'bool'),
            new Prop('thing.id', 'ut.thing_id', true),
            new Prop('thing.uid', 'ut.user_id', false, true, 'thing_user'),
        ];
    }

    protected function getDefaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()}
                FROM vUsers u
                LEFT JOIN UserThings ut ON ut.user_id = u.u_id";
    }
}
