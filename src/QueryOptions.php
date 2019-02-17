<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class QueryOptions
{
    private $filter;
    private $sort;
    /** @var Prop[] */
    private $fieldProps;

    public function __construct(array $query)
    {
        $this->filter = $query['filter'];
        $this->sort = $query['sort'];
        $this->fieldProps = $query['fieldProps'];
    }

    public function getFilter(): array
    {
        return $this->filter;
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
