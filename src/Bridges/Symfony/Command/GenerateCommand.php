<?php

namespace PhpSwag\Bridges\Symfony\Command;

use PhpSwag\Core;
use PhpSwag\Validation\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateCommand extends Command
{
    protected static $defaultName = 'phpswag:generate';
    private ParameterBagInterface $parameterBag;

    /**
     * GenerateCommand constructor.
     *
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        parent::__construct();
        $this->parameterBag = $parameterBag;
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('phpswag:generate')
            ->setDescription('Generate OpenAPI documentation from PHP source code')
            ->addOption('validate', null, InputOption::VALUE_NONE, 'Validate the generated specification');
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->parameterBag->has('phpswag.paths')) {
            $output->writeln('<error>phpswag configuration not loaded.</error>');
            return Command::FAILURE;
        }

        /** @var array<int, string> $paths */
        $paths = $this->parameterBag->get('phpswag.paths');
        /** @var string $outputPath */
        $outputPath = $this->parameterBag->get('phpswag.output');
        /** @var string $formatVal */
        $formatVal = $this->parameterBag->get('phpswag.format');
        $format = strtolower($formatVal);

        if (empty($paths)) {
            $output->writeln('<error>No paths defined in phpswag configuration.</error>');
            return Command::FAILURE;
        }

        try {
            $core = Core::createDefault();

            // Set metadata from configuration if defined
            if ($this->parameterBag->has('phpswag.title')) {
                /** @var string $title */
                $title = $this->parameterBag->get('phpswag.title');
                $core->setTitle($title);
            }
            if ($this->parameterBag->has('phpswag.version')) {
                /** @var string $version */
                $version = $this->parameterBag->get('phpswag.version');
                $core->setApiVersion($version);
            }
            if ($this->parameterBag->has('phpswag.description')) {
                /** @var string|null $description */
                $description = $this->parameterBag->get('phpswag.description');
                $core->setDescription($description);
            }
            if ($this->parameterBag->has('phpswag.servers')) {
                $servers = $this->parameterBag->get('phpswag.servers');
                if (is_array($servers) && !empty($servers)) {
                    /** @var array<int, array<string, mixed>> $servers */
                    $core->setServers($servers);
                }
            } elseif ($this->parameterBag->has('phpswag.host')) {
                /** @var string $host */
                $host = $this->parameterBag->get('phpswag.host');
                $core->setServers([['url' => $host]]);
            }
            if ($this->parameterBag->has('phpswag.contact')) {
                $contact = $this->parameterBag->get('phpswag.contact');
                if (is_array($contact) && !empty($contact)) {
                    /** @var array<string, mixed> $contact */
                    $core->setContact($contact);
                }
            }
            if ($this->parameterBag->has('phpswag.license')) {
                $license = $this->parameterBag->get('phpswag.license');
                if (is_array($license) && !empty($license)) {
                    /** @var array<string, mixed> $license */
                    $core->setLicense($license);
                }
            }

            // Set cache if enabled
            if ($this->parameterBag->get('phpswag.cache')) {
                /** @var string $cacheFile */
                $cacheFile = $this->parameterBag->get('phpswag.cache_file');
                $core->enableCache($cacheFile);
            }

            // Validate if requested
            if ($input->getOption('validate')) {
                $specArray = $core->generateSpecArray($paths);
                $validator = new Validator();
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

            // Generate specification
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
            $output->writeln(sprintf('<info>Documentation generated successfully to %s</info>', $outputPath));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error generating documentation: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
