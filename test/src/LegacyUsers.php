<?php

declare(strict_types=1);

namespace theodorejb\Phaster\Test\src;

use theodorejb\Phaster\{Entities, QueryOptions};
use PeachySQL\QueryBuilder\SqlParams;

/**
 * @psalm-import-type PropArray from Entities
 * https://github.com/vimeo/psalm/issues/8645
 */
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

    protected function processFilter(array $filter): array
    {
        unset($filter['customFilter']);
        return $filter;
    }

    protected function getBaseSelect(QueryOptions $options): SqlParams
    {
        $originalFilter = $options->originalFilter;
        $customFilter = '';
        $params = [];

        if (isset($originalFilter['customFilter'])) {
            $customFilter = 'WHERE u1.user_id <> ?';
            /** @psalm-suppress MixedAssignment */
            $params[] = $originalFilter['customFilter'];
        }

        $sql = "
            SELECT {$options->getColumns()}
            FROM (
                SELECT * FROM Users u1 $customFilter
            ) u
            LEFT JOIN UserThings ut ON ut.user_id = u.user_id";

        return new SqlParams($sql, $params);
    }

    protected function processRow(array $row, array $ids): array
    {
        if (count($ids) === 0) {
            $row['dob'] = '2001-03-20';
        }

        return $row;
    }
}
