<?php

namespace FLE\JsonHydrator\Migration;

use FLE\JsonHydrator\Database\Connection;
use Psr\Log\LoggerInterface;
use function file_get_contents;

abstract class MigrationAbstract
{
    protected static int $version;
    protected ?string $requestMigrationDirectory;
    protected Connection $connection;
    protected LoggerInterface $logger;

    public static function camelToSnack($input)
    {
        return ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $input)), '_');
    }

    /**
     * Migration constructor.
     *
     * @param Connection      $connection
     * @param string          $requestMigrationDirectory
     * @param LoggerInterface $logger
     */
    public function __construct(Connection $connection, ?string $requestMigrationDirectory, LoggerInterface $logger)
    {
        if ($requestMigrationDirectory === null || empty($requestMigrationDirectory)) {
            $requestMigrationDirectory = __DIR__.'/sql/request';
        }
        $this->requestMigrationDirectory   = $requestMigrationDirectory;
        $this->connection                  = $connection;
        $this->logger                      = $logger;
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
            $stmt = $this->connection->prepare(file_get_contents($this->requestMigrationDirectory.'/getNextVersion.sql'));
            $stmt->execute();
            $version = $stmt->fetchColumn();

            self::$version = $version === false ? null : $version;
        }

        return self::$version;
    }

    abstract protected function getShortName(string $filename): string;
}
