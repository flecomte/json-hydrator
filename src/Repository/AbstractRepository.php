<?php

namespace FLE\JsonHydrator\Repository;

use FLE\JsonHydrator\Database\Connection;
use FLE\JsonHydrator\Database\PDOStatement;
use FLE\JsonHydrator\Entity\EntityInterface;
use FLE\JsonHydrator\Entity\IdEntityInterface;
use FLE\JsonHydrator\Entity\UuidEntityInterface;
use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\SerializerInterface;
use LogicException;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use function class_implements;
use function file_exists;
use function get_class;
use function in_array;
use function is_iterable;
use function preg_match;
use function ucfirst;

abstract class AbstractRepository implements RepositoryInterface
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
     * @var string
     *             Short name class
     */
    protected $entityName;

    /**
     * @var EntityCollection
     */
    protected $entityCollection;

    /**
     * @var bool
     */
    protected $cache = false;

    /**
     * @var string
     */
    protected $shortName;

    /**
     * @var string
     */
    private $requestDirectory;

    /**
     * @param string|int $pkValue
     *
     * @return EntityInterface
     */
    public function reference($pkValue): EntityInterface
    {
        $pkey   = $this->entityCollection->getPk($pkValue, $this->entityName);
        $entity = $this->entityCollection->getReference($this->entityName, $pkey);

        return $entity;
    }

    /**
     * @param EntityInterface $entity
     *
     * @return EntityInterface
     *
     * @throws NotFoundException
     * @throws LogicException
     */
    public function refresh(EntityInterface $entity): EntityInterface
    {
        $repoNoCache = $this->disableCache();

        if ($entity instanceof IdEntityInterface) {
            return $repoNoCache->genericFindBy($entity->getId(), get_class($entity));
        } elseif ($entity instanceof UuidEntityInterface) {
            return $repoNoCache->genericFindBy($entity->getUuid(), get_class($entity));
        }

        throw new LogicException('You must pass "IdEntityInterface" or "UuidEntityInterface"');
    }

    /**
     * @param int $limit
     * @param int $page
     *
     * @return float|int Offset
     */
    protected static function calculatePagination(int $limit = null, int $page = null)
    {
        if ($limit === null || $limit <= 0) {
            $limit = 10;
        }
        if ($page === null || $page <= 0) {
            $page = 1;
        }

        return ($limit * $page) - $limit;
    }

    protected static function paginate(array $elements, int $count, int $limit = null, int $page = null)
    {
        $adapter = new FixedAdapter($count, $elements);
        $pager   = new Pagerfanta($adapter);
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage($page ?? 1);
        $pager->setMaxPerPage($limit ?? 10);

        return $pager;
    }

    /**
     * AbstractRepository constructor.
     *
     * @param Connection          $connection
     * @param SerializerInterface $serializer
     * @param string              $entityName
     * @param EntityCollection    $entityCollection
     * @param string              $requestDirectory
     */
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

    /**
     * @param string $fqn
     * @param int|null $limit
     * @param int|null $page
     * @param array $extraParams
     *
     * @return Pagerfanta
     */
    protected function genericFindAllPaginated(string $fqn, int $limit = null, int $page = null, array $extraParams = []): Pagerfanta
    {
        $count = 0;
        $entities = $this->genericFindAll($fqn, $limit, self::calculatePagination($limit, $page), $count, $extraParams);

        return self::paginate($entities, $count, $limit, $page);
    }

    /**
     * @param string $fqn
     * @param int $limit
     * @param int|null $offset
     * @param int|null $count
     * @param array $extraParams
     *
     * @return UuidEntityInterface[]
     */
    protected function genericFindAll(string $fqn, int $limit = null, int $offset = null, int &$count = null, array $extraParams = []): array
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
     * @param string $requestName
     *
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
     * @param string $requestName
     *
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
     * @param mixed  $value
     * @param string $fqn
     * @param string $field
     *
     * @return EntityInterface
     *
     * @throws NotFoundException
     */
    protected function genericFindBy($value, string $fqn, $field = null): EntityInterface
    {
        if ($field === null && in_array(UuidEntityInterface::class, class_implements($fqn))) {
            $field = 'uuid';
        } elseif ($field === null && in_array(IdEntityInterface::class, class_implements($fqn))) {
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
     * @param string $field
     *
     * @return array
     *
     * @throws NotFoundException
     */
    protected function genericFindByAsArray($value, $field): array
    {
        $stmt = $this->prepareRequest('findBy'.ucfirst($field));
        $stmt->bindParam($field, $value);

        return $stmt->fetchAsArray();
    }

    /**
     * @param EntityInterface $entity
     * @param string[]|null   $groups
     *
     * @return EntityInterface
     */
    protected function genericUpsert(EntityInterface $entity, array $groups = null): EntityInterface
    {
        $stmt = $this->prepareRequest('upsert');
        $stmt->bindEntity($entity, $groups);

        return $stmt->fetchEntity(get_class($entity));
    }

    /**
     * @param EntityInterface $object
     *
     * @return EntityInterface
     *
     * @throws NotFoundException
     */
    protected function genericDelete(EntityInterface $object): EntityInterface
    {
        $stmt = $this->prepareRequest('delete');

        if ($object instanceof IdEntityInterface) {
            $stmt->bindValue(':id', $object->getId());
        } elseif ($object instanceof UuidEntityInterface) {
            $stmt->bindValue(':uuid', $object->getUuid());
        }

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
}
