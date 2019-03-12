<?php

namespace FLE\JsonHydrator\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

trait CreatedByEntity
{
    /**
     * @var UserInterface
     */
    protected $createdBy;

    /**
     * @return UserInterface
     */
    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    /**
     * @param UserInterface $createdBy
     *
     * @return CreatedByEntity
     */
    public function setCreatedBy(UserInterface $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
