<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use PeachySQL\PeachySql;
use PeachySQL\SqlException;
use Teapot\{HttpException, StatusCode};

abstract class Entities
{
    /**
     * Returns a map of properties to columns for the current table
     */
    abstract protected function getMap(): array;

    protected $db;
    protected $idField = 'id';
    private $selectId;
    private $idColumn;
    private $fullPropMap;
    private $map;
    private $isModern;

    public function __construct(PeachySql $db)
    {
        $this->db = $db;
        $this->isModern = ($this->rowsToJson(self::emptyGenerator()) === ['unused' => true]);
        $rawPropMap = array_replace_recursive(self::selectMapToPropMap($this->getSelectMap()), $this->getPropMap());
        $propMap = self::rawPropMapToPropMap($rawPropMap);
        $map = $this->getMap();

        if (isset($propMap[$this->idField])) {
            $this->selectId = $propMap[$this->idField]->col;
        } elseif ($this->isModern) {
            throw new \Exception('Missing required id property in map');
        } else {
            // backwards compatibility
            $this->selectId = $this->getSelectId();
            $propMap[$this->idField] = new Prop($this->idField, ['col' => $this->selectId]);
        }

        if (isset($map[$this->idField])) {
            $this->idColumn = $map[$this->idField];
            unset($map[$this->idField]); // prevent modifying identity column
        } else {
            $idParts = explode('.', $this->selectId);
            $this->idColumn = array_pop($idParts);
        }

        $this->fullPropMap = $propMap;
        $this->map = $map;
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
     * @deprecated Use getBaseQuery() instead
     */
    protected function getBaseSelect(): string
    {
        return 'SELECT * FROM ' . $this->getTableName();
    }

    /**
     * Returns the base select query which can be subsequently filtered, sorted, and paged
     */
    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()} FROM " . $this->getTableName();
    }

    protected function getDefaultSort(): array
    {
        return [];
    }

    /**
     * Specify a friendly error message for constraint violations (when inserting/updating rows)
     */
    protected function getDuplicateError(): string
    {
        return '';
    }

    /**
     * Specify a friendly error message for constraint violations (when attempting to delete rows)
     */
    protected function getConstraintError(): string
    {
        return '';
    }

    /**
     * @deprecated Set id property in getMap() instead
     */
    protected function getIdColumn(): string
    {
        return $this->idColumn;
    }

    /**
     * @deprecated Set id property in getSelectMap() or getPropMap() instead
     */
    protected function getSelectId(): string
    {
        return $this->getIdColumn();
    }

    /**
     * Can be used to return a separate property map for filtering/sorting (but not inserting/updating)
     */
    protected function getSelectMap(): array
    {
        return $this->getMap();
    }

    /**
     * Merge additional property information with getSelectMap().
     * Look at the Prop class constructor to see supported options.
     */
    protected function getPropMap(): array
    {
        return [];
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
        if (count($ids) === 0) {
            return 0;
        }

        try {
            return $this->db->deleteFrom($this->getTableName(), [$this->idColumn => $ids]);
        } catch (SqlException $e) {
            $constraintError = $this->getConstraintError();

            if ($constraintError !== '' && $e->getSqlState() === '23000') {
                throw new HttpException($constraintError, StatusCode::CONFLICT, $e);
            } else {
                throw $e;
            }
        }
    }

    public function updateById($id, array $data): int
    {
        $row = self::propertiesToColumns($this->map, $this->processValues($data, [$id]), true);

        try {
            return $this->db->updateRows($this->getTableName(), $row, [$this->idColumn => $id]);
        } catch (SqlException $e) {
            throw $this->properException($e);
        }
    }

    /**
     * Update one or more rows via a JSON Merge Patch (https://tools.ietf.org/html/rfc7396)
     */
    public function patchByIds(array $ids, array $mergePatch): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        $colVals = self::propertiesToColumns($this->map, $this->processValues($mergePatch, $ids));

        try {
            return $this->db->updateRows($this->getTableName(), $colVals, [$this->idColumn => $ids]);
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
            return self::propertiesToColumns($this->map, $this->processValues($data, []), true);
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

    public function getEntityById($id, array $fields = [])
    {
        $entities = $this->getEntitiesByIds([$id], $fields);

        if (count($entities) === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $entities[0];
    }

    public function getEntitiesByIds(array $ids, array $fields = []): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->getEntities([$this->idField => $ids], [], 0, 0, $fields);
    }

    public function getEntities(array $filter = [], array $sort = [], int $offset = 0, int $limit = 0, array $fields = []): array
    {
        $filter = $this->processFilter($filter);
        $selectMap = self::propMapToSelectMap($this->fullPropMap);;

        if ($sort === []) {
            $sort = $this->getDefaultSort();
        }

        $baseQuery = $this->getBaseSelect();

        if ($this->isModern) {
            if ($baseQuery !== 'SELECT * FROM ' . $this->getTableName()) {
                throw new \Exception('getBaseSelect should not be implemented - use getBaseQuery instead');
            }

            $queryOptions = new QueryOptions([
                'filter' => $filter,
                'sort' => $sort,
                'fieldProps' => self::getFieldPropMap($fields, $this->fullPropMap),
            ]);

            $baseQuery = $this->getBaseQuery($queryOptions);
        } elseif ($fields !== []) {
            throw new HttpException('fields parameter is not supported for this endpoint', StatusCode::BAD_REQUEST);
        }

        $select = $this->db->selectFrom($baseQuery)
            ->where(self::propertiesToColumns($selectMap, $filter))
            ->orderBy(self::propertiesToColumns($selectMap, $sort));

        if ($limit !== 0) {
            $select->offset($offset, $limit);
        }

        if ($this->isModern) {
            $aliasMap = self::propMapToAliasMap($queryOptions->getFieldProps());
            return self::mapRows($select->query()->getIterator(), $aliasMap);
        }

        return $this->rowsToJson($select->query()->getIterator());
    }

    /**
     * Converts an array of selected rows to a JSON serializable array
     * @deprecated Specify field info using getPropMap() instead
     */
    protected function rowsToJson(\Generator $rows): array
    {
        return ['unused' => true];
    }

    private static function emptyGenerator(): \Generator
    {
        yield from [];
    }

    /**
     * @param Prop[] $aliasMap
     */
    public static function mapRows(\Generator $rows, array $aliasMap): array
    {
        if (!$rows->valid()) {
            return []; // no rows selected
        }

        $entities = [];

        foreach ($rows as $row) {
            $entity = [];
            /** @var Prop[] $nullParents */
            $nullParents = [];

            foreach ($row as $colName => $value) {
                $prop = $aliasMap[$colName];

                if ($prop->nullGroup) {
                    if ($value === null) {
                        $nullParents[] = $prop;
                        continue;
                    } elseif ($prop->noOutput) {
                        continue;
                    }
                }

                if ($prop->getValue) {
                    $value = ($prop->getValue)($row);
                } elseif ($prop->type) {
                    settype($value, $prop->type);
                } elseif ($value !== null && $prop->timeZone !== false) {
                    $value = (new \DateTimeImmutable($value, $prop->timeZone))->format(\DateTime::ATOM);
                }

                $ref = &$entity[$prop->map[0]];

                for ($i = 1; $i < $prop->depth; $i++) {
                    $ref = &$ref[$prop->map[$i]];
                }

                $ref = $value;
                unset($ref); // dereference
            }

            foreach ($nullParents as $prop) {
                $depth = $prop->depth - 1;
                $ref = &$entity[$prop->map[0]];

                for ($i = 1; $i < $depth; $i++) {
                    $ref = &$ref[$prop->map[$i]];
                }

                $ref = null;
                unset($ref); // dereference
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param string[] $fields
     * @param Prop[] $propMap
     * @return Prop[]
     */
    public static function getFieldPropMap(array $fields, array $propMap): array
    {
        /** @var Prop[] $fieldProps */
        $fieldProps = [];

        if ($fields === []) {
            // select all default fields
            foreach ($propMap as $prop => $data) {
                if ($data->isDefault) {
                    $fieldProps[$prop] = $data;
                }
            }
        } else {
            foreach ($fields as $field) {
                if (isset($propMap[$field])) {
                    $fieldProps[$field] = $propMap[$field];
                } else {
                    // check for sub-fields
                    $parent = $field . '.';
                    $length = strlen($parent);
                    $foundChild = false;

                    foreach ($propMap as $prop => $data) {
                        if (substr($prop, 0, $length) === $parent) {
                            $fieldProps[$prop] = $data;
                            $foundChild = true;
                        }
                    }

                    if (!$foundChild) {
                        throw new HttpException("'{$field}' is not a valid field", StatusCode::BAD_REQUEST);
                    }
                }
            }

            $selectedParents = [];

            foreach ($fieldProps as $data) {
                if ($data->depth > 1) {
                    $selectedParents[$data->getParent()] = true;
                }
            }

            foreach ($propMap as $prop => $data) {
                if (!$data->nullGroup || isset($fieldProps[$prop])) {
                    continue; // already selected or not a nullable group identifier
                }

                if (isset($selectedParents[$data->getParent()])) {
                    $data = clone $data;
                    $data->noOutput = true;
                    $fieldProps[$prop] = $data;
                }
            }
        }

        return $fieldProps;
    }

    /**
     * @return Prop[]
     */
    public static function rawPropMapToPropMap(array $map): array
    {
        $propMap = [];

        foreach ($map as $prop => $options) {
            $propMap[$prop] = new Prop($prop, $options);
        }

        return $propMap;
    }

    /**
     * @param Prop[] $map
     */
    public static function propMapToSelectMap(array $map): array
    {
        $selectMap = [];

        foreach ($map as $prop) {
            $ref = &$selectMap[$prop->map[0]];

            for ($i = 1; $i < $prop->depth; $i++) {
                $ref = &$ref[$prop->map[$i]];
            }

            $ref = $prop->col;
            unset($ref); // dereference
        }

        return $selectMap;
    }

    /**
     * @param Prop[] $map
     * @return Prop[]
     */
    public static function propMapToAliasMap(array $map): array
    {
        $aliasMap = [];

        foreach ($map as $prop) {
            $aliasMap[$prop->getOutputCol()] = $prop;
        }

        return $aliasMap;
    }

    public static function selectMapToPropMap(array $map, string $context = ''): array
    {
        $propMap = [];

        if ($context !== '') {
            $context .= '.';
        }

        foreach ($map as $key => $val) {
            $newKey = $context . $key;

            if (is_array($val)) {
                $propMap = array_merge($propMap, self::selectMapToPropMap($val, $newKey));
            } else {
                $propMap[$newKey] = ['col' => $val];
            }
        }

        return $propMap;
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
