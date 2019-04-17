<?php

namespace FLE\JsonHydrator\Serializer\EventSubscriber;

use Exception;
use FLE\JsonHydrator\Serializer\EntityCollection;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;

/**
 * Save Unserialized Entity to the temporary EntityCollection.
 */
class SaveEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityCollection
     */
    private $entityCollection;

    public function __construct(EntityCollection $entityCollection)
    {
        $this->entityCollection = $entityCollection;
    }

    /**
     * Returns the events to which this class has subscribed.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event'  => 'serializer.post_deserialize',
                'method' => 'onPostDeserialize',
            ],
        ];
    }

    /**
     * @param ObjectEvent $event
     *
     * @throws Exception
     */
    public function onPostDeserialize(ObjectEvent $event)
    {
        if (!$event->getContext()->hasAttribute('persist') || $event->getContext()->getAttribute('persist') !== false) {
            $this->entityCollection->persist($event->getObject());
        }
    }
}
