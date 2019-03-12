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
use function trim;
use const PATHINFO_BASENAME;
use const PATHINFO_DIRNAME;
use const SORT_STRING;

class MigrationFunctions extends MigrationAbstract
{
    /**
     * @var string
     */
    protected $functionsDirectory;

    /**
     * Migration constructor.
     *
     * @param Connection      $connection
     * @param string          $requestDirectory
     * @param string          $functionsDirectory
     * @param LoggerInterface $logger
     */
    public function __construct(Connection $connection, ?string $requestDirectory, string $functionsDirectory, LoggerInterface $logger)
    {
        parent::__construct($connection, $requestDirectory, $logger);
        $this->functionsDirectory = $functionsDirectory;
    }

    /**
     * Generate the blank file with the right name.
     *
     * @param string $name
     *
     * @return array
     */
    public function generateFile(string $name = null): array
    {
        $directoryName = pathinfo($this->functionsDirectory.'/'.$name, PATHINFO_DIRNAME);
        $functionName  = strtolower(pathinfo($this->functionsDirectory.'/'.$name, PATHINFO_BASENAME));
        if (!is_dir($directoryName)) {
            mkdir($directoryName, 0755, true);
        }

        $filename   = "$this->functionsDirectory/$name.sql";

        $sql = <<<SQL
CREATE OR REPLACE FUNCTION $functionName (_my_param TEXT DEFAULT 'hello') RETURNS jsonb
  LANGUAGE plpgsql
  PARALLEL SAFE
AS $$
DECLARE
  _my_param_bis int = 0;
BEGIN
  -- SQL HERE
END;
$$;
SQL;
        file_put_contents($filename, $sql);

        return [$filename];
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
     * @param array|null  $list
     * @param string|null $error
     *
     * @throws BrokenMigrationException
     */
    protected function functionsUp(?array &$list = [], ?string &$error = null): void
    {
        $this->createTable();
        $version           = $this->getNextVersion();
        $functionsContent  = $this->getFunctionsContent();
        $executedFunctions = $this->getExecutedFunctions();
        foreach ($functionsContent as $filename => $newFunctionContent) {
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
                $oldDefinition      = $executedFunctions[$filename]['up'];

                /* Execute ONLY if function has changed (definition and content) */
                if ($oldFunctionContent === $newFunctionContent) {
                    $list[$filename] = null;
                    continue;
                }

                /*
                 * If definition has change, is imposible to replace it.
                 * So, try to remove and recreate the function
                 */
                if ($oldDefinition != $newDefinition) {
                    $this->logger->warning("Remove $oldDefinition and recreate $newDefinition");
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
                $this->connection->prepare(file_get_contents($this->requestDirectory.'/upsertFunctions.sql'))
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
    }

    /**
     * @param array|null  $list
     * @param string|null $error
     *
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

                    $this->connection->prepare(file_get_contents($this->requestDirectory.'/deleteFunctions.sql'))
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
        $executedFunctions = $this->getExecutedFunctions();
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
     *
     * @return int
     */
    protected function createTable(): int
    {
        $sql = file_get_contents($this->requestDirectory.'/createTableFunctions.sql');
        $this->logger->debug('Init Table Migration Functions', ['request' => $sql]);

        return $this->connection->exec($sql);
    }

    protected function getExecutedFunctions(): array
    {
        return $this->connection->prepare(file_get_contents($this->requestDirectory.'/findAllFunctions.sql'))->fetchAsArray();
    }

    protected function getDefinition(string $functionContent)
    {
        ['name' => $name, 'params' => $params, 'types' => $types, 'return' => $return] = $this->getDefinitionMatches($functionContent);

        return "$name ($params) $return";
    }

    protected function getDefinitionShort(string $functionContent)
    {
        ['name' => $name, 'params' => $params, 'types' => $types] = $this->getDefinitionMatches($functionContent);

        return "$name ($types)";
    }

    protected function getDeleteFunctionRequest(string $functionContent)
    {
        $definition = $this->getDefinitionShort($functionContent);

        return "DROP FUNCTION $definition;";
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

    private function getDefinitionMatches(string $functionContent)
    {
        /* find function name and params */
        preg_match('`create .*(procedure|function) *(?<name>[^\(\s]+)\s*\((?<params>(\s*((IN|OUT|INOUT|VARIADIC)?\s+)?([^\s,\)]+\s+)?([^\s,\)]+)(\s+(?:default\s|=)\s*[^\s,\)]+)?\s*(,|(?=\))))*)\) *(?<return>RETURNS *[^ ]+)`mi', $functionContent, $matchs);
        $name   = trim($matchs['name']);
        $params = trim($matchs['params']);
        $return = trim($matchs['return']);

        preg_match_all('`\s*(?<param>((IN|OUT|INOUT|VARIADIC)?\s+)?([^\s,\)]+\s+)?(?<type>[^\s,\)]+)(\s+(?:default\s|=)\s*[^\s,\)]+)?)\s*(,|$)`mi', $params, $matchs);
        $params = $matchs['param'];
        $types  = $matchs['type'];

        $paramsList = implode(', ', array_map('trim', $params));
        $typesList  = implode(', ', array_map('trim', $types));

        return [
            'name'    => $name,
            'params'  => $paramsList,
            'types'   => $typesList,
            'return'  => $return,
        ];
    }
}
