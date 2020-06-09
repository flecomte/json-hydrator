<?php

namespace FLE\JsonHydrator\Entity;

trait IdEntity
{
    protected int $id;

    /**
     * @param int $id
     */
    public static function getReference($id): self
    {
        $n = new self();
        $n->id = $id;

        return $n;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
