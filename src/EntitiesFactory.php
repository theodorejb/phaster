<?php

declare(strict_types=1);

namespace Phaster;

interface EntitiesFactory
{
    public function createEntities($class): Entities;
}
