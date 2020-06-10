<?php

namespace FLE\JsonHydrator\Serializer\EventSubscriber;

use Exception;
use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Entity\TypeInterface;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\PersistException;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use LogicException;

/**
 * Save Unserialized Entity to the temporary EntityCollection.
 */
class SaveEventSubscriber implements EventSubscriberInterface
{
    private EntityCollection $entityCollection;

    public function __construct(EntityCollection $entityCollection)
    {
        $this->entityCollection = $entityCollection;
    }

    /**
     * Returns the events to which this class has subscribed.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            [
                'event'  => 'serializer.post_deserialize',
                'method' => 'onPostDeserialize',
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function onPostDeserialize(ObjectEvent $event)
    {
        if (!$event->getContext()->hasAttribute('persist') || $event->getContext()->getAttribute('persist') !== false) {
            $obj = $event->getObject();
            if ($obj instanceof EntityInterface) {
                $this->entityCollection->persist($event->getObject());
            } elseif (!$obj instanceof TypeInterface) {
                throw new LogicException('The object must be a "EntityInterface" or "TypeInterface"');
            }
        }
    }
}
