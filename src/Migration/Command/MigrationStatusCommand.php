<?php

namespace FLE\JsonHydrator\Migration\Command;

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

    /**
     * @var Migration
     */
    private $migration;

    /**
     * @var MigrationFunctions
     */
    private $migrationFunctions;

    /**
     * MigrationStatusCommand constructor.
     *
     * @param Migration          $migration
     * @param MigrationFunctions $migrationFunctions
     */
    public function __construct(Migration $migration, MigrationFunctions $migrationFunctions)
    {
        parent::__construct();
        $this->migration          = $migration;
        $this->migrationFunctions = $migrationFunctions;
    }

    protected function configure()
    {
        $this->setDescription('List all migration left');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isUpToDate = true;
        $this->migration->status($migrations, $error);
        $table = new Table($output);
        $table->setHeaderTitle('Status of Migrations script');
        $table->setHeaders(['Migration scripts', 'Status', 'Up', 'Down', 'Version']);

        foreach ($migrations as $filename => $infos) {
            $status = $infos['executed'] ? '<info>executed</info>' : '<error>not executed</error>';

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
                $down       = '<comment>Missing</comment>';
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

        $this->migrationFunctions->status($migrations, $error);
        $table = new Table($output);
        $table->setHeaderTitle('Status of Functions');
        $table->setHeaders(['Functions', 'Status', 'Validation', 'Version']);

        foreach ($migrations as $filename => $infos) {
            if ($infos['status'] === null) {
                $status     = '<info>Is up to date</info>';
                $valitation = '<info>Skip</info>';
            } elseif ($infos['status'] === false) {
                $status     = '';
                $valitation = '<error>SQL Error</error>';
                $isUpToDate = false;
            } elseif ($infos['status'] === +1) {
                $status     = '<error>Need Update</error>';
                $valitation = '<info>Valid for creation</info>';
                $isUpToDate = false;
            } elseif ($infos['status'] === +2) {
                $status     = '<error>Need Update</error>';
                $valitation = '<info>Valid for update</info>';
                $isUpToDate = false;
            } elseif ($infos['status'] === -1) {
                $status     = '<error>Need Remove</error>';
                $valitation = '<info>SQL is valid for delete</info>';
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
