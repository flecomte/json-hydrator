<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait CreatedAtEntity
{
    protected DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt = null): self
    {
        $this->createdAt = $createdAt ?? new DateTime();

        return $this;
    }
}
