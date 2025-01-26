<?php

namespace DevTheorem\Phaster;

/**
 * @api
 */
class QueryOptions
{
    /**
     * @param mixed[] $filter
     * @param mixed[] $originalFilter
     * @param mixed[] $sort
     * @param Prop[] $fieldProps
     */
    public function __construct(
        public readonly array $filter,
        public readonly array $originalFilter,
        public readonly array $sort,
        public readonly array $fieldProps,
    ) {}

    public function getColumns(): string
    {
        $columns = '';

        foreach ($this->fieldProps as $prop) {
            if ($prop->col === '') {
                continue;
            }

            $columns .= $prop->col;

            if ($prop->alias) {
                $columns .= ' AS ' . $prop->alias;
            }

            $columns .= ', ';
        }

        return substr($columns, 0, -2);
    }
}
