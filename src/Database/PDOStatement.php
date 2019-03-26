<?php

namespace FLE\JsonHydrator\Database;

use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Repository\NotFoundException;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\Serializer;
use JMS\Serializer\Exception\NotAcceptableException;
use JMS\Serializer\SerializationContext;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use function get_class;
use function implode;
use function is_array;
use function json_decode;
use function microtime;
use function preg_match;
use function round;
use function strlen;
use function substr;

class PDOStatement extends \PDOStatement
{
    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityCollection
     */
    private $entityCollection;

    /**
     * @var array
     */
    private $params;

    /**
     * @var bool
     */
    private $isExecuted = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var float
     */
    private $duration;

    /**
     * @var string
     */
    private $name;

    protected function __construct(?Stopwatch $stopwatch, Serializer $serializer, EntityCollection $entityCollection, ?LoggerInterface $logger)
    {
        $this->stopwatch        = $stopwatch;
        $this->serializer       = $serializer;
        $this->entityCollection = $entityCollection;
        $this->logger           = $logger;
    }

    /**
     * @param null $inputParameters
     *
     * @return bool
     */
    public function execute($inputParameters = null)
    {
        if ($this->isExecuted == true) {
            return false;
        }
        $this->isExecuted = true;
        $startTime        = microtime(true);
        try {
            $this->stopwatch && $this->stopwatch->start('sql.query');
            $result = parent::execute($inputParameters);
            $this->stopwatch && $this->stopwatch->stop('sql.query');
        } catch (PDOException $e) {
            $this->logger && $this->logger->critical($e->getMessage(), [
                'query'  => $this->queryString,
                'params' => $this->params,
            ]);
            if ($this->name) {
                throw new PDOException("{$e->getMessage()}\n\n(Query name: {$this->name})", 0, $e);
            }
            throw $e;
        } finally {
            $this->duration = microtime(true) - $startTime;
        }

        return $result ?? null;
    }

    public function bindParam($parameter, &$variable, $dataType = PDO::PARAM_STR, $length = null, $driverOptions = null)
    {
        $this->params[$parameter] = $variable;
        parent::bindParam($parameter, $variable, $dataType, $length, $driverOptions);
    }

    public function bindValue($parameter, $variable, $dataType = PDO::PARAM_STR)
    {
        $this->params[$parameter] = $variable;
        parent::bindValue($parameter, $variable, $dataType);
    }

    /**
     * bind multiple parameters.
     *
     * @param $parameters
     */
    public function bindParams($parameters)
    {
        foreach ($parameters as $key => $parameter) {
            $this->bindParam($key, $parameter);
        }
    }

    /**
     * @param EntityInterface $entity
     * @param string[]        $group
     *
     * @return bool
     */
    public function bindEntity(EntityInterface $entity, array $group = null)
    {
        $this->entityCollection->persist($entity);

        preg_match('/[^\\\]+$/', get_class($entity), $matches);
        $shortName = $matches[0];

        $sc = new SerializationContext();
        $sc->setGroups($group === null ? $shortName : $group);

        $json = $this->serializer->serialize($entity, 'json', $sc);

        return $this->bindParam(':'.strtolower($shortName), $json);
    }

    /**
     * @param EntityInterface[] $entities
     * @param string[]        $group
     *
     * @return bool
     */
    public function bindEntities(array $entities, array $group = null)
    {
        foreach ($entities as $entity) {
            $this->entityCollection->persist($entity);
        }

        preg_match('/[^\\\]+$/', get_class($entities[0]), $matches);
        $shortName = $matches[0];

        $sc = new SerializationContext();
        $sc->setGroups($group === null ? $shortName : $group);

        $json = $this->serializer->serialize($entities, 'json', $sc);

        return $this->bindParam(':'.strtolower($shortName).'s', $json);
    }

    /**
     * @param string $fqn
     *
     * @return EntityInterface
     *
     * @throws NotFoundException
     */
    public function fetchEntity(string $fqn)
    {
        $this->execute();
        $json = $this->fetchColumn();
        $this->logResult($json);
        if ($json === false || $json === null) {
            throw new NotFoundException($fqn);
        }

        return $this->serializer->deserialize($json, $fqn, 'json');
    }

    /**
     * @return array
     *
     * @throws NotFoundException
     */
    public function fetchAsArray()
    {
        $this->execute();
        $json = $this->fetchColumn();
        $this->logResult($json);
        if ($json === false || $json === null) {
            return [];
        }

        return json_decode($json, true);
    }

    /**
     * @param string   $fqn
     * @param int|null $count
     *
     * @return EntityInterface[]
     *
     * @throws NotAcceptableException
     */
    public function fetchEntities(string $fqn, int &$count = null)
    {
        $this->execute();
        $all = $this->fetch();
        $this->logResult($all);
        $count = $all['count'] ?? null;
        $json  = $all[0];
        if ($json === false || $json === null) {
            $count = 0;

            return [];
        }
        if (substr($json, 0, 1) !== '[') {
            throw new NotAcceptableException('The json must be an array');
        }

        return $this->serializer->deserialize($json, "array<$fqn>", 'json');
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param mixed $result
     */
    public function logResult($result)
    {
        if ($this->logger) {
            $size     = round(strlen(is_array($result) ? implode('', $result) : $result) / 1024, 2);
            $duration = round($this->duration * 1000, 2);
            $level    = $size > 50 || $duration > 300 ? 'warning' : 'debug';
            $this->logger->log($level, ($this->name ?? 'SQL Query')." in $duration ms ($size kb)", [
                'name'     => $this->name,
                'size'     => $size,
                'query'    => $this->queryString,
                'params'   => $this->params,
                'duration' => $duration,
                'result'   => $result,
            ]);
        }
    }
}
