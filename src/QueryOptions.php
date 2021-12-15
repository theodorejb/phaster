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
     * @param Prop[] $fieldProps
     */
    public function __construct(array $filter, array $originalFilter, array $sort, array $fieldProps)
    {
        $this->filter = $filter;
        $this->originalFilter = $originalFilter;
        $this->sort = $sort;
        $this->fieldProps = $fieldProps;
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
