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

    public function __construct(PeachySql $db)
    {
        $this->db = $db;
        $rawPropMap = array_replace_recursive(self::selectMapToPropMap($this->getSelectMap()), $this->getPropMap());
        $propMap = self::rawPropMapToPropMap($rawPropMap);
        $map = $this->getMap();

        if (!isset($propMap[$this->idField])) {
            throw new \Exception('Missing required id property in map');
        }

        $this->selectId = $propMap[$this->idField]->col;

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
     * Returns the base select query which can be subsequently filtered, sorted, and paged
     */
    protected function getBaseQuery(QueryOptions $options): string
    {
        return "SELECT {$options->getColumns()} FROM " . $this->getTableName();
    }

    protected function getDefaultSort(): array
    {
        return [$this->idField => 'asc'];
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

    /**
     * Make changes to a row before it is inserted or updated in the database.
     */
    protected function processRow(array $row, array $ids): array
    {
        return $row;
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
        $row = $this->processRow($row, [$id]);

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
        $colVals = $this->processRow($colVals, $ids);

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

        $defaultValues = $this->getDefaultValues();
        $rows = [];

        foreach ($entities as $entity) {
            $data = array_replace_recursive($defaultValues, $entity);
            $row = self::propertiesToColumns($this->map, $this->processValues($data, []), true);
            $rows[] = $this->processRow($row, []);
        }

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

    public function getEntityById($id, array $fields = []): array
    {
        $entities = $this->getEntitiesByIds([$id], $fields);

        if (count($entities) === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $entities[0];
    }

    public function getEntitiesByIds(array $ids, array $fields = [], array $sort = []): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->getEntities([$this->idField => $ids], $fields, $sort);
    }

    public function getEntities(array $filter = [], array $fields = [], array $sort = [], int $offset = 0, int $limit = 0): array
    {
        $filter = $this->processFilter($filter);
        $selectMap = self::propMapToSelectMap($this->fullPropMap);;

        if ($sort === []) {
            $sort = $this->getDefaultSort();
        }

        $queryOptions = new QueryOptions([
            'filter' => $filter,
            'sort' => $sort,
            'fieldProps' => self::getFieldPropMap($fields, $this->fullPropMap),
        ]);

        $select = $this->db->selectFrom($this->getBaseQuery($queryOptions))
            ->where(self::propertiesToColumns($selectMap, $filter))
            ->orderBy(self::propertiesToColumns($selectMap, $sort));

        if ($limit !== 0) {
            $select->offset($offset, $limit);
        }

        return self::mapRows($select->query()->getIterator(), $queryOptions->getFieldProps());
    }

    /**
     * @param Prop[] $aliasMap
     */
    public static function mapRows(\Generator $rows, array $fieldProps): array
    {
        if (!$rows->valid()) {
            return []; // no rows selected
        }

        $aliasMap = self::propMapToAliasMap($fieldProps);
        $entities = [];

        foreach ($rows as $row) {
            $entity = [];
            /** @var Prop[] $nullParents */
            $nullParents = [];

            foreach ($row as $colName => $value) {
                $prop = $aliasMap[$colName];

                if ($prop->nullGroup && $value === null) {
                    // only add if there isn't a higher-level null parent
                    $parent = '';

                    foreach ($prop->parents as $parent) {
                        if (isset($nullParents[$parent])) {
                            continue 2;
                        }
                    }

                    $nullParents[$parent] = $prop;
                    continue;
                } elseif ($prop->noOutput) {
                    continue;
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
        $dependedOn = [];

        if ($fields === []) {
            // select all default fields
            foreach ($propMap as $prop => $data) {
                if ($data->isDefault) {
                    $fieldProps[$prop] = $data;

                    foreach ($data->dependsOn as $value) {
                        $dependedOn[$value] = true;
                    }
                }
            }
        } else {
            foreach ($fields as $field) {
                /** @var Prop[] $matches */
                $matches = [];

                if (isset($propMap[$field])) {
                    $matches[$field] = $propMap[$field];
                } else {
                    // check for sub-fields
                    $parent = $field . '.';
                    $length = strlen($parent);

                    foreach ($propMap as $prop => $data) {
                        if (substr($prop, 0, $length) === $parent) {
                            $matches[$prop] = $data;
                        }
                    }

                    if (count($matches) === 0) {
                        throw new HttpException("'{$field}' is not a valid field", StatusCode::BAD_REQUEST);
                    }
                }

                foreach ($matches as $prop => $data) {
                    foreach ($data->dependsOn as $value) {
                        $dependedOn[$value] = true;
                    }

                    $fieldProps[$prop] = $data;
                }
            }

            foreach ($propMap as $prop => $data) {
                if (isset($fieldProps[$prop])) {
                    continue; // already selected
                }

                if ($data->nullGroup) {
                    // check if any selected field is a child
                    $parents = $data->parents;
                    $parent = array_pop($parents);
                    $length = strlen($parent);

                    foreach ($fieldProps as $field => $val) {
                        if (substr($field, 0, $length) === $parent) {
                            $dependedOn[$prop] = true;
                            break;
                        }
                    }
                }
            }
        }

        foreach ($propMap as $prop => $data) {
            if (!isset($fieldProps[$prop]) && isset($dependedOn[$prop])) {
                $data = clone $data;
                $data->noOutput = true;
                $fieldProps[$prop] = $data;
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
            if (isset($options['dependsOn'])) {
                if (!is_array($options['dependsOn'])) {
                    throw new \Exception("dependsOn key on {$prop} property must be an array");
                }

                if (count($options['dependsOn']) !== 0 && !isset($options['getValue'])) {
                    throw new \Exception("dependsOn key on {$prop} property cannot be used without getValue function");
                }

                foreach ($options['dependsOn'] as $field) {
                    if ($field === $prop || !isset($map[$field])) {
                        throw new \Exception("Invalid dependsOn value '{$field}' on {$prop} property");
                    }
                }
            }

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
