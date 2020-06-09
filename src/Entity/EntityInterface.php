<?php

namespace FLE\JsonHydrator\Entity;

interface EntityInterface
{
    static function getReference($id): self;
}
