<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Entity\IdEntityInterface;

interface IdRepository
{
    /**
     * @throws NotFoundException
     */
    public function findById(int $id): IdEntityInterface;
}
