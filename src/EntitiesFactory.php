<?php

declare(strict_types=1);

namespace theodorejb\Phaster;

interface EntitiesFactory
{
    public function createEntities($class): Entities;
}
