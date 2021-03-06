<?php

namespace FLE\JsonHydrator\Migration\Command;

use FLE\JsonHydrator\Database\Connection;
use FLE\JsonHydrator\Migration\Migration;
use FLE\JsonHydrator\Migration\MigrationFunctions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function str_pad;
use const STR_PAD_LEFT;

/**
 * Class StatusMigrationCommand.
 */
class MigrationStatusCommand extends Command
{
    protected static $defaultName = 'migration:status';

    private Migration $migration;
    private MigrationFunctions $migrationFunctions;
    private Connection $connection;

    /**
     * MigrationStatusCommand constructor.
     *
     * @param Migration          $migration
     * @param MigrationFunctions $migrationFunctions
     * @param Connection         $connection
     */
    public function __construct(Migration $migration, MigrationFunctions $migrationFunctions, Connection $connection)
    {
        parent::__construct();
        $this->migration          = $migration;
        $this->migrationFunctions = $migrationFunctions;
        $this->connection         = $connection;
    }

    protected function configure()
    {
        $this->setDescription('List all migration left');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isUpToDate = true;
        $this->migration->status($migrations, $error);
        $table = new Table($output);
        $table->setHeaderTitle('Status of Migrations script');
        $table->setHeaders(['Migration scripts', 'Status', 'Up', 'Down', 'Version']);

        foreach ($migrations as $filename => $infos) {
            if ($infos['executed']) {
                $status = '<info>Executed</info>';
            } else {
                $status     = '<error>Not executed</error>';
                $isUpToDate = false;
            }

            if ($infos['up'] === false) {
                $up         = '<error>SQL Error</error>';
                $isUpToDate = false;
            } elseif ($infos['up'] === true) {
                $up = '<info>SQL is valid</info>';
            } else {
                $up = '<comment>Skip test</comment>';
            }

            if ($infos['down'] === false) {
                $down       = '<error>SQL Error</error>';
                $isUpToDate = false;
            } elseif ($infos['down'] === true || $infos['down'] <= -1) {
                $down = '<info>SQL is valid</info>';
            } elseif ($infos['version'] === 1) {
                $down       = '<error>Missing</error>';
                $isUpToDate = false;
            } else {
                $down       = '<error>Missing</error>';
                $isUpToDate = false;
            }

            if ($infos['down'] === -2) {
                $status     = '<error>Need Rollback</error>';
                $isUpToDate = false;
            }

            $table->addRow([
                $filename,
                $status,
                $up,
                $down,
                str_pad($infos['version'], 7, ' ', STR_PAD_LEFT),
            ]);
        }
        $table->render();

        $output->writeln("<error>$error</error>");

        /* FUNCTIONS */
        $this->connection->beginTransaction();
        $this->migration->execute($migrations);
        $this->migrationFunctions->status($migrations, $error);
        $this->connection->rollBack();

        $table = new Table($output);
        $table->setHeaderTitle('Status of Functions');
        $table->setHeaders(['Functions', 'Status', 'Validation', 'Version']);

        foreach ($migrations as $filename => $infos) {
            if ($infos['status'] === null) {
                $status     = '<info>Is up to date</info>';
                $valitation = '<comment>Skip</comment>';
            } elseif ($infos['status'] === false) {
                $status     = '<error>On Error</error>';
                $valitation = '<error>SQL fail</error>';
                $isUpToDate = false;
            } elseif ($infos['status'] === +1) {
                $status     = '<error>Need Creation</error>';
                $valitation = '<info>Valid for creation</info>';
                $isUpToDate = false;
            } elseif ($infos['status'] === +2) {
                $status     = '<error>Need Update</error>';
                $valitation = '<info>Valid for update</info>';
                $isUpToDate = false;
            } elseif ($infos['status'] === -1) {
                $status     = '<error>Need Remove</error>';
                $valitation = '<info>Valid for remove</info>';
                $isUpToDate = false;
            } else {
                $status     = '<comment>Skip test</comment>';
                $valitation = '';
            }

            $table->addRow([
                $filename,
                $status,
                $valitation,
                str_pad($infos['version'], 7, ' ', STR_PAD_LEFT),
            ]);
        }
        $table->render();

        $output->writeln("<error>$error</error>");

        if ($error) {
            return 2;
        }

        return $isUpToDate ? 0 : 1;
    }
}
