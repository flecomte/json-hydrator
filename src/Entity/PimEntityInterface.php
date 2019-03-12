<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

interface PimEntityInterface extends IdEntityInterface
{
    /**
     * @return int
     */
    public function getId(): ?int;

    /**
     * @param int $id
     *
     * @return PimEntityInterface
     */
    public function setId(int $id);

    /**
     * @return DateTime
     */
    public function getImportedAt(): DateTime;

    /**
     * @param DateTime $importedAt
     *
     * @return PimEntityInterface
     */
    public function setImportedAt(DateTime $importedAt);

    /**
     * @return DateTime
     */
    public function getUpdatedAt(): DateTime;

    /**
     * @param DateTime $updatedAt
     *
     * @return PimEntityInterface
     */
    public function setUpdatedAt(DateTime $updatedAt);

    /**
     * @return bool
     */
    public function isPublished(): bool;

    /**
     * @param bool $published
     *
     * @return PublishedEntity
     */
    public function setPublished(bool $published);
}
