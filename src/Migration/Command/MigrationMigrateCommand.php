<?php

namespace FLE\JsonHydrator\Migration\Command;

use FLE\JsonHydrator\Migration\Migration;
use FLE\JsonHydrator\Migration\MigrationFunctions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class UpMigrationCommand.
 *
 * command : migration:migrate
 *
 * Search and executed the missing migration.
 */
class MigrationMigrateCommand extends Command
{
    protected static $defaultName = 'migration:migrate';

    /**
     * @var Migration
     */
    private $migration;

    /**
     * @var MigrationFunctions
     */
    private $migrationFunctions;

    /**
     * MigrationMigrateCommand constructor.
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
        $this->setDescription('Execute migrations')
            ->addOption('dry', 'd', InputOption::VALUE_NONE, 'Dry mode: Dont execut query')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Not prompt before migrate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandStatus = $this->getApplication()->find('migration:status');
        $arguments     = [
            'command' => 'migration:status',
        ];

        $inputStatus  = new ArrayInput($arguments);
        $outputStatus = new BufferedOutput();
        $outputStatus->setFormatter($output->getFormatter());
        $outputStatus->setVerbosity($output->getVerbosity());
        $returnCode = $commandStatus->run($inputStatus, $outputStatus);

        if ($returnCode === 1) { // if need to update
            if (!$input->getOption('force')) {
                $output->write($outputStatus->fetch());
                $helper   = $this->getHelper('question');
                $question = new ConfirmationQuestion('<question>Do you want to execute migrations ?</question> [Y/n]', true);

                if (!$helper->ask($input, $output, $question)) {
                    return 0;
                }
            }
        } elseif ($returnCode > 1) {
            $output->writeln('<error>Migration has errors</error>');
            $output->write($outputStatus->fetch());

            return $returnCode;
        } else {
            $output->writeln('<info>All is up to date</info>');

            return 0;
        }

        $dry = $input->getOption('dry');
        $this->migration->execute($migrations, $dry, $error);
        $table = new Table($output);
        $table->setHeaderTitle('Migrations scripts');
        $table->setHeaders(['Migration', 'Passed']);
        foreach ($migrations as $filename => $passed) {
            if ($passed === true) {
                $statusRow = '<info>Executed</info>';
            } elseif ($passed === -1) {
                $statusRow = '<info>Rollbacked</info>';
            } elseif ($passed === false) {
                $statusRow = '<error>SQL Error</error>';
            } else {
                $statusRow = '<comment>Skip</comment>';
            }
            $table->addRow([$filename, $statusRow]);
        }
        $table->render();

        if ($error !== null) {
            $output->writeln("<error>$error</error>");
        }

        /* Functions */

        $this->migrationFunctions->execute($migrations, $dry, $error);
        $table = new Table($output);
        $table->setHeaderTitle('Migrations Function');
        $table->setHeaders(['Functions', 'Passed']);
        foreach ($migrations as $filename => $passed) {
            if ($passed === +1) {
                $statusRow = '<info>Created</info>';
            } elseif ($passed === +2) {
                $statusRow = '<info>Updated</info>';
            } elseif ($passed === -1) {
                $statusRow = '<info>Removed</info>';
            } elseif ($passed === false) {
                $statusRow = '<error>SQL Error</error>';
            } else {
                $statusRow = '<comment>Skip</comment>';
            }
            $table->addRow([$filename, $statusRow]);
        }
        $table->render();

        if ($error !== null) {
            $output->writeln("<error>$error</error>");

            return 1;
        }

        return 0;
    }
}
