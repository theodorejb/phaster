<?php

namespace theodorejb\Phaster;

/**
 * @internal
 */
class Helpers
{
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
}
