<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Entity\UuidEntityInterface;

interface UuidRepository
{
    /**
     * @param string $uuid
     *
     * @return UuidEntityInterface
     *
     * @throws NotFoundException
     */
    public function findByUuid(string $uuid);
}
