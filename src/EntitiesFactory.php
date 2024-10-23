<?php

declare(strict_types=1);

namespace DevTheorem\Phaster;

interface EntitiesFactory
{
    /**
     * @param class-string<Entities> $class
     */
    public function createEntities(string $class): Entities;
}
