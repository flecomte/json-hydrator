<?php

namespace FLE\JsonHydrator\Serializer;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Instantiator\Instantiator;
use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Entity\IdEntityInterface;
use FLE\JsonHydrator\Entity\TypeInterface;
use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Visitor\DeserializationVisitorInterface;
use ReflectionClass;
use ReflectionException;
use function in_array;

class UnserializeObjectConstructor implements ObjectConstructorInterface
{
    /**
     * @var Instantiator
     */
    private $instantiator;

    /**
     * @var EntityCollection
     */
    private $entityCollection;

    public function __construct(EntityCollection $entityCollection)
    {
        $this->entityCollection = $entityCollection;
    }

    /**
     * Instantiate the Entity and put it into the EntityCollection.
     *
     * @param object|array|string|int|bool $data
     *
     * @return object|EntityInterface|null
     * @throws ExceptionInterface|ReflectionException
     */
    public function construct(DeserializationVisitorInterface $visitor, ClassMetadata $metadata, $data, array $type, DeserializationContext $context): ?object
    {
        $persist = !$context->hasAttribute('persist') || $context->getAttribute('persist') !== false;

        /* If empty or set ad clone use Doctrine\Instantiator */
        if ($data === null || in_array('clone', $type['params']) || $persist === false) {
            return $this->getInstantiator()->instantiate($metadata->name);
        }
        /* $type['name'] is the class name (FQN) */
        $reflexion = new ReflectionClass($type['name']);
        if (!$reflexion->isSubclassOf(TypeInterface::class)) {
            $pk = $this->entityCollection->getPk($data, $type['name']);
            /* Get already exist entity or return null */
            $object = $this->entityCollection->get($metadata->name, $pk);
        }

        if (!isset($object) || null === $object) {
            /** @var EntityInterface|TypeInterface $object */
            $object = $this->getInstantiator()->instantiate($metadata->name);
            if ($object instanceof EntityInterface) {
                /* Set the PK to the new Entity and put it into the EntityCollection */
                $newPk = self::setPk($object, $data);
                $this->entityCollection->set($object, $newPk);
            }
        }

        return $object;
    }

    private function getInstantiator(): Instantiator
    {
        if (null === $this->instantiator) {
            $this->instantiator = new Instantiator();
        }

        return $this->instantiator;
    }

    /**
     * Set the PK Entity from raw data.
     *
     * @param EntityInterface $entity
     * @param array           $data
     *
     * @return array the new PK
     */
    public static function setPk(EntityInterface $entity, array $data): array
    {
        $pk = [];
        if ($entity instanceof IdEntityInterface) {
            $entity->setId($data['id'] ?? null);
            $pk['id'] = $entity->getId();
        }

        return $pk;
    }
}
