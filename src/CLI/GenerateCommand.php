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
            ->addOption(
                'openapi-version',
                null,
                InputOption::VALUE_REQUIRED,
                'OpenAPI version (3.0.0 or 3.1.0)',
                '3.0.0'
            )
            ->addOption(
                'filter-unused',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter unused schemas (true or false)',
                'true'
            )
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'API Title')
            ->addOption('api-version', null, InputOption::VALUE_REQUIRED, 'API Version')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'API Description')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'API Host/Server URL')
            ->addOption('cache', null, InputOption::VALUE_NONE, 'Enable caching to speed up generation')
            ->addOption('cache-file', null, InputOption::VALUE_REQUIRED, 'Cache file path', './.phpswag-cache')
            ->addOption('validate', null, InputOption::VALUE_NONE, 'Validate the generated OpenAPI specification');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getOption('path');
        $configFile = 'phpswag.yaml';
        $config = [];
        if (file_exists($configFile)) {
            try {
                $config = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
                if (!is_array($config)) {
                    $config = [];
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    '<error>Error parsing configuration file "%s": %s</error>',
                    $configFile,
                    $e->getMessage()
                ));
                return Command::FAILURE;
            }
        }

        if (empty($paths)) {
            $paths = $config['paths'] ?? [];
        }

        if (empty($paths)) {
            $output->writeln('<error>At least one --path is required or defined in phpswag.yaml.</error>');
            return Command::FAILURE;
        }

        try {
            $core = Core::createDefault();

            // OpenAPI Version
            $openapiVersion = $input->getOption('openapi-version');
            if ($openapiVersion === '3.0.0' && isset($config['openapi_version'])) {
                $openapiVersion = $config['openapi_version'];
            }
            $core->setOpenApiVersion($openapiVersion);

            // Filter unused schemas
            $filterUnusedOption = $input->getOption('filter-unused');
            if ($filterUnusedOption === 'true' && isset($config['filter_unused'])) {
                $filterUnused = (bool)$config['filter_unused'];
            } else {
                $filterUnused = filter_var($filterUnusedOption, FILTER_VALIDATE_BOOLEAN);
            }
            $core->setFilterUnusedSchemas($filterUnused);

            // Cache
            $enableCache = $input->getOption('cache');
            if ($enableCache === false && isset($config['cache'])) {
                $enableCache = (bool)$config['cache'];
            }
            if ($enableCache) {
                $cacheFile = $input->getOption('cache-file');
                if ($cacheFile === './.phpswag-cache' && isset($config['cache_file'])) {
                    $cacheFile = $config['cache_file'];
                }
                $core->enableCache($cacheFile ?: './.phpswag-cache');
            }

            if ($title = $input->getOption('title')) {
                $core->setTitle($title);
            }
            if ($apiVersion = $input->getOption('api-version')) {
                $core->setApiVersion($apiVersion);
            }
            if ($description = $input->getOption('description')) {
                $core->setDescription($description);
            }
            if ($host = $input->getOption('host')) {
                $core->setServers([['url' => $host]]);
            }

            // Validate if requested
            if ($input->getOption('validate')) {
                $specArray = $core->generateSpecArray($paths);
                $validator = new \PhpSwag\Validation\Validator();
                $errors = $validator->validate($specArray);
                if (!empty($errors)) {
                    $output->writeln('<error> ❌ OpenAPI Validation Failed: </error>');
                    foreach ($errors as $error) {
                        $output->writeln(sprintf('<error> - %s </error>', $error));
                    }
                    return Command::FAILURE;
                }
                $output->writeln('<info> ✅ OpenAPI Validation Passed! </info>');
            }

            // Format
            $format = strtolower($input->getOption('format'));
            if ($format === 'yaml' && isset($config['format'])) {
                $format = strtolower($config['format']);
            }

            if ($format === 'json') {
                $result = $core->generateJson($paths);
            } else {
                $result = $core->generateYaml($paths);
            }

            // Output destination
            $outputPath = $input->getOption('output');
            if ($outputPath === null && isset($config['output'])) {
                $outputPath = $config['output'];
            }

            if ($outputPath) {
                file_put_contents($outputPath, $result);
                $output->writeln(sprintf('<info>Documentation generated to %s</info>', $outputPath));
            } else {
                $output->write($result);
            }

            return Command::SUCCESS;
        } catch (\PhpSwag\Exception\DiagnosticException $e) {
            $output->writeln('');
            $output->writeln('<error> ❌ Lỗi Phân Tích (Analysis Error) </error>');
            $output->writeln(sprintf('<error> %s </error>', $e->getMessage()));
            if ($e->getFilePath()) {
                $realPath = realpath($e->getFilePath()) ?: $e->getFilePath();
                $link = 'file://' . $realPath;
                if ($e->getLineNumber()) {
                    $link .= '#L' . $e->getLineNumber();
                    $output->writeln(sprintf(
                        '<comment>Vị trí lỗi:</comment> <href=%s>%s:%d</>',
                        $link,
                        $realPath,
                        $e->getLineNumber()
                    ));
                } else {
                    $output->writeln(sprintf(
                        '<comment>Vị trí lỗi:</comment> <href=%s>%s</>',
                        $link,
                        $realPath
                    ));
                }
            }
            $output->writeln('');
            return Command::FAILURE;
        }
    }
}
