<?php

namespace FLE\JsonHydrator\Entity;

interface UuidEntityInterface extends EntityInterface
{
    public function getId(): ?string;

    /**
     * @param string $id
     */
    static function getReference($id): self;
}
