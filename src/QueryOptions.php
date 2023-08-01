<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

/**
 * @api
 */
class QueryOptions
{
    /** @readonly */
    public array $filter;
    /** @readonly */
    public array $originalFilter;
    /** @readonly */
    public array $sort;

    /**
     * @var Prop[]
     * @readonly
     */
    public array $fieldProps;

    /**
     * @param Prop[] $fieldProps
     */
    public function __construct(array $filter, array $originalFilter, array $sort, array $fieldProps)
    {
        $this->filter = $filter;
        $this->originalFilter = $originalFilter;
        $this->sort = $sort;
        $this->fieldProps = $fieldProps;
    }

    /**
     * @deprecated Use readonly property instead
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * @deprecated Use readonly property instead
     */
    public function getOriginalFilter(): array
    {
        return $this->originalFilter;
    }

    /**
     * @deprecated Use readonly property instead
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @deprecated Use readonly property instead
     * @return Prop[]
     */
    public function getFieldProps(): array
    {
        return $this->fieldProps;
    }

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
