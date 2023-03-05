<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use PeachySQL\PeachySql;
use PeachySQL\QueryBuilder\SqlParams;
use PeachySQL\SqlException;
use Teapot\{HttpException, StatusCode};

/**
 * @psalm-type PropArray = array{
 *     col?: string, nullGroup?: bool, notDefault?: bool, alias?: string, type?: string,
 *     timeZone?: \DateTimeZone|null, getValue?: callable|null, dependsOn?: list<string>
 * }
 */
abstract class Entities
{
    /**
     * Returns a map of properties to columns for the current table
     */
    abstract protected function getMap(): array;

    protected PeachySql $db;
    protected string $idField = 'id';
    private string $idColumn;
    /** @var array<string, Prop> */
    private array $fullPropMap;
    private array $map;

    public function __construct(PeachySql $db)
    {
        $this->db = $db;
        /** @psalm-suppress DeprecatedMethod */
        $legacyPropMap = $this->getPropMap();
        /** @var array<string, PropArray> $rawPropMap */
        $rawPropMap = array_replace_recursive(Helpers::selectMapToPropMap($this->getSelectMap()), $legacyPropMap);
        $bcProps = Helpers::rawPropMapToProps($rawPropMap);

        $propMap = array_replace(
            Helpers::propListToPropMap($bcProps),
            Helpers::propListToPropMap($this->getSelectProps()),
        );

        if (!isset($propMap[$this->idField])) {
            throw new \Exception('Missing required id property in map');
        }

        $map = $this->getMap();

        if (isset($map[$this->idField])) {
            /** @psalm-suppress MixedAssignment */
            $this->idColumn = $map[$this->idField];
            unset($map[$this->idField]); // prevent modifying identity column
        } else {
            $idParts = explode('.', $propMap[$this->idField]->col);
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

    /**
     * Returns the base select query with optional bound params.
     */
    protected function getBaseSelect(QueryOptions $options): SqlParams
    {
        return new SqlParams($this->getBaseQuery($options), []);
    }

    protected function getDefaultSort(): array
    {
        return [$this->idField => 'asc'];
    }

    /**
     * Specify a friendly error message for constraint violations (when inserting/updating rows)
     * @deprecated
     */
    protected function getDuplicateError(): string
    {
        return '';
    }

    /**
     * Specify a friendly error message for constraint violations (when attempting to delete rows)
     * @deprecated
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
     * @deprecated Use getSelectProps() instead.
     * @return array<string, PropArray>
     */
    protected function getPropMap(): array
    {
        return [];
    }

    /**
     * Merge additional property information with getSelectMap().
     * Look at the Prop class constructor to see supported options.
     * @return list<Prop>
     */
    protected function getSelectProps(): array
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
     * @param list<string|int> $ids
     */
    protected function processValues(array $data, array $ids): array
    {
        return $data;
    }

    /**
     * Make changes to a row before it is inserted or updated in the database.
     * @param array<string, mixed> $row
     * @param list<string|int> $ids
     * @return array<string, mixed>
     */
    protected function processRow(array $row, array $ids): array
    {
        return $row;
    }

    /**
     * @param list<string|int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        if (count($ids) === 0) {
            return 0;
        }

        try {
            return $this->db->deleteFrom($this->getTableName(), [$this->idColumn => $ids]);
        } catch (SqlException $e) {
            /** @psalm-suppress DeprecatedMethod */
            $constraintError = $this->getConstraintError();

            if ($constraintError !== '' && $e->getSqlState() === '23000') {
                throw new HttpException($constraintError, StatusCode::CONFLICT, $e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param int|string $id
     */
    public function updateById($id, array $data): int
    {
        $row = self::allPropertiesToColumns($this->map, $this->processValues($data, [$id]));
        $row = $this->processRow($row, [$id]);

        try {
            return $this->db->updateRows($this->getTableName(), $row, [$this->idColumn => $id]);
        } catch (SqlException $e) {
            throw $this->properException($e);
        }
    }

    /**
     * Update one or more rows via a JSON Merge Patch (https://tools.ietf.org/html/rfc7396)
     * @param list<string|int> $ids
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
     * @param list<array> $entities
     * @return list<int>
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
            $row = self::allPropertiesToColumns($this->map, $this->processValues($data, []));
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
        /** @psalm-suppress DeprecatedMethod */
        $duplicateError = $this->getDuplicateError();

        if ($duplicateError !== '' && $e->getSqlState() === '23000') {
            return new HttpException($duplicateError, StatusCode::CONFLICT, $e);
        } else {
            return $e;
        }
    }

    /**
     * @param int|string $id
     * @param string[] $fields
     */
    public function getEntityById($id, array $fields = []): array
    {
        $entities = $this->getEntitiesByIds([$id], $fields);

        if (count($entities) === 0) {
            throw new HttpException('Invalid ID', StatusCode::NOT_FOUND);
        }

        return $entities[0];
    }

    /**
     * @param string[] $fields
     * @return list<array>
     */
    public function getEntitiesByIds(array $ids, array $fields = [], array $sort = []): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return $this->getEntities([$this->idField => $ids], $fields, $sort);
    }

    /**
     * @param string[] $fields
     * @return list<array>
     */
    public function getEntities(array $filter = [], array $fields = [], array $sort = [], int $offset = 0, int $limit = 0): array
    {
        $processedFilter = $this->processFilter($filter);
        $selectMap = Helpers::propMapToSelectMap($this->fullPropMap);;

        if ($sort === []) {
            $sort = $this->getDefaultSort();
        }

        $fieldProps = Helpers::getFieldPropMap($fields, $this->fullPropMap);
        $queryOptions = new QueryOptions($processedFilter, $filter, $sort, $fieldProps);

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $select = $this->db->select($this->getBaseSelect($queryOptions))
            ->where(self::propertiesToColumns($selectMap, $processedFilter))
            ->orderBy(self::propertiesToColumns($selectMap, $sort));

        if ($limit !== 0) {
            $select->offset($offset, $limit);
        }

        return Helpers::mapRows($select->query()->getIterator(), $fieldProps);
    }

    /**
     * Converts nested properties to an array of columns and values using a map.
     * @return array<string, mixed>
     */
    public static function propertiesToColumns(array $map, array $properties, bool $allowExtraProperties = false): array
    {
        return self::propsToColumns($map, $properties, $allowExtraProperties, true);
    }

    /**
     * Converts nested properties to an array of columns and values using a map. All properties in the map are required.
     * @return array<string, mixed>
     * @internal
     */
    public static function allPropertiesToColumns(array $map, array $properties): array
    {
        self::propsToColumns($properties, $map, false, false);
        return self::propsToColumns($map, $properties, true, true);
    }

    /**
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
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

            /** @var array|mixed $newMap */
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

                /** @psalm-suppress MixedAssignment */
                $columns[$newMap] = $value;
            }
        }

        return $columns;
    }
}
