<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Database\Connection;
use FLE\JsonHydrator\Database\PDOStatement;
use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\SerializerInterface;
use LogicException;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use function file_exists;
use function get_class;
use function is_iterable;
use function preg_match;
use function ucfirst;

abstract class AbstractRepository implements RepositoryInterface
{
    protected Connection $connection;
    protected SerializerInterface $serializer;
    /**
     * Short name class
     */
    protected string $entityName;
    protected EntityCollection $entityCollection;
    protected bool $cache = false;
    protected string $shortName;
    private string $requestDirectory;

    /**
     * @throws NotFoundException
     * @throws LogicException
     */
    public function refresh(EntityInterface $entity): EntityInterface
    {
        $repoNoCache = $this->disableCache();

        return $repoNoCache->genericFindBy($entity->getId(), get_class($entity));
    }

    protected static function calculatePagination(int $limit = null, int $page = null): int
    {
        if ($limit === null || $limit <= 0) {
            $limit = 10;
        }
        if ($page === null || $page <= 0) {
            $page = 1;
        }

        return ($limit * $page) - $limit;
    }

    protected static function paginate(array $elements, int $count, int $limit = null, int $page = null): Pagerfanta
    {
        $adapter = new FixedAdapter($count, $elements);
        $pager   = new Pagerfanta($adapter);
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage($page ?? 1);
        $pager->setMaxPerPage($limit ?? 10);

        return $pager;
    }

    public function __construct(Connection $connection, SerializerInterface $serializer, string $entityName, EntityCollection $entityCollection, string $requestDirectory)
    {
        preg_match('/[^\\\]+$/', $entityName, $matches);
        $this->shortName        = $matches[0];
        $this->connection       = $connection;
        $this->serializer       = $serializer;
        $this->entityName       = $entityName;
        $this->entityCollection = $entityCollection;
        $this->requestDirectory = $requestDirectory;
    }

    protected function genericFindAllPaginated(string $fqn, ?int $limit = null, ?int $page = null, array $extraParams = []): Pagerfanta
    {
        $count    = 0;
        $entities = $this->genericFindAll($fqn, $limit, self::calculatePagination($limit, $page), $count, $extraParams);

        return self::paginate($entities, $count, $limit, $page);
    }

    /**
     * @return EntityInterface[]
     */
    protected function genericFindAll(string $fqn, int $limit = null, ?int $offset = null, ?int &$count = null, array $extraParams = []): array
    {
        $limit  = $limit ?? 200;
        $offset = $offset ?? 0;
        $stmt   = $this->prepareRequest('findAll');
        $stmt->bindParam('limit', $limit);
        $stmt->bindParam('offset', $offset);
        if (is_iterable($extraParams)) {
            foreach ($extraParams as $name => $value) {
                $stmt->bindValue($name, $value);
            }
        }

        return $stmt->fetchEntities($fqn, $count);
    }

    /**
     * @return false|string
     *
     * @throws LogicException
     */
    protected function getRequest(string $requestName)
    {
        $fileName = $this->requestDirectory."/$this->shortName/$requestName.sql";
        if (!file_exists($fileName)) {
            throw new LogicException("You must implement the request \"$requestName\" for entity \"$this->shortName\" in file \"$fileName\"");
        }

        return file_get_contents($fileName);
    }

    /**
     * @return PDOStatement|bool|void
     */
    protected function prepareRequest(string $requestName)
    {
        $stmt = $this->connection->prepare($this->getRequest($requestName));
        preg_match('/[^\\\]+$/', get_class($this), $matches);
        $shortName = $matches[0];
        $stmt->setName($shortName.'::'.$requestName);

        return $stmt;
    }

    /**
     * @param mixed $value
     *
     * @throws NotFoundException
     */
    protected function genericFindBy($value, string $fqn, ?string $field = null): EntityInterface
    {
        if ($field === null) {
            $field = 'id';
        }

        if (null !== $cachedObject = $this->getCachedObject($fqn, $field, $value)) {
            return $cachedObject;
        }

        $stmt = $this->prepareRequest('findBy'.ucfirst($field));
        $stmt->bindParam($field, $value);

        return $stmt->fetchEntity($fqn);
    }

    /**
     * @param mixed  $value
     *
     * @throws NotFoundException
     */
    protected function genericFindByAsArray($value, string $field): array
    {
        $stmt = $this->prepareRequest('findBy'.ucfirst($field));
        $stmt->bindParam($field, $value);

        return $stmt->fetchAsArray();
    }

    /**
     * @param string[]|null   $groups
     *
     * @throws NotFoundException
     */
    protected function genericUpsert(EntityInterface $entity, array $groups = null): EntityInterface
    {
        $stmt = $this->prepareRequest('upsert');
        $stmt->bindEntity($entity, $groups);

        return $stmt->fetchEntity(get_class($entity));
    }

    /**
     * @param EntityInterface[] $entities
     * @param string[]|null     $groups
     *
     * @return EntityInterface[]
     */
    protected function genericMultiUpsert(array $entities, array $groups = null): array
    {
        $stmt = $this->prepareRequest('multiUpsert');
        $stmt->bindEntities($entities, $groups);

        return $stmt->fetchEntities(get_class($entities[0]));
    }

    /**
     * @throws NotFoundException
     */
    protected function genericDelete(EntityInterface $object): EntityInterface
    {
        $stmt = $this->prepareRequest('delete');

        $stmt->bindValue(':id', $object->getId());

        return $stmt->fetchEntity(get_class($object));
    }

    protected function getCachedObject($fqn, $field, $value): ?EntityInterface
    {
        if ($this->cache === true && null !== $cachedObject = $this->entityCollection->get($fqn, [$field => $value])) {
            return $cachedObject;
        }

        return null;
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    /**
     * @return AbstractRepository|RepositoryInterface
     */
    public function enableCache(): RepositoryInterface
    {
        if ($this->cache !== true) {
            $cachedRepo        = clone $this;
            $cachedRepo->cache = true;

            return $cachedRepo;
        }

        return $this;
    }

    /**
     * @return AbstractRepository|RepositoryInterface
     */
    public function disableCache(): RepositoryInterface
    {
        if ($this->cache !== false) {
            $cachedRepo        = clone $this;
            $cachedRepo->cache = false;

            return $cachedRepo;
        }

        return $this;
    }

    public function persist(EntityInterface $object)
    {
        return $this->entityCollection->persist($object);
    }

    public function detach(EntityInterface $object)
    {
        return $this->entityCollection->detach($object);
    }
}
