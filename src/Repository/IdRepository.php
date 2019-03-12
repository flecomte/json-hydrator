<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Entity\IdEntityInterface;

interface IdRepository
{
    /**
     * @param int $id
     *
     * @return IdEntityInterface
     *
     * @throws NotFoundException
     */
    public function findById(int $id);
}
