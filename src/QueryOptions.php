<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class QueryOptions
{
    private array $filter;
    private array $originalFilter;
    private array $sort;
    /** @var Prop[] */
    private array $fieldProps;

    /**
     * @param array{filter: array, originalFilter: array, sort: array, fieldProps: Prop[]} $query
     */
    public function __construct(array $query)
    {
        $this->filter = $query['filter'];
        $this->originalFilter = $query['originalFilter'];
        $this->sort = $query['sort'];
        $this->fieldProps = $query['fieldProps'];
    }

    public function getFilter(): array
    {
        return $this->filter;
    }

    public function getOriginalFilter(): array
    {
        return $this->originalFilter;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @return Prop[]
     */
    public function getFieldProps(): array
    {
        return $this->fieldProps;
    }

    public function getColumns(): string
    {
        $columns = [];

        foreach ($this->fieldProps as $prop) {
            $col = $prop->col;

            if ($prop->alias) {
                $col .= ' AS ' . $prop->alias;
            }

            $columns[] = $col;
        }

        return implode(', ', $columns);
    }
}
