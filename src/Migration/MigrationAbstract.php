<?php

namespace FLE\JsonHydrator\Migration;

use FLE\JsonHydrator\Database\Connection;
use Psr\Log\LoggerInterface;
use function file_get_contents;

abstract class MigrationAbstract
{
    /**
     * @var int
     */
    protected static $version;

    /**
     * @var string
     */
    protected $requestDirectory;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Migration constructor.
     *
     * @param Connection      $connection
     * @param string          $requestDirectory
     * @param LoggerInterface $logger
     */
    public function __construct(Connection $connection, ?string $requestDirectory, LoggerInterface $logger)
    {
        if ($requestDirectory === null || empty($requestDirectory)) {
            $requestDirectory = __DIR__.'/sql/request';
        }
        $this->requestDirectory   = $requestDirectory;
        $this->connection         = $connection;
        $this->logger             = $logger;
    }

    /**
     * Execute migrations
     * Stop and rollback on first fail.
     *
     * In dry mode is always rollback
     *
     * @param array       $list
     *                           null  = skip
     *                           false = error
     *                           true  = valid
     * @param bool        $dry
     * @param string|null $error
     */
    abstract public function execute(?array &$list = [], bool $dry = false, ?string &$error = null): void;

    abstract public function generateFile(string $name = null): array;

    /**
     * Create Table if not exist.
     *
     * @return int
     */
    abstract protected function createTable(): int;

    protected function getNextVersion(): ?int
    {
        if (self::$version === null) {
            $stmt = $this->connection->prepare(file_get_contents($this->requestDirectory.'/getNextVersion.sql'));
            $stmt->execute();
            $version = $stmt->fetchColumn();

            self::$version = $version === false ? null : $version;
        }

        return self::$version;
    }

    abstract protected function getShortName(string $filename): string;
}
