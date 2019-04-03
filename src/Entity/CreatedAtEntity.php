<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait CreatedAtEntity
{
    /**
     * @var DateTime
     */
    protected $createdAt;

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     *
     * @return CreatedAtEntity
     */
    public function setCreatedAt(DateTime $createdAt = null): self
    {
        $this->createdAt = $createdAt ?? new DateTime();

        return $this;
    }
}
