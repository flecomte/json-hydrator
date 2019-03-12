<?php

namespace FLE\JsonHydrator\Repository;

use Exception;
use Throwable;

class NotFoundException extends Exception
{
    private $uuid;

    public function __construct(string $uuid, Throwable $previous = null)
    {
        parent::__construct("Not found with this UUID : $uuid", 0, $previous);
        $this->uuid = $uuid;
    }

    public function getUuid()
    {
        return $this->uuid;
    }
}
