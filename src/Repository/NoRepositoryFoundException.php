<?php

namespace FLE\JsonHydrator\Repository;

use Throwable;

class NoRepositoryFoundException extends \LogicException
{
    public function __construct(string $fqn, Throwable $previous = null)
    {
        parent::__construct("No repository found for $fqn class.", 0, $previous);
    }
}
