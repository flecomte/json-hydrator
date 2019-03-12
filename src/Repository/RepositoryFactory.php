<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Database\Connection;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\SerializerInterface;
use function class_exists;

class RepositoryFactory
{
    /**
     * @var Connection
     */
    protected $connection;
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var EntityCollection
     */
    private $entityCollection;

    /**
     * @var string
     */
    private $requestDirectory;

    /**
     * RepositoryFactory constructor.
     *
     * @param Connection          $connection
     * @param SerializerInterface $serializer
     * @param EntityCollection    $entityCollection
     * @param string              $requestDirectory
     */
    public function __construct(Connection $connection, SerializerInterface $serializer, EntityCollection $entityCollection, string $requestDirectory)
    {
        $this->connection       = $connection;
        $this->serializer       = $serializer;
        $this->entityCollection = $entityCollection;
        $this->requestDirectory = $requestDirectory;
    }

    /**
     * @param string $fqn
     *
     * @return RepositoryInterface
     */
    public function getRepository(string $fqn): RepositoryInterface
    {
        $var = (preg_replace('/\\\\Entity\\\\/', '\\Repository\\', $fqn).'Repository');
        if (class_exists($var)) {
            return new $var($this->connection, $this->serializer, $fqn, $this->entityCollection, $this->requestDirectory);
        } else {
            throw new NoRepositoryFoundException($fqn);
        }
    }
}
