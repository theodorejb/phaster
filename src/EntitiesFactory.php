<?php

namespace Phaster;

interface EntitiesFactory
{
    public function createEntities($class): Entities;
}
