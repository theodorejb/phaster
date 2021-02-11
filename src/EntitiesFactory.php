<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

interface EntitiesFactory
{
    /**
     * @param class-string<Entities> $class
     */
    public function createEntities(string $class): Entities;
}
