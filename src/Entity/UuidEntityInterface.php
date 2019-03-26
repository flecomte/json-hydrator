<?php

namespace FLE\JsonHydrator\Entity;

interface UuidEntityInterface extends EntityInterface, DuplicateInterface
{
    /**
     * @return string
     */
    public function getUuid(): ?string;

    /**
     * @param string $uuid
     *
     * @return UuidEntityInterface
     */
    public function setUuid(string $uuid = null);
}
