<?php

namespace FLE\JsonHydrator\Serializer;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer as JmsSerializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function count;
use function debug_backtrace;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function microtime;
use function round;

class Serializer implements SerializerInterface, ArrayTransformerInterface
{
    /**
     * @var SerializerInterface
     */
    protected $jmsSerializer;

    /**
     * @var Stopwatch|null
     */
    private $stopwatch;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Serializer constructor.
     *
     * @param JmsSerializer   $jmsSerializer
     * @param Stopwatch|null  $stopwatch
     * @param LoggerInterface $logger
     */
    public function __construct(JmsSerializer $jmsSerializer, ?Stopwatch $stopwatch, ?LoggerInterface $logger)
    {
        $this->jmsSerializer = $jmsSerializer;
        $this->stopwatch     = $stopwatch;
        $this->logger        = $logger;
    }

    /**
     * @param mixed                     $data
     * @param string                    $format
     * @param SerializationContext|null $context
     * @param string|null               $type
     *
     * @return string
     */
    public function serialize($data, string $format, ?SerializationContext $context = null, ?string $type = null): string
    {
        if ($this->stopwatch) {
            $suffix = is_object($data) ? '.'.get_class($data) : '';
            $this->stopwatch->start('serialize'.$suffix);
        }
        $start = microtime(true);

        $result = $this->jmsSerializer->serialize($data, $format, $context, $type);

        $duration = microtime(true) - $start;
        if ($this->stopwatch) {
            $this->stopwatch->stop('serialize'.$suffix);
        }
        $this->logResult($data, $result, $duration);

        return $result;
    }

    /**
     * @param string                      $data
     * @param string                      $type
     * @param string                      $format
     * @param DeserializationContext|null $context
     *
     * @return mixed
     */
    public function deserialize(string $data, string $type, string $format, ?DeserializationContext $context = null)
    {
        $this->stopwatch && $this->stopwatch->start('deserialize.'.$type);
        $start = microtime(true);

        $result = $this->jmsSerializer->deserialize($data, $type, $format, $context);

        $duration = microtime(true) - $start;
        $this->stopwatch && $this->stopwatch->stop('deserialize.'.$type);
        $this->logResult($data, $result, $duration);

        return $result;
    }

    /**
     * Converts objects to an array structure.
     *
     * This is useful when the data needs to be passed on to other methods which expect array data.
     *
     * @param mixed                     $data    anything that converts to an array, typically an object or an array of objects
     * @param SerializationContext|null $context
     * @param string|null               $type
     *
     * @return array
     */
    public function toArray($data, ?SerializationContext $context = null, ?string $type = null): array
    {
        $this->stopwatch && $this->stopwatch->start('toArray.'.$type);
        $start = microtime(true);

        $result = $this->jmsSerializer->toArray($data, $context, $type);

        $duration = microtime(true) - $start;
        $this->stopwatch && $this->stopwatch->stop('toArray.'.$type);
        $this->logResult($data, $result, $duration);

        return $result;
    }

    /**
     * Restores objects from an array structure.
     *
     * @param array                       $data
     * @param string                      $type
     * @param DeserializationContext|null $context
     *
     * @return mixed this returns whatever the passed type is, typically an object or an array of objects
     */
    public function fromArray(array $data, string $type, ?DeserializationContext $context = null)
    {
        $this->stopwatch && $this->stopwatch->start('fromArray.'.$type);
        $start = microtime(true);

        $result = $this->jmsSerializer->fromArray($data, $type, $context);

        $duration = microtime(true) - $start;
        $this->stopwatch && $this->stopwatch->stop('fromArray.'.$type);
        $this->logResult($data, $result, $duration);

        return $result;
    }

    /**
     * @param mixed $source
     * @param mixed $result
     * @param float $duration
     */
    private function logResult($source, $result, float $duration)
    {
        if ($this->logger) {
            $object        = is_string($source) ? $result : $source;
            $directionName = is_string($source) ? 'Deserialize' : 'Serialize';
            $count         = is_array($object) ? count($object) : 1;
            $roundDuration = round($duration * 1000, 2);
            $name          = $this->getLogName($object);
            $level         = $roundDuration > 50 ? 'warning' : 'info';
            $this->logger->log($level, "$directionName $name in $roundDuration ms", [
                'type'      => $name,
                'duration'  => $duration,
                'count'     => $count,
                'backtrace' => debug_backtrace(),
                'result'    => $result,
                'source'    => $source,
            ]);
        }
    }

    private function getLogName($data)
    {
        if (is_object($data)) {
            return get_class($data);
        } elseif (is_array($data)) {
            if (isset($data[0])) {
                return is_object($data[0]) ? get_class($data[0]).'[]' : gettype($data[0]).'[]';
            }

            return 'array';
        }

        return 'serializer';
    }
}
