<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Entity\UuidEntityInterface;

interface UuidRepository
{
    /**
     * @param string $id
     *
     * @return UuidEntityInterface
     *
     * @throws NotFoundException
     */
    public function findById(string $id);
}
