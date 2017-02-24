<?php

namespace Phaster;

use PeachySQL\PeachySql;
use PeachySQL\SqlException;
use Teapot\{HttpException, StatusCode};

abstract class Entities
{
    /**
     * Returns the table's identity column
     */
    abstract protected function getIdColumn(): string;

    /**
     * Returns a map of properties to columns for the current table
     */
    abstract protected function getMap(): array;

    /**
     * Converts an array of selected rows to a JSON serializable array
     */
    abstract protected function rowsToJson(\Generator $rows): array;

    protected $db;

    public function __construct(PeachySql $db)
    {
        $this->db = $db;
    }

    /**
     * Returns the name of the table or view to select/insert/update/delete from
     */
    protected function getTableName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Returns the identity increment value of the table
     */
    protected function getIdentityIncrement(): int
    {
        return 1;
    }

    /**
     * Returns the base select query which can be subsequently filtered, sorted, and paged
     */
    protected function getBaseSelect(): string
    {
        return 'SELECT * FROM ' . $this->getTableName();
    }

    protected function getDefaultSort(): array
    {
        return [];
    }

    protected function getDuplicateError(): string
    {
        return '';
    }

    /**
     * Can be used to return a separate property map for filtering/sorting (but not updating)
     */
    protected function getSelectMap(): array
    {
        return $this->getMap();
    }

    /**
     * Allows default values to be specified for mapped properties.
     * These defaults are only used when adding entities.
     */
    protected function getDefaultValues(): array
    {
        return [];
    }

    /**
     * Can modify the filter or throw an exception if it is invalid
     */
    protected function processFilter(array $filter): array
    {
        return $filter;
    }

    /**
     * Perform any validations/alterations to a set of properties/values to insert/update.
     * When adding entities, default values are merged prior to calling this method.
     */
    protected function processValues(array $data, array $ids): array
    {
        return $data;
    }

    public function deleteByIds(array $ids): int
    {
        return $this->db->deleteFrom($this->getTableName(), [$this->getIdColumn() => $ids]);
    }

    public function updateById($id, array $data): int
    {
        $row = self::propertiesToColumns($this->getMap(), $this->processValues($data, [$id]), true);

        try {
            return $this->db->updateRows($this->getTableName(), $row, [$this->getIdColumn() => $id]);
        } catch (SqlException $e) {
            throw $this->properException($e);
        }
    }

    /**
     * Update one or more rows via a JSON Merge Patch (https://tools.ietf.org/html/rfc7396)
     */
    public function patchByIds(array $ids, array $mergePatch): int
    {
        $colVals = self::propertiesToColumns($this->getMap(), $this->processValues($mergePatch, $ids));

        try {
            return $this->db->updateRows($this->getTableName(), $colVals, [$this->getIdColumn() => $ids]);
        } catch (SqlException $e) {
            throw $this->properException($e);
        }
    }

    /**
     * Returns an array containing the IDs of the inserted rows
     */
    public function addEntities(array $entities): array
    {
        if (count($entities) === 0) {
            return [];
        }

        $rows = array_map(function ($data) {
            $data = array_replace_recursive($this->getDefaultValues(), $data);
            return self::propertiesToColumns($this->getMap(), $this->processValues($data, []), true);
        }, $entities);

        try {
            return $this->db->insertRows($this->getTableName(), $rows, $this->getIdentityIncrement())->getIds();
        } catch (SqlException $e) {
            throw $this->properException($e);
        }
    }

    private function properException(SqlException $e): \Exception
    {
        $duplicateError = $this->getDuplicateError();

        if ($duplicateError !== '' && $e->getSqlState() === '23000') {
            return new HttpException($duplicateError, StatusCode::CONFLICT, $e);
        } else {
            return $e;
        }
    }

    public function getEntityById($id)
    {
        $result = $this->db->selectFrom($this->getBaseSelect())
            ->where([$this->getIdColumn() => $id])->query();

        $entities = $this->rowsToJson($result->getIterator());

        if (count($entities) === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $entities[0];
    }

    public function getEntitiesByIds(array $ids): array
    {
        $result = $this->db->selectFrom($this->getBaseSelect())
            ->where([$this->getIdColumn() => $ids])
            ->query();

        return $this->rowsToJson($result->getIterator());
    }

    public function getEntities(array $filter = [], array $sort = [], int $offset = null, int $limit = 0): array
    {
        $filter = $this->processFilter($filter);
        $selectMap = $this->getSelectMap();

        if ($sort === []) {
            $sort = $this->getDefaultSort();
        }

        $select = $this->db->selectFrom($this->getBaseSelect())
            ->where(self::propertiesToColumns($selectMap, $filter))
            ->orderBy(self::propertiesToColumns($selectMap, $sort));

        if ($offset !== null) {
            $select->offset($offset, $limit);
        }

        return $this->rowsToJson($select->query()->getIterator());
    }

    /**
     * Uses a map array to convert nested properties to an array of columns and values
     */
    public static function propertiesToColumns(array $map, array $properties, bool $requireFullMap = false): array
    {
        if ($requireFullMap) {
            // ensure that all the mapped properties exist
            self::propsToColumns($properties, $map, false, false);
        }

        return self::propsToColumns($map, $properties, $requireFullMap, true);
    }

    private static function propsToColumns(array $map, array $properties, bool $allowExtraProperties, bool $buildColumns, string $context = '', array &$columns = []): array
    {
        if ($context !== '') {
            $context .= '.';
        }

        foreach ($properties as $property => $value) {
            $contextProp = $context . $property;

            if (!array_key_exists($property, $map)) {
                if ($allowExtraProperties) {
                    continue;
                }

                $errMsg = $buildColumns ? 'Invalid' : 'Missing required';
                throw new HttpException("{$errMsg} {$contextProp} property", StatusCode::BAD_REQUEST);
            }

            $newMap = $map[$property]; // might be value

            if (is_array($newMap)) {
                if (!is_array($value)) {
                    if ($buildColumns) {
                        throw new HttpException("Expected {$contextProp} property to be an object, got " . gettype($value), StatusCode::BAD_REQUEST);
                    } else {
                        continue;
                    }
                }

                self::propsToColumns($newMap, $value, $allowExtraProperties, $buildColumns, $contextProp, $columns);
            } elseif ($buildColumns) {
                if (!is_string($newMap)) {
                    throw new \Exception('Map values must be arrays or strings, found ' . gettype($newMap) . " for {$contextProp} property");
                }

                if (array_key_exists($newMap, $columns)) {
                    throw new \Exception("Column '{$newMap}' is mapped to more than one property ({$contextProp})");
                }

                $columns[$newMap] = $value;
            }
        }

        return $columns;
    }
}
