<?php

namespace PhpSwag\CLI;

use PhpSwag\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WatchCommand extends Command
{
    protected static $defaultName = 'watch';

    protected function configure(): void
    {
        $this
            ->setName('watch')
            ->setDescription('Start a live preview server and watch for file changes')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Path(s) to scan')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: swagger.yaml)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (yaml or json)', 'yaml')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host for the server', 'localhost')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port for the server', '8080');
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

        // Output destination
        $outputPath = $input->getOption('output');
        if ($outputPath === null) {
            $outputPath = $config['output'] ?? 'swagger.yaml';
        }

        // Format
        $format = strtolower($input->getOption('format'));
        if ($format === 'yaml' && isset($config['format'])) {
            $format = strtolower($config['format']);
        }

        $host = $input->getOption('host');
        if ($host === 'localhost' && isset($config['watch_host'])) {
            $host = $config['watch_host'];
        }

        $port = $input->getOption('port');
        if ($port === '8080' && isset($config['watch_port'])) {
            $port = (int)$config['watch_port'];
        } else {
            $port = (int)$port;
        }

        // Temp change file for SSE signaling
        $changeFile = tempnam(sys_get_temp_dir(), 'phpswag_changed_');
        if ($changeFile === false) {
            $changeFile = __DIR__ . '/../../.phpswag-changed';
        }
        touch($changeFile);

        $output->writeln('<info>Generating initial specification...</info>');
        $this->generateSpec($paths, $outputPath, $format, $config, $output);

        // Start Built-in PHP Web Server
        $routerPath = __DIR__ . '/router.php';
        $cmd = sprintf(
            '%s -S %s:%d %s',
            escapeshellarg(PHP_BINARY),
            $host,
            $port,
            escapeshellarg($routerPath)
        );

        $env = array_merge($_ENV, [
            'PHPSWAG_SPEC_FILE' => realpath($outputPath) ?: $outputPath,
            'PHPSWAG_CHANGE_FILE' => realpath($changeFile) ?: $changeFile,
        ]);

        $output->writeln(sprintf('<info>Starting Live Preview Server on http://%s:%d...</info>', $host, $port));

        $devNull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['file', $devNull, 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $env);

        if (!is_resource($process)) {
            $output->writeln('<error>Failed to start PHP Built-in Server.</error>');
            return Command::FAILURE;
        }

        // Non-blocking pipes
        stream_set_blocking($pipes[2], false);

        $output->writeln('<info>Watcher started. Watching for changes... Press Ctrl+C to stop.</info>');

        $lastFiles = $this->getFiles($paths);

        try {
            while (true) {
                // Check if process is still running
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $output->writeln('<error>PHP Server stopped unexpectedly.</error>');
                    $stderr = stream_get_contents($pipes[2]);
                    if (!empty($stderr)) {
                        $output->writeln('<error>Server Error Log:</error>');
                        $output->writeln(sprintf('<comment>%s</comment>', trim($stderr)));
                    }
                    break;
                }

                // Scan files for changes
                $currentFiles = $this->getFiles($paths);
                $hasChanged = false;

                if (count($currentFiles) !== count($lastFiles)) {
                    $hasChanged = true;
                } else {
                    foreach ($currentFiles as $file => $mtime) {
                        if (!isset($lastFiles[$file]) || $lastFiles[$file] !== $mtime) {
                            $hasChanged = true;
                            break;
                        }
                    }
                }

                if ($hasChanged) {
                    $output->writeln(sprintf(
                        '<comment>[%s] Change detected, regenerating spec...</comment>',
                        date('H:i:s')
                    ));
                    if ($this->generateSpec($paths, $outputPath, $format, $config, $output)) {
                        touch($changeFile);
                        $output->writeln('<info>Spec updated successfully.</info>');
                    }
                    $lastFiles = $currentFiles;
                }

                if (getenv('PHPSWAG_TEST_LOOP') === '1') {
                    break;
                }

                usleep(500000); // Sleep for 500ms
            }
        } finally {
            $output->writeln('<info>Cleaning up and shutting down...</info>');
            if (file_exists($changeFile)) {
                unlink($changeFile);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string> $paths
     * @param string $outputPath
     * @param string $format
     * @param array<string, mixed> $config
     * @param OutputInterface $output
     */
    private function generateSpec(
        array $paths,
        string $outputPath,
        string $format,
        array $config,
        OutputInterface $output
    ): bool {
        try {
            $core = Core::createDefault();

            // OpenAPI Version
            $openapiVersion = $config['openapi_version'] ?? '3.0.0';
            $core->setOpenApiVersion($openapiVersion);

            // Filter unused schemas
            $filterUnused = isset($config['filter_unused']) ? (bool)$config['filter_unused'] : true;
            $core->setFilterUnusedSchemas($filterUnused);

            // Cache
            if (isset($config['cache']) && $config['cache']) {
                $core->enableCache($config['cache_file'] ?? './.phpswag-cache');
            }

            if ($format === 'json') {
                $result = $core->generateJson($paths);
            } else {
                $result = $core->generateYaml($paths);
            }

            file_put_contents($outputPath, $result);
            return true;
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
            return false;
        }
    }

    /**
     * @param array<int, string> $paths
     * @return array<string, int>
     */
    private function getFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[realpath($path)] = filemtime($path);
            } elseif (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $files[$file->getRealPath()] = $file->getMTime();
                    }
                }
            }
        }
        return $files;
    }
}
