<?php

namespace FLE\JsonHydrator\Entity;

interface IdEntityInterface extends EntityInterface
{
    public function getId(): ?int;

    public function setId(int $id);

    /**
     * @param int $id
     */
    static function getReference($id): self;
}
