<?php

namespace FLE\JsonHydrator\Entity;

use DateTime;

trait ImportedAtEntity
{
    protected ?DateTime $importedAt = null;

    public function getImportedAt(): ?DateTime
    {
        return $this->importedAt;
    }

    public function setImportedAt(DateTime $importedAt): self
    {
        $this->importedAt = $importedAt;

        return $this;
    }
}
