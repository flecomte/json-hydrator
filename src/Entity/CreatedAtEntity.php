<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;
use JMS\Serializer\Annotation as Serializer;

trait CreatedAtEntity
{
    /**
     * @Serializer\Type("DateTime<'Y-m-d\TH:i:s.u'>")
     */
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
