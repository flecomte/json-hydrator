<?php

namespace FLE\JsonHydrator\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

trait UpdatedByEntity
{
    protected ?UserInterface $updatedBy;

    public function getUpdatedBy(): ?UserInterface
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?UserInterface $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
