<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait UpdatedAtEntity
{
    /**
     * @var DateTime
     */
    protected $updatedAt;

    /**
     * @return DateTime
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     *
     * @return UpdatedAtEntity
     */
    public function setUpdatedAt(DateTime $updatedAt = null): self
    {
        $this->updatedAt = $updatedAt ?? new DateTime();

        return $this;
    }
}
