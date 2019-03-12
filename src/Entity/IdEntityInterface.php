<?php

namespace FLE\JsonHydrator\Entity;

interface IdEntityInterface extends EntityInterface, DuplicateInterface
{
    /**
     * @return int
     */
    public function getId(): ?int;

    /**
     * @param int $id
     *
     * @return IdEntityInterface
     */
    public function setId(int $id);
}
