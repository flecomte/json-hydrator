<?php

namespace FLE\JsonHydrator\Entity;

trait IdEntity
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return IdEntity
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return IdEntity
     */
    public function duplicate()
    {
        $copy     = clone $this;
        $copy->id = null;

        return $copy;
    }
}
