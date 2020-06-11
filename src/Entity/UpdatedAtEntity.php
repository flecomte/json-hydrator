<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait UpdatedAtEntity
{
    protected DateTime $updatedAt;

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
