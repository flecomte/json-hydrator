<?php

namespace FLE\JsonHydrator\Serializer;

use FLE\JsonHydrator\Entity\EntityInterface;
use LogicException;
use Throwable;
use function get_class;

class DetachException extends LogicException
{
    protected EntityInterface $object;

    public function __construct(string $key, EntityInterface $object, Throwable $previous = null)
    {
        $this->object = $object;
        $class        = get_class($object);
        parent::__construct("You cannot detach a another object ($class) with the same pk ($key)", 0, $previous);
    }
}
