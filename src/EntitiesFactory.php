<?php

namespace DevTheorem\Phaster;

interface EntitiesFactory
{
    /**
     * @param class-string<Entities> $class
     */
    public function createEntities(string $class): Entities;
}
