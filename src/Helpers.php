<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

use Teapot\{HttpException, StatusCode};

/**
 * @internal
 * @psalm-import-type PropArray from Entities
 */
class Helpers
{
    /**
     * @return array<string, PropArray>
     */
    public static function selectMapToPropMap(array $map, string $context = ''): array
    {
        $propMap = [];

        if ($context !== '') {
            $context .= '.';
        }

        /**
         * @var string|array $val
         */
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
     * @param Prop[] $map
     */
    public static function propMapToSelectMap(array $map): array
    {
        $selectMap = [];

        foreach ($map as $prop) {
            $key = $prop->map[0];
            /** @psalm-suppress EmptyArrayAccess */
            $_ref = &$selectMap[$key];

            for ($i = 1; $i < $prop->depth; $i++) {
                $key = $prop->map[$i];
                /** @psalm-suppress MixedArrayAccess */
                $_ref = &$_ref[$key];
            }

            $_ref = $prop->col;
            unset($_ref); // dereference
        }

        return $selectMap;
    }

    /**
     * @param \Generator<int, array> $rows
     * @param Prop[] $fieldProps
     * @return list<array>
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

            /** @var mixed $value */
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
                    /** @var mixed $value */
                    $value = ($prop->getValue)($row);
                } elseif ($prop->type) {
                    settype($value, $prop->type);
                } elseif (is_string($value) && $prop->timeZone !== false) {
                    $value = (new \DateTimeImmutable($value, $prop->timeZone))->format(\DateTime::ATOM);
                }

                $key = $prop->map[0];
                /** @psalm-suppress EmptyArrayAccess */
                $_ref = &$entity[$key];

                for ($i = 1; $i < $prop->depth; $i++) {
                    $key = $prop->map[$i];
                    /** @psalm-suppress MixedArrayAccess */
                    $_ref = &$_ref[$key];
                }

                $_ref = $value;
                unset($_ref); // dereference
            }

            foreach ($nullParents as $prop) {
                $depth = $prop->depth - 1;
                $key = $prop->map[0];
                /** @psalm-suppress EmptyArrayAccess */
                $_ref = &$entity[$key];

                for ($i = 1; $i < $depth; $i++) {
                    $key = $prop->map[$i];
                    /** @psalm-suppress MixedArrayAccess */
                    $_ref = &$_ref[$key];
                }

                $_ref = null;
                unset($_ref); // dereference
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param string[] $fields
     * @param array<string, Prop> $propMap
     * @return array<string, Prop>
     */
    public static function getFieldPropMap(array $fields, array $propMap): array
    {
        /** @var array<string, Prop> $fieldProps */
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
                /** @var array<string, Prop> $matches */
                $matches = [];

                if (isset($propMap[$field])) {
                    $matches[$field] = $propMap[$field];
                } else {
                    // check for subfields
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

                    foreach ($fieldProps as $field => $_val) {
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
     * @param array<string, PropArray> $map
     * @return list<Prop>
     */
    public static function rawPropMapToProps(array $map): array
    {
        $props = [];

        foreach ($map as $name => $options) {
            $props[] = new Prop(
                $name,
                $options['col'] ?? '',
                $options['nullGroup'] ?? false,
                !($options['notDefault'] ?? false),
                $options['alias'] ?? '',
                $options['type'] ?? null,
                $options['timeZone'] ?? false,
                $options['getValue'] ?? null,
                $options['dependsOn'] ?? []
            );
        }

        return $props;
    }

    /**
     * @param list<Prop> $props
     * @return array<string, Prop>
     */
    public static function propListToPropMap(array $props): array
    {
        $propMap = [];
        $fullDependsList = [];

        foreach ($props as $prop) {
            if (isset($propMap[$prop->name])) {
                throw new \Exception("Duplicate property {$prop->name}");
            }

            foreach ($prop->dependsOn as $field) {
                if ($field === $prop->name) {
                    throw new \Exception("{$prop->name} property cannot depend on itself");
                }

                $fullDependsList[$field] = $prop->name;
            }

            $propMap[$prop->name] = $prop;
        }

        foreach ($fullDependsList as $field => $propName) {
            if (!isset($propMap[$field])) {
                throw new \Exception("Invalid dependsOn value '{$field}' on {$propName} property");
            }
        }

        return $propMap;
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
}
