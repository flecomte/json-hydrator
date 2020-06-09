<?php

namespace FLE\JsonHydrator\Entity;

use Ramsey\Uuid\Uuid;

trait UuidEntity
{
    protected string $id;

    public function __construct()
    {
        $this->id = (string) Uuid::uuid4();
    }

    /**
     * @param string $id
     */
    public static function getReference($id): EntityInterface
    {
        $n = new self();
        $n->id = $id;

        return $n;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
