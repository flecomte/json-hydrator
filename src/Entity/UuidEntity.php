<?php

namespace FLE\JsonHydrator\Entity;

use Ramsey\Uuid\Uuid;

trait UuidEntity
{
    /**
     * @var string
     */
    protected $uuid;

    public function __construct()
    {
        $this->setUuid();
    }

    /**
     * @return string
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return UuidEntity
     */
    public function setUuid(string $uuid = null)
    {
        $this->uuid = $uuid ?? (string) Uuid::uuid4();

        return $this;
    }

    /**
     * @return UuidEntity
     */
    public function duplicate()
    {
        $copy = clone $this;
        $copy->setUuid();

        return $copy;
    }
}
