<?php

namespace FLE\JsonHydrator\Migration;

use Exception;
use Throwable;

class BrokenMigrationException extends Exception
{
    public function __construct($message = 'The migrations are broken', string $migration = null, Throwable $previous = null)
    {
        parent::__construct("$message for: $migration", 0, $previous);
    }
}
