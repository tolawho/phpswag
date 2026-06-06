<?php

namespace PhpSwag\CLI;

use PhpSwag\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    protected static $defaultName = 'generate';

    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate OpenAPI documentation from PHP source code')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Path(s) to scan')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: stdout)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (yaml or json)', 'yaml')
            ->addOption('openapi-version', null, InputOption::VALUE_REQUIRED, 'OpenAPI version (3.0.0 or 3.1.0)', '3.0.0')
            ->addOption('filter-unused', null, InputOption::VALUE_NONE, 'Filter unused schemas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getOption('path');
        if (empty($paths)) {
            $output->writeln('<error>At least one --path is required.</error>');
            return Command::FAILURE;
        }

        $core = new Core();
        $core->setOpenApiVersion($input->getOption('openapi-version'));
        $core->setFilterUnusedSchemas($input->getOption('filter-unused'));

        $format = strtolower($input->getOption('format'));
        if ($format === 'json') {
            $result = $core->generateJson($paths);
        } else {
            $result = $core->generateYaml($paths);
        }

        $outputPath = $input->getOption('output');
        if ($outputPath) {
            file_put_contents($outputPath, $result);
            $output->writeln(sprintf('<info>Documentation generated to %s</info>', $outputPath));
        } else {
            $output->write($result);
        }

        return Command::SUCCESS;
    }
}
