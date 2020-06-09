<?php

namespace FLE\JsonHydrator\Entity;

trait PublishedEntity
{
    protected bool $published;

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): PublishedEntity
    {
        $this->published = $published;

        return $this;
    }
}
