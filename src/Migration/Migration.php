<?php

namespace FLE\JsonHydrator\Migration;

use DateTime;
use FLE\JsonHydrator\Database\Connection;
use Generator;
use Psr\Log\LoggerInterface;
use Throwable;
use function array_combine;
use function array_fill;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function in_array;
use function is_dir;
use function krsort;
use function ksort;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function str_repeat;
use function str_replace;
use function strlen;
use function substr;
use function substr_count;
use const SORT_STRING;

class Migration extends MigrationAbstract
{
    /**
     * @var string
     */
    protected $migrationDirectory;

    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * Migration constructor.
     *
     * @param Connection      $connection
     * @param string          $migrationDirectory
     * @param string          $requestDirectory
     * @param LoggerInterface $logger
     * @param string          $dateFormat
     */
    public function __construct(Connection $connection, string $migrationDirectory, ?string $requestDirectory, LoggerInterface $logger, string $dateFormat = 'Y-m/Y-m-d_H-i-s')
    {
        parent::__construct($connection, $requestDirectory, $logger);
        $this->migrationDirectory = $migrationDirectory;
        $this->dateFormat         = $dateFormat;
    }

    /**
     * Generate the blank file with the right name.
     *
     * @param null $name
     *
     * @return array
     */
    public function generateFile(string $name = null): array
    {
        $now           = new DateTime();
        $datePath      = $now->format($this->dateFormat);
        $directoryName = pathinfo($this->migrationDirectory.'/'.$datePath, PATHINFO_DIRNAME);
        if (!is_dir($directoryName)) {
            mkdir($directoryName, 0755, true);
        }
        if ($name !== null && !empty($name) && substr($name, 0, 1) !== '-') {
            $name = '-'.$name;
        }

        $fileNameUp   = "$this->migrationDirectory/$datePath$name.up.sql";
        $fileNameDown = "$this->migrationDirectory/$datePath$name.down.sql";

        file_put_contents($fileNameUp, '-- Your SQL script for UP migration here');
        file_put_contents($fileNameDown, '-- Your SQL script for DOWN migration here');

        return [$fileNameUp, $fileNameDown];
    }

    /**
     * Execute UP then DOWN migrations
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
    public function execute(?array &$list = [], bool $dry = false, ?string &$error = null): void
    {
        $this->connection->beginTransaction();

        /* Create Table if not exist */
        $this->createTable();

        $allList = $this->mergeMigrations();
        /* Execute UP and DOWN migrations */
        try {
            $this->executeUp($upList, $error);
            $this->executeDown($downList, $error);

            /* Merge result and sort */
            $list = array_merge($allList, $upList ?? [], $downList ?? []);
            ksort($list, SORT_STRING);

            /* Rollback if up or down migration fail */
            if ($dry === false) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
            }
        } catch (BrokenMigrationException $e) {
            $list = array_merge($allList, $upList ?? [], $downList ?? []);
            $this->connection->rollBack();
        }
    }

    /**
     * Execute up migrations
     * Stop if fail
     * WARNING: no transaction is present !
     *
     * @param array  $list
     *                      null  = skip
     *                      false = error
     *                      true  = valid
     * @param string $error
     *
     * @throws BrokenMigrationException
     */
    protected function executeUp(?array &$list = [], ?string &$error = null): void
    {
        $version               = $this->getNextVersion();
        $executedMigration     = $this->getExecutedMigration();
        $downMigrations        = $this->getDownMigrationsContent();
        $upMigrations          = $this->getUpMigrationsContent();
        foreach ($upMigrations as $upFilename => $upSql) {
            try {
                $downSql = $downMigrations[$upFilename];

                /* Skip migration if is already executed */
                if (array_key_exists($upFilename, $executedMigration)) {
                    $this->logger->info("Skip migration, already executed: '$upFilename'");
                    $list[$upFilename] = null;
                    continue;
                }

                /* Stop migration if empty */
                if ($this->sqlIsEmpty($upSql)) {
                    $list[$upFilename] = false;

                    throw new BrokenMigrationException('Migration is empty', $upFilename);
                }

                /* Execute migrations */
                $this->logger->info("Execute migration '$upFilename'", ['request' => $upSql]);
                $this->connection->exec($upSql);
                $this->connection->prepare(file_get_contents($this->requestDirectory.'/insert.sql'))
                    ->execute([
                        ':filename' => $upFilename,
                        ':up'       => $upSql,
                        ':down'     => $downSql,
                        ':version'  => $version,
                    ]);
                $list[$upFilename] = true;
            } catch (Throwable $e) {
                /* Stop if migration fail */
                $list[$upFilename] = false;
                $error             = $e->getMessage();
                $this->logger->critical("Migration '$upFilename' Fail: $error", ['request' => $upSql ?? '', 'exeption' => $e]);
                throw new BrokenMigrationException($error, $upFilename);
            }
        }
    }

    /**
     * Execute down migrations
     * Stop if fail
     * WARNING: no transaction is present !
     *
     * If version is defined, force down to the defined version
     *
     * @param array       $list
     *                             null  = skip
     *                             false = error
     *                             true  = valid
     * @param string|null $error
     * @param int|null    $version
     *
     * @throws BrokenMigrationException
     */
    protected function executeDown(?array &$list = [], ?string &$error = null, int $version = null): void
    {
        /*
         * Find down migration to be execute
         * (If the file does not exist but is present in DB)
         */
        $toDownMigrations = [];
        foreach ($this->getExecutedMigration() as $upFilename => $migration) {
            /* If version is defined, force down */
            if ($version !== null && $migration['version'] >= $version) {
                $toDownMigrations[$upFilename] = $migration['down'];
                continue;
            }

            $upMigrationFiles = $this->getUpMigrationsFiles();
            if (!in_array($upFilename, $upMigrationFiles) && $migration['version'] > 1) {
                $toDownMigrations[$upFilename] = $migration['down'];
            }
        }

        /*
         * Execute down migrations as reverse order
         */
        krsort($toDownMigrations, SORT_STRING);
        foreach ($toDownMigrations as $upFilename => $sqlDown) {
            try {
                $this->connection->exec($sqlDown);
                $this->connection->prepare(file_get_contents($this->requestDirectory.'/delete.sql'))
                    ->execute([':filename' => $upFilename]);
                $list[$upFilename] = $this->getDownMigrationExist($upFilename) ? -1 : -2;
            } catch (Throwable $e) {
                $error             = $e->getMessage();
                $this->logger->error($error);
                $list[$upFilename] = false;

                throw new BrokenMigrationException($error, $upFilename);
            }
        }
    }

    /**
     * @param array       $list
     *                           Array with up and down status
     *                           null  = skip or missing
     *                           false = error
     *                           true  = valid
     * @param string|null $error
     */
    public function status(?array &$list = [], ?string &$error = null)
    {
        $this->connection->beginTransaction();
        $this->createTable();
        $migrations    = $this->getExecutedMigration();
        $allMigrations = $this->mergeMigrationsVersion();
        $upList        = [];
        $downList      = [];
        try {
            $this->executeUp($upList, $error);
            $this->executeDown($downList, $error, 2);
        } catch (BrokenMigrationException $e) {
        }

        foreach ($allMigrations as $upFileName => $version) {
            $isExecuted    = array_key_exists($upFileName, $migrations);
            $migrationPass = $upList[$upFileName] ?? null;
            $up            = $isExecuted ? null : $migrationPass;

            $downContent  = $this->getDownMigrationContent($upFileName);
            if (isset($downList[$upFileName]) && $downList[$upFileName] != null) { /* If is tested */
                $down = $downList[$upFileName];
            } elseif ($downContent === null) {
                $down = null;
            } elseif ($this->sqlIsEmpty($downContent)) {
                $down = false;
            } else {
                $down = null;
            }

            $list[$this->getShortName($upFileName)] = [
                'executed' => $isExecuted,
                'up'       => $up,
                'down'     => $down,
                'version'  => $version ?? null,
            ];
        }
        $this->connection->rollBack();
    }

    /**
     * Return All migrations, files and executed
     * value is the version.
     *
     * @return array
     */
    protected function mergeMigrationsVersion(): array
    {
        $allMigration = [];
        foreach ($this->getUpMigrationsFiles() as $filename) {
            $allMigration[$filename] = null;
        }
        foreach ($this->getExecutedMigration() as $filename => $migration) {
            $allMigration[$filename] = $migration['version'];
        }

        ksort($allMigration, SORT_STRING);

        return $allMigration;
    }

    /**
     * Return All migrations, files and executed.
     *
     * @return array
     */
    protected function mergeMigrations(): array
    {
        $allList = $this->mergeMigrationsVersion();

        /* Set all value of $allList to null */
        $nullArray = array_fill(1, count($allList), null);

        return array_combine(array_keys($allList), $nullArray);
    }

    protected function getShortName(string $filename): string
    {
        return str_replace($this->migrationDirectory, '.', $filename);
    }

    private function getDirPattern(): string
    {
        $count = substr_count($this->dateFormat, '/');

        return str_repeat('*/', $count);
    }

    protected function getUpMigrationsFiles(): array
    {
        $patternOfDirectories = $this->getDirPattern();

        return glob("$this->migrationDirectory/$patternOfDirectories*.up.sql");
    }

    protected function getDownMigrationsFiles(): array
    {
        $patternOfDirectories = $this->getDirPattern();

        return glob("$this->migrationDirectory/$patternOfDirectories*.down.sql");
    }

    protected function getDownMigrationContent(string $upFileName): ?string
    {
        $downFileName = $this->getDownFilename($upFileName);
        if (!file_exists($downFileName)) {
            return null;
        }

        return file_get_contents($downFileName);
    }

    protected function getDownMigrationExist(string $upFileName): ?string
    {
        return $this->getDownMigrationContent($upFileName) !== null;
    }

    protected function getDownFilename(string $upFileName): string
    {
        return str_replace('.up.', '.down.', $upFileName);
    }

    private function getDownMigrationsContent(): array
    {
        $executedMigration = $this->getExecutedMigration();
        $isFirstMigration  = empty($executedMigration);
        $upMigrations      = $this->getUpMigrationsFiles();
        $downMigrations    = [];
        foreach ($upMigrations as $upFilename) {
            $downSql = $this->getDownMigrationContent($upFilename);
            if ($downSql === null || $this->sqlIsEmpty($downSql)) {
                if ($isFirstMigration) {
                    $downMigrations[$upFilename] = /* @lang PostgreSQL */ 'DO $$BEGIN RAISE EXCEPTION \'You cannot rollback the first migration\' USING ERRCODE = \'MI001\'; END;$$';
                } else {
                    $downMigrations[$upFilename] = /* @lang PostgreSQL */ 'DO $$BEGIN RAISE EXCEPTION \'No migration down is defined, you cannot rollback\' USING ERRCODE = \'MI002\'; END;$$';
                }
            } else {
                $downMigrations[$upFilename] = $downSql;
            }
        }

        return $downMigrations;
    }

    /**
     * @return Generator
     */
    protected function getUpMigrationsContent(): Generator
    {
        foreach ($this->getUpMigrationsFiles() as $file) {
            yield $file => file_get_contents($file);
        }
    }

    /**
     * Create Table if not exist.
     *
     * @return int
     */
    protected function createTable(): int
    {
        $sql = file_get_contents($this->requestDirectory.'/createTable.sql');
        $this->logger->debug('Init Table Migration', ['request' => $sql]);

        return $this->connection->exec($sql);
    }

    protected function getExecutedMigration(): array
    {
        return $this->connection->prepare(file_get_contents($this->requestDirectory.'/findAll.sql'))->fetchAsArray();
    }

    protected function sqlIsEmpty(string $sql): bool
    {
        $cleaned = preg_replace(['`--.*$`m', '` *`', '`\n`'], '', $sql);

        return strlen($cleaned) === 0;
    }
}
