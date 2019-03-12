<?php

namespace FLE\JsonHydrator\Entity;

trait PublishedEntity
{
    /**
     * @var bool
     */
    protected $published;

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @param bool $published
     *
     * @return PublishedEntity
     */
    public function setPublished(bool $published)
    {
        $this->published = $published;

        return $this;
    }
}
