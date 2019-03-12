<?php

namespace FLE\JsonHydrator\Serializer;

use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Entity\IdEntityInterface;
use FLE\JsonHydrator\Entity\UuidEntityInterface;
use Exception;
use LogicException;
use Metadata\MetadataFactory;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use function get_class;
use function is_array;
use function is_object;

class EntityCollection
{
    /**
     * @var MetadataFactory
     */
    protected $metadataFactory;

    public function __construct(MetadataFactory $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @var array
     */
    protected $collection = [];

    public function get(string $class, array $pkey): ?EntityInterface
    {
        $key = $this->getKey($class, $pkey);

        return $this->collection[$key] ?? null;
    }

    public function has(string $class, array $pkey): bool
    {
        return $this->get($class, $pkey) !== null;
    }

    /**
     * @param EntityInterface $object
     * @param string[]        $pkey
     *
     * @return string|null
     */
    public function set(EntityInterface $object, array $pkey): ?string
    {
        if (!empty($pkey)) {
            $class = get_class($object);
            $key   = $this->getKey($class, $pkey);
            if (isset($this->collection[$key]) && $this->collection[$key] != $object) {
                throw new PersistException($key, $object);
            }
            $this->collection[$key] = $object;

            return $key;
        }

        return null;
    }

    /**
     * @param object|array|string|int $object
     * @param string                  $className
     *
     * @return string[]|int[]
     *
     * @throws LogicException
     */
    public function getPk($object, string $className = null): array
    {
        $pkey = [];
        if (is_object($object) && $className === null) {
            $className = get_class($object);
        }

        if ($className === null) {
            throw new LogicException('the argument $className of method EntityCollection::getPk must be defined if first argument is an array');
        }

        if (is_object($object)) {
            if ($object instanceof UuidEntityInterface) {
                $pkey['uuid'] = $object->getUuid();
            } elseif ($object instanceof IdEntityInterface) {
                $pkey['id'] = $object->getId();
            } else {
                throw new LogicException('You must pass "IdEntityInterface" or "UuidEntityInterface"');
            }
        } else {
            $reflexion = new ReflectionClass($className);
            if ($reflexion->implementsInterface(UuidEntityInterface::class)) {
                if (is_array($object)) {
                    if (!isset($object['uuid'])) {
                        throw new LogicException('"UuidEntityInterface" must have an uuid');
                    } else {
                        $pkey['uuid'] = $object['uuid'];
                    }
                } else {
                    $pkey['uuid'] = $object;
                }
            } elseif ($reflexion->implementsInterface(IdEntityInterface::class)) {
                if (is_array($object)) {
                    if (!isset($object['id'])) {
                        throw new LogicException('"IdEntityInterface" must have an id');
                    } else {
                        $pkey['id'] = $object['id'];
                    }
                } else {
                    $pkey['id'] = $object;
                }
            } else {
                throw new LogicException('You must pass "IdEntityInterface" or "UuidEntityInterface"');
            }
        }

        return $pkey;
    }

    protected function getKey(string $class, array $pkey): string
    {
        if (empty($pkey)) {
            return null;
        }
        $key = $class.'$$';
        foreach ($pkey as $name => $value) {
            $key .= $name.'$'.$value.'$$';
        }

        return $key;
    }

    public function getCollection(): array
    {
        return $this->collection;
    }

    /**
     * @param EntityInterface $object
     *
     * @return string|int|null
     *
     * @throws Exception
     */
    public function persist(EntityInterface $object)
    {
        $pkey = $this->getPk($object);
        if (!$this->has(get_class($object), $pkey)) {
            if ($object instanceof UuidEntityInterface) {
                if (empty($pkey) || current($pkey) === null) {
                    $pkName        = key($pkey);
                    $uuid          = Uuid::uuid4()->toString();
                    $pkey[$pkName] = $uuid;
                    $object->setUuid($uuid);
                }

                $this->set($object, $pkey);

                return current($pkey);
            }
            if ($object instanceof IdEntityInterface && $object->getId() !== null) {
                $this->set($object, $pkey);

                return current($pkey);
            }
        }

        return null;
    }

    /**
     * @param string $className
     * @param array  $pkey
     *
     * @return EntityInterface
     */
    public function getReference(string $className, array $pkey): EntityInterface
    {
        $entity = $this->get($className, $pkey);
        if ($entity === null) {
            /** @var EntityInterface $entity */
            $entity = new $className();
            if ($entity instanceof UuidEntityInterface) {
                $entity->setUuid($pkey['uuid']);
            } elseif ($entity instanceof IdEntityInterface) {
                $entity->setId($pkey['id']);
            } else {
                throw new LogicException('You must pass "IdEntityInterface" or "UuidEntityInterface"');
            }
        }

        return $entity;
    }
}
