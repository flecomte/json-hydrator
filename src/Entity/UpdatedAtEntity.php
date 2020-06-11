<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;
use JMS\Serializer\Annotation as Serializer;

trait UpdatedAtEntity
{
    /**
     * @Serializer\Type("DateTime<'Y-m-d\TH:i:s.u'>")
     */
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
