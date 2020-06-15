<?php

namespace FLE\JsonHydrator\Migration;

use FLE\JsonHydrator\Database\Connection;
use Generator;
use PDOException;
use Psr\Log\LoggerInterface;
use function array_combine;
use function array_fill;
use function array_keys;
use function array_map;
use function count;
use function file_exists;
use function file_get_contents;
use function glob;
use function implode;
use function in_array;
use function ksort;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;
use const PATHINFO_DIRNAME;
use const SORT_STRING;

class MigrationFunctions extends MigrationAbstract
{
    protected string $functionsDirectory;
    private ?string $requestDirectory;

    /**
     * Migration constructor.
     *
     * @param Connection      $connection
     * @param string          $requestDirectory
     * @param string          $functionsDirectory
     * @param string          $requestMigrationDirectory
     * @param LoggerInterface $logger
     */
    public function __construct(Connection $connection, string $requestDirectory, string $functionsDirectory, ?string $requestMigrationDirectory, LoggerInterface $logger)
    {
        parent::__construct($connection, $requestMigrationDirectory, $logger);
        $this->functionsDirectory = $functionsDirectory;
        $this->requestDirectory   = $requestDirectory;
    }

    /**
     * Generate the blank file with the right name.
     *
     * @param string|null $entity
     * @param string|null $request
     *
     * @return array
     */
    public function generateFile(string $entity = null, string $request = null): array
    {
        $functionDirectoryName = pathinfo($this->functionsDirectory.'/'.$entity, PATHINFO_DIRNAME);
        if (!is_dir($this->functionsDirectory.'/'.$entity)) {
            mkdir($this->functionsDirectory.'/'.$entity, 0755, true);
        }
        if (!is_dir($this->requestDirectory.'/'.$entity)) {
            mkdir($this->requestDirectory.'/'.$entity, 0755, true);
        }

        $table        = self::camelToSnack($entity);
        $filename     = $entity.'/'.$request;
        $functionName = strtolower(str_replace('/', '_', $entity.'/'.self::camelToSnack($request)));

        $functionFilename = "$this->functionsDirectory/$filename.sql";
        $requestFilename  = "$this->requestDirectory/$filename.sql";

        $sql = <<<SQL
            CREATE OR REPLACE FUNCTION $functionName (_uuid uuid) RETURNS json
              LANGUAGE plpgsql
              PARALLEL SAFE
            AS $$
            DECLARE
              result json;
            BEGIN
              SELECT to_json(t) INTO result
                FROM
                (
                  SELECT
                    *
                  FROM $table
                  WHERE uuid = _uuid
                ) t;
                RETURN result;
            END;
            $$;
            SQL;
        file_put_contents($functionFilename, $sql);

        $requestSQL = <<<SQL
            SELECT * FROM $functionName(:uuid)
            SQL;

        file_put_contents($requestFilename, $requestSQL);

        return [$functionFilename, $requestFilename];
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

        $list = $this->mergeMigrationsVersion();
        /* Execute functions migrations */
        try {
            $this->functionsUp($list, $error);
            $this->functionsDown($list, $error);

            /* Rollback if up or down migration fail */
            if ($dry === false) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
            }
        } catch (BrokenMigrationException $e) {
            $this->connection->rollBack();
        }
    }

    /**
     * @throws BrokenMigrationException
     */
    protected function functionsUp(?array &$list = [], ?string &$error = null): void
    {
        $this->createTable();
        $functionsContent  = $this->getFunctionsContent();
        $notYetPassed = [];
        foreach ($functionsContent as $filename => $newFunctionContent) {
            $notYetPassed[$filename] = $newFunctionContent;
        }
        /* If one function not pass, retry */
        $successAtThisPass = false;
        while ($successAtThisPass = true && count($notYetPassed) > 0) {
            $successAtThisPass = false;
            foreach ($notYetPassed as $filename => $newFunctionContent) {
                $this->connection->beginTransaction();
                try {
                    $this->logger->debug("$filename : Try to execute");
                    $this->functionUp($filename, $newFunctionContent, $list, $error);
                    unset($notYetPassed[$filename]);
                    $successAtThisPass = true;
                    $this->logger->debug("$filename : passed");
                    $this->connection->commit();
                } catch (BrokenMigrationException $e) {
                    $this->logger->debug("$filename : Fail with errorcode {$e->getPrevious()->getCode()}");
                    if ($e->getPrevious()->getCode() == '42883') {
                        $this->logger->debug("$filename : is marked to retry for the next pass");
                        $notYetPassed[$filename] = $newFunctionContent;
                        $this->connection->rollBack();
                    } else {
                        throw $e;
                    }
                }
            }
        }
        if (count($notYetPassed) == 0) {
            $error = null;
        }
    }

    /**
     * @throws BrokenMigrationException
     */
    protected function functionUp(
        string $filename,
        string $newFunctionContent,
        ?array &$list = [],
        ?string &$error = null
    ): void
    {
        $version           = $this->getNextVersion();
        $executedFunctions = $this->getExecutedFunctions();
        $alreadyExist  = isset($executedFunctions[$filename]);
        $newDefinition = $this->getDefinition($newFunctionContent);

        /* Check if OR REPLACE exist */
        if (!preg_match('`\s+OR\s+REPLACE\s+`mi', $newFunctionContent)) {
            $list[$filename] = false;
            $this->logger->error("The function must be declared with 'OR REPLACE': $filename");
            throw new BrokenMigrationException("The function must be declared with 'OR REPLACE': $filename", $filename);
        }

        /* Check if only one function exist per file */
        if (preg_match_all('`create .*(procedure|function) *(?<name>[^\(\s]+)\s*`mi', $newFunctionContent) > 1) {
            $list[$filename] = false;
            $error           = "Only one function per file must be declared in $filename";
            $this->logger->error($error);
            throw new BrokenMigrationException($error, $filename);
        }

        /* If function already exist in DB */
        if ($alreadyExist) {
            $oldFunctionContent = $executedFunctions[$filename]['up'];
            $oldDefinition      = $this->getDefinition($oldFunctionContent);

            /* Execute ONLY if function has changed (definition and content) */
            if ($oldFunctionContent === $newFunctionContent) {
                $list[$filename] = null;
                return;
            }

            /*
             * If definition has change, is imposible to replace it.
             * So, try to remove and recreate the function
             */
            if ($oldDefinition != $newDefinition) {
                $this->logger->warning("Remove and recreate:\n    $oldDefinition\n    $newDefinition");
                $deleteSQL = $this->getDeleteFunctionRequest($oldFunctionContent);
                try {
                    $this->connection->exec($deleteSQL);
                } catch (PDOException $e) {
                    $list[$filename] = false;
                    $error           = "Remove for recreate function IMPOSIBLE: $oldDefinition";
                    $this->logger->error($error, ['message' => $e->getMessage()]);
                    throw new BrokenMigrationException($error, $filename, $e);
                }
            }
        }

        /* Create or replace function */
        try {
            $deleteSQL = $this->getDeleteFunctionRequest($newFunctionContent);
            $this->connection->exec($newFunctionContent);
            $this->connection->prepare(file_get_contents($this->requestMigrationDirectory.'/upsertFunctions.sql'))
                ->execute([
                    ':filename'   => $filename,
                    ':definition' => $newDefinition,
                    ':up'         => $newFunctionContent,
                    ':down'       => $deleteSQL,
                    ':version'    => $version,
                ]);

            $list[$filename] = $alreadyExist ? +2 : +1;
        } catch (PDOException $e) {
            $list[$filename] = false;
            $newDefinition   = $newDefinition ?? $this->getDefinition($newFunctionContent);
            $error           = "The function $newDefinition cant be create/replaced. \n\n".$e->getMessage();
            throw new BrokenMigrationException($error, $filename, $e);
        }
    }

    /**
     * @throws BrokenMigrationException
     */
    protected function functionsDown(?array &$list = [], ?string &$error = null): void
    {
        $this->createTable();
        $functionsFiles    = $this->getFunctionsFiles();
        $executedFunctions = $this->getExecutedFunctions();
        foreach ($executedFunctions as $currentFunction) {
            $filename          = $currentFunction['filename'];
            $currentDefinition = $currentFunction['definition'];
            $currentDown       = $currentFunction['down'];
            if (!in_array($filename, $functionsFiles)) {
                try {
                    $this->logger->info("Remove old function: $currentDefinition");
                    $this->connection->exec($currentDown);

                    $this->connection->prepare(file_get_contents($this->requestMigrationDirectory.'/deleteFunctions.sql'))
                        ->execute([':filename' => $filename]);

                    $list[$filename] = -1;
                } catch (PDOException $e) {
                    $list[$filename] = false;
                    $error           = "Remove for recreate function IMPOSIBLE: $currentDefinition";
                    $this->logger->error($error, ['message' => $e->getMessage()]);
                    throw new BrokenMigrationException($error, $filename, $e);
                }
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
        $allMigrations     = $this->mergeMigrationsVersion();
        $list              = [];
        try {
            $listTmp = [];
            $this->functionsUp($listTmp, $error);
            $this->functionsDown($listTmp, $error);
        } catch (BrokenMigrationException $e) {
        }

        foreach ($allMigrations as $filename => $version) {
            $migrationPass = $listTmp[$filename] ?? null;

            $list[$this->getShortName($filename)] = [
                'status'  => $migrationPass,
                'version' => $version ?? null,
            ];
        }
        $this->connection->rollBack();
    }

    protected function getShortName(string $filename): string
    {
        return str_replace($this->functionsDirectory, '.', $filename);
    }

    protected function getFunctionsFiles(): array
    {
        return glob("$this->functionsDirectory/*/*.sql");
    }

    /**
     * @return Generator
     */
    protected function getFunctionsContent(): Generator
    {
        foreach ($this->getFunctionsFiles() as $file) {
            yield $file => file_get_contents($file);
        }
    }

    protected function getFunctionContent(string $filename): ?string
    {
        if (!file_exists($filename)) {
            return null;
        }

        return file_get_contents($filename);
    }

    /**
     * Create Table if not exist.
     */
    protected function createTable(): int
    {
        $sql = file_get_contents($this->requestMigrationDirectory.'/createTableFunctions.sql');
        $this->logger->debug('Init Table Migration Functions', ['request' => $sql]);

        return $this->connection->exec($sql);
    }

    protected function getExecutedFunctions(): array
    {
        return $this->connection->prepare(file_get_contents($this->requestMigrationDirectory.'/findAllFunctions.sql'))->fetchAsArray();
    }

    protected function getDefinition(string $functionContent)
    {
        ['name' => $name, 'params' => $params, 'return' => $return] = $this->getDefinitionMatches($functionContent);

        $paramsList = implode(', ', array_map('trim', $params));

        return "$name ($paramsList) $return";
    }

    protected function getDefinitionShort(string $functionContent)
    {
        ['name' => $name, 'directions' => $directions, 'types' => $types] = $this->getDefinitionMatches($functionContent);

        /* Remove OUT parameters */
        foreach ($directions as $key => $direction) {
            if (strtoupper($direction) === 'OUT') {
                unset($types[$key]);
            }
        }

        $typesList  = implode(', ', array_map('trim', $types));

        return "$name ($typesList)";
    }

    protected function getDeleteFunctionRequest(string $functionContent)
    {
        $definition = $this->getDefinitionShort($functionContent);

        return "DROP FUNCTION IF EXISTS $definition;";
    }

    /**
     * Return All migrations, files and executed
     * value is the version.
     */
    protected function mergeMigrationsVersion(): array
    {
        $allMigration = [];
        foreach ($this->getFunctionsFiles() as $filename) {
            $allMigration[$filename] = null;
        }
        foreach ($this->getExecutedFunctions() as $filename => $migration) {
            $allMigration[$filename] = $migration['version'];
        }

        ksort($allMigration, SORT_STRING);

        return $allMigration;
    }

    /**
     * Return All migrations, files and executed.
     */
    protected function mergeMigrations(): array
    {
        $allList = $this->mergeMigrationsVersion();

        /* Set all value of $allList to null */
        $nullArray = array_fill(1, count($allList), null);

        return array_combine(array_keys($allList), $nullArray);
    }

    private function getDefinitionMatches(string $functionContent)
    {
        /* find function name and params */
        preg_match('`create .*(procedure|function) *(?<name>[^\(\s]+)\s*\((?<params>(\s*((IN|OUT|INOUT|VARIADIC)?\s+)?([^\s,\)]+\s+)?([^\s,\)]+)(\s+(?:default\s|=)\s*[^\s,\)]+)?\s*(,|(?=\))))*)\) *(?<return>RETURNS *[^ ]+)?`mi', $functionContent, $matchs);
        $name   = trim($matchs['name']);
        $params = trim($matchs['params']);
        $return = isset($matchs['return']) ? trim($matchs['return']) : '';

        preg_match_all('`\s*(?<param>((?<direction>IN|OUT|INOUT|VARIADIC)?\s+)?([^\s,\)]+\s+)?(?<type>[^\s,\)]+)(\s+(?:default\s|=)\s*[^\s,\)]+)?)\s*(,|$)`mi', $params, $matchs);
        $params     = $matchs['param'];
        $directions = $matchs['direction'];
        $types      = $matchs['type'];

        return [
            'name'       => $name,
            'params'     => $params,
            'directions' => $directions,
            'types'      => $types,
            'return'     => $return,
        ];
    }
}
