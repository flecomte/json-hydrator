<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Database\Connection;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\SerializerInterface;
use function class_exists;

class RepositoryFactory
{
    protected Connection $connection;
    protected SerializerInterface $serializer;
    private EntityCollection $entityCollection;
    private string $requestDirectory;

    public function __construct(Connection $connection, SerializerInterface $serializer, EntityCollection $entityCollection, string $requestDirectory)
    {
        $this->connection       = $connection;
        $this->serializer       = $serializer;
        $this->entityCollection = $entityCollection;
        $this->requestDirectory = $requestDirectory;
    }

    public function getRepository(string $fqn, string $shortName = null): RepositoryInterface
    {
        /* If fqn is directly a repository name */
        if (class_exists($fqn)) {
            $class = new \ReflectionClass($fqn);
            if ($class->isSubclassOf(AbstractRepository::class)) {
                if ($shortName === null) {
                    preg_match('/([^\\\]+)(Repository)?$/', $fqn, $matches);
                    $shortName = $matches[1];
                }
                return new $fqn($this->connection, $this->serializer, $shortName, $this->entityCollection, $this->requestDirectory);
            }
        }

        /* If $fqn is an entity name */
        $var = (preg_replace('/\\\\Entity\\\\/', '\\Repository\\', $fqn).'Repository');
        if (class_exists($var)) {
            if ($shortName === null) {
                preg_match('/([^\\\]+)(Entity)?$/', $fqn, $matches);
                $shortName = $matches[1];
            }
            return new $var($this->connection, $this->serializer, $shortName, $this->entityCollection, $this->requestDirectory);
        } else {
            throw new NoRepositoryFoundException($fqn);
        }
    }
}
