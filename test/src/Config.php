<?php

namespace theodorejb\Phaster\Test\src;

/**
 * Default test config. Values can be overridden with a LocalConfig child class.
 */
class Config
{
    public function getMysqlHost(): string
    {
        return '127.0.0.1';
    }

    public function getMysqlUser(): string
    {
        return 'root';
    }

    public function getMysqlPassword(): string
    {
        return '';
    }

    public function getMysqlDatabase(): string
    {
        return 'Phaster';
    }

    public function getSqlsrvServer(): string
    {
        return '(local)\SQLEXPRESS';
    }

    public function getSqlsrvConnInfo(): array
    {
        return [
            'Database' => 'Phaster',
            'ReturnDatesAsStrings' => true,
            'CharacterSet' => 'UTF-8',
        ];
    }
}
