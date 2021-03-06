<?php

namespace FLE\JsonHydrator\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

trait CreatedByEntity
{
    protected UserInterface $createdBy;

    public function getCreatedBy(): UserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
