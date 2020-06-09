<?php

namespace FLE\JsonHydrator\Database;

use FLE\JsonHydrator\Serializer\EntityCollection;
use FLE\JsonHydrator\Serializer\Serializer;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class Connection extends PDO
{
    use NestedTransactionPDO;

    public function __construct(string $dbName, string $host, string $port, string $dbUser, string $dbPwd, ?Stopwatch $stopwatch, Serializer $serializer, EntityCollection $entityCollection, ?LoggerInterface $logger)
    {
        parent::__construct("pgsql:dbname=$dbName;host=$host;port=$port;", $dbUser, $dbPwd);
        $this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$stopwatch, $serializer, $entityCollection, $logger]]);
    }

    /**
     * @param string $statement
     * @param array  $options
     *
     * @return bool|PDOStatement
     */
    public function prepare($statement, $options = [])
    {
        return parent::prepare($statement, $options);
    }
}
