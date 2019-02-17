<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

class Prop
{
    /** @var string */
    public $col;

    /** @var string */
    public $alias;

    /** @var callable | null */
    public $getValue;

    /** @var string | null */
    public $type;

    /** @var \DateTimeZone | null | false */
    public $timeZone = false;

    public $nullGroup = false;
    public $isDefault = true;
    public $noOutput = false;

    /** @var string[] */
    public $map;

    /** @var int */
    public $depth;

    public function __construct(string $prop, array $options)
    {
        $this->map = explode('.', $prop);
        $this->depth = count($this->map);

        if (!isset($options['col'])) {
            throw new \Exception("{$prop} property must have 'col' key");
        } elseif (gettype($options['col']) !== 'string') {
            throw new \Exception("col key on {$prop} property must be a string");
        }

        $this->col = $options['col'];

        if (isset($options['alias'])) {
            if (gettype($options['alias']) !== 'string') {
                throw new \Exception("alias key on {$prop} property must be a string");
            }

            $this->alias = $options['alias'];
        }

        if (isset($options['getValue'])) {
            if (!is_callable($options['getValue'])) {
                throw new \Exception("getValue key on {$prop} property must be callable");
            }

            if (isset($options['type'])) {
                throw new \Exception("type key on {$prop} property cannot be set along with getValue");
            } elseif (isset($options['timeZone'])) {
                throw new \Exception("timeZone key on {$prop} property cannot be set along with getValue");
            }

            $this->getValue = $options['getValue'];
        }

        if (isset($options['type'])) {
            $allowedTypes = ['bool', 'int', 'float', 'string'];

            if (!in_array($options['type'], $allowedTypes, true)) {
                throw new \Exception("type key on {$prop} property must be bool, int, float, or string");
            }

            if (isset($options['timeZone'])) {
                throw new \Exception("timeZone key on {$prop} property cannot be set along with type");
            }

            $this->type = $options['type'];
        }

        if (array_key_exists('timeZone', $options)) {
            if ($options['timeZone'] === null || $options['timeZone'] instanceof \DateTimeZone) {
                $this->timeZone = $options['timeZone'];
            } else {
                throw new \Exception("timeZone key on {$prop} property must be a DateTimeZone instance or null");
            }
        }

        if (isset($options['nullGroup'])) {
            if (gettype($options['nullGroup']) !== 'boolean') {
                throw new \Exception("nullGroup key on {$prop} property must be a boolean");
            } elseif ($this->depth < 2) {
                throw new \Exception("nullGroup cannot be set on top-level {$prop} property");
            }

            $this->nullGroup = $options['nullGroup'];
        }

        if (isset($options['notDefault'])) {
            if (gettype($options['notDefault']) !== 'boolean') {
                throw new \Exception("notDefault key on {$prop} property must be a boolean");
            }

            $this->isDefault = !$options['notDefault'];
        }

        unset(
            $options['col'],
            $options['alias'],
            $options['getValue'],
            $options['type'],
            $options['timeZone'],
            $options['nullGroup'],
            $options['notDefault']
        );

        foreach ($options as $key => $value) {
            // any remaining options are invalid
            throw new \Exception("Invalid key '{$key}' on {$prop} property");
        }
    }

    public function getParent(): string
    {
        return implode('.', array_slice($this->map, 0, -1));
    }

    public function getOutputCol(): string
    {
        if ($this->alias) {
            return $this->alias;
        }

        $col = explode('.', $this->col);
        return array_pop($col);
    }
}
