<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait UpdatedAtEntity
{
    /**
     * @var DateTime
     */
    protected $updatedAt;

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt = null): self
    {
        $this->updatedAt = $updatedAt ?? new DateTime();

        return $this;
    }
}
