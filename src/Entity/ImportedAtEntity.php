<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait ImportedAtEntity
{
    /**
     * @var DateTime
     */
    protected $importedAt;

    /**
     * @return DateTime
     */
    public function getImportedAt(): DateTime
    {
        return $this->importedAt;
    }

    /**
     * @param DateTime $importedAt
     *
     * @return ImportedAtEntity
     */
    public function setImportedAt(DateTime $importedAt)
    {
        $this->importedAt = $importedAt;

        return $this;
    }
}
