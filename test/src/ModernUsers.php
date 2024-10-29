<?php

namespace DevTheorem\Phaster\Test\src;

use DevTheorem\Phaster\{Entities, Prop, QueryOptions};

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
            'isDisabled' => 'is_disabled',
        ];
    }

    protected function getSelectProps(): array
    {
        $getValue = function (array $row): float {
            /** @var array{weight: int} $row */
            return $row['weight'] + 1;
        };

        return [
            new Prop('id', 'u.user_id'),
            new Prop('name', 'name', false, true, 'username'),
            new Prop('isDisabled', 'is_disabled', false, true, '', 'bool'),
            new Prop('computed', '', false, true, '', null, false, $getValue, ['weight']),
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
                FROM Users u
                LEFT JOIN UserThings ut ON ut.user_id = u.user_id";
    }

    protected function processValues(array $data, array $ids): array
    {
        if (count($ids) === 0) {
            if ($data['name'] === 'Modern user 3') {
                $data['name'] = 'Modern user 3 modified';
            } elseif ($data['name'] === 'Modern user 2') {
                $data['id'] = -42; // don't insert row for this item
            }
        }

        return $data;
    }
}
