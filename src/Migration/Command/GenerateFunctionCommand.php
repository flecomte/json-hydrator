<?php

namespace FLE\JsonHydrator\Migration\Command;

use FLE\JsonHydrator\Migration\MigrationFunctions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFunctionCommand extends Command
{
    protected static $defaultName = 'migration:generate:function';

    /**
     * @var MigrationFunctions
     */
    private $migrationFunctions;

    /**
     * GenerateFileCommand constructor.
     *
     * @param MigrationFunctions $migrationFunctions
     */
    public function __construct(MigrationFunctions $migrationFunctions)
    {
        parent::__construct();
        $this->migrationFunctions = $migrationFunctions;
    }

    protected function configure()
    {
        $this->setDescription('Generate the blank function with the right name.')
            ->addArgument('name', InputArgument::REQUIRED, 'Add name to the generated function');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaderTitle('Generated files');
        $table->setHeaders(['Filename']);
        foreach ($this->migrationFunctions->generateFile($input->getArgument('name')) as $filename) {
            $table->addRow([$filename]);
        }
        $table->render();
    }
}
