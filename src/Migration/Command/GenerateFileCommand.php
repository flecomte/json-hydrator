<?php

namespace FLE\JsonHydrator\Migration\Command;

use FLE\JsonHydrator\Migration\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFileCommand extends Command
{
    protected static $defaultName = 'migration:generate:sql';

    /**
     * @var Migration
     */
    private $migration;

    /**
     * GenerateFileCommand constructor.
     *
     * @param Migration $migration
     */
    public function __construct(Migration $migration)
    {
        parent::__construct();
        $this->migration = $migration;
    }

    protected function configure()
    {
        $this->setDescription('Generate the blank file with the right name.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Add name to the generated files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaderTitle('Generated files');
        $table->setHeaders(['Filename']);
        foreach ($this->migration->generateFile($input->getArgument('name')) as $filename) {
            $table->addRow([$filename]);
        }
        $table->render();
    }
}
