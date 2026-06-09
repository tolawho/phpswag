<?php

namespace PhpSwag\Bridges\Laravel\Commands;

use Illuminate\Console\Command;
use PhpSwag\Core;
use PhpSwag\Validation\Validator;

class GenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'phpswag:generate'
        . ' {--validate : Run validation on the generated spec}'
        . ' {--filter-unused= : Filter unused schemas (true or false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAPI documentation from PHP source code';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $config = config('phpswag');
        if (!is_array($config)) {
            $this->error('Laravel phpswag configuration not found. Did you publish it?');
            return Command::FAILURE;
        }

        $paths = $config['paths'] ?? [];
        if (empty($paths)) {
            $this->error('No paths defined in phpswag configuration.');
            return Command::FAILURE;
        }

        $outputPath = $config['output'] ?? public_path('swagger.yaml');
        $format = strtolower($config['format'] ?? 'yaml');

        try {
            $core = Core::createDefault();

            // Apply global metadata from config if not defined in code
            if (!empty($config['title'])) {
                $core->setTitle($config['title']);
            }
            if (!empty($config['version'])) {
                $core->setApiVersion($config['version']);
            }
            if (!empty($config['description'])) {
                $core->setDescription($config['description']);
            }
            if (!empty($config['servers'])) {
                $core->setServers($config['servers']);
            } elseif (!empty($config['host'])) {
                $core->setServers([['url' => $config['host']]]);
            }
            if (!empty($config['contact']) && is_array($config['contact'])) {
                $core->setContact($config['contact']);
            }
            if (!empty($config['license']) && is_array($config['license'])) {
                $core->setLicense($config['license']);
            }

            // Apply Cache configuration
            if (!empty($config['cache'])) {
                $cacheFile = $config['cache_file'] ?? storage_path('framework/cache/phpswag-cache');
                $core->enableCache($cacheFile);
            }

            // Apply filter-unused configuration
            $filterUnusedOption = $this->option('filter-unused');
            if ($filterUnusedOption === null || $filterUnusedOption === '') {
                $filterUnused = (bool)($config['filter_unused'] ?? true);
            } else {
                $filterUnused = filter_var($filterUnusedOption, FILTER_VALIDATE_BOOLEAN);
            }
            $core->setFilterUnusedSchemas($filterUnused);

            // Perform validation if flag is set
            if ($this->option('validate')) {
                $specArray = $core->generateSpecArray($paths);
                $validator = new Validator();
                $errors = $validator->validate($specArray);
                if (!empty($errors)) {
                    $this->error('❌ OpenAPI Validation Failed:');
                    foreach ($errors as $error) {
                        $this->line(" - $error");
                    }
                    return Command::FAILURE;
                }
                $this->info('✅ OpenAPI Validation Passed!');
            }

            // Generate output
            if ($format === 'json') {
                $result = $core->generateJson($paths);
            } else {
                $result = $core->generateYaml($paths);
            }

            // Write output file
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($outputPath, $result);
            $this->info("Documentation generated successfully to $outputPath");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error generating documentation: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
