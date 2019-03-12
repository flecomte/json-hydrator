<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Entity\EntityInterface;

interface RepositoryInterface
{
    /**
     * @param string|int $pkValue
     *
     * @return EntityInterface
     */
    public function reference($pkValue): EntityInterface;

    /**
     * @param EntityInterface $entity
     *
     * @return EntityInterface
     */
    public function refresh(EntityInterface $entity): EntityInterface;

    public function beginTransaction();

    public function commit();

    public function rollBack();

    /**
     * @return AbstractRepository|RepositoryInterface
     */
    public function enableCache(): self;

    /**
     * @return AbstractRepository|RepositoryInterface
     */
    public function disableCache(): self;
}
