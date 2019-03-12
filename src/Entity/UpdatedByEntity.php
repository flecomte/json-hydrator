<?php

namespace FLE\JsonHydrator\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

trait UpdatedByEntity
{
    /**
     * @var UserInterface
     */
    protected $updatedBy;

    /**
     * @return UserInterface
     */
    public function getUpdatedBy(): ?UserInterface
    {
        return $this->updatedBy;
    }

    /**
     * @param UserInterface $updatedBy
     *
     * @return UpdatedByEntity
     */
    public function setUpdatedBy(UserInterface $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }
}
