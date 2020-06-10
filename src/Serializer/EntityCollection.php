<?php

namespace FLE\JsonHydrator\Serializer;

use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Entity\IdEntityInterface;
use FLE\JsonHydrator\Entity\UuidEntityInterface;
use LogicException;
use Metadata\MetadataFactory;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionException;
use function get_class;
use function is_array;
use function is_object;

class EntityCollection
{
    protected MetadataFactory $metadataFactory;
    protected array $collection = [];

    public function __construct(MetadataFactory $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    public function get(string $class, ?array $pkey): ?EntityInterface
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
     * @throws PersistException
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
     * @param EntityInterface $object
     * @param string[]        $pkey
     *
     * @return string|null
     *
     * @throw DetachException
     */
    public function remove(EntityInterface $object, array $pkey): ?string
    {
        if (!empty($pkey)) {
            $class = get_class($object);
            $key   = $this->getKey($class, $pkey);
            if (isset($this->collection[$key]) && $this->collection[$key] == $object) {
                unset($this->collection[$key]);
            } else {
                throw new DetachException($key, $object);
            }

            return $key;
        }

        return null;
    }

    /**
     * @param object|array|string|int $object
     *
     * @return string[]|int[]
     * @throws ReflectionException
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

        if (is_object($object) && $object instanceof EntityInterface) {
            $pkey['id'] = $object->getId();
        } else {
            $reflexion = new ReflectionClass($className);
            if ($reflexion->isSubclassOf(EntityInterface::class)) {
                if (is_array($object)) {
                    if (!isset($object['id'])) {
                        $pkey['id'] = null;
                    } else {
                        $pkey['id'] = $object['id'];
                    }
                } else {
                    $pkey['id'] = $object;
                }
            } else {
                throw new LogicException('You must pass "EntityInterface"');
            }
        }

        return $pkey;
    }

    protected function getKey(string $class, ?array $pkey): ?string
    {
        if ($pkey === null || empty($pkey)) {
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
     * @throws PersistException|ReflectionException
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
     * @return string|int|null
     *
     * @throws ReflectionException
     */
    public function detach(EntityInterface $object)
    {
        $pkey = $this->getPk($object);
        if ($this->has(get_class($object), $pkey)) {
            if ($object instanceof UuidEntityInterface) {
                $this->remove($object, $pkey);

                return current($pkey);
            }
            if ($object instanceof IdEntityInterface && $object->getId() !== null) {
                $this->remove($object, $pkey);

                return current($pkey);
            }
        }

        return null;
    }
}
