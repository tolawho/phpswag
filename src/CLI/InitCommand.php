<?php

namespace PhpSwag\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;

class InitCommand extends Command
{
    protected static $defaultName = 'init';

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize a new phpswag configuration file (phpswag.yaml)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $configFile = 'phpswag.yaml';

        if (file_exists($configFile)) {
            $question = new ConfirmationQuestion(
                sprintf('<question>File "%s" already exists. Overwrite?</question> (y/N): ', $configFile),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Initialization cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $output->writeln([
            '',
            '==================================================',
            '       phpswag Configuration Wizard               ',
            '==================================================',
            '',
        ]);

        // 1. Paths to scan
        $pathsQuestion = new Question(
            '<question>Enter path(s) to scan for annotations (comma-separated)</question> [src]: ',
            'src'
        );
        $pathsStr = $helper->ask($input, $output, $pathsQuestion);
        $paths = array_map('trim', explode(',', $pathsStr));

        // 2. OpenAPI Version
        $versionQuestion = new ChoiceQuestion(
            '<question>Select OpenAPI version</question> [3.0.0]:',
            ['3.0.0', '3.1.0'],
            0
        );
        $openapiVersion = $helper->ask($input, $output, $versionQuestion);

        // 3. Output format
        $formatQuestion = new ChoiceQuestion(
            '<question>Select default output format</question> [yaml]:',
            ['yaml', 'json'],
            0
        );
        $format = $helper->ask($input, $output, $formatQuestion);

        // 4. Output destination path
        $defaultOutput = 'swagger.' . $format;
        $outputQuestion = new Question(
            sprintf('<question>Enter output destination file path</question> [%s]: ', $defaultOutput),
            $defaultOutput
        );
        $outputPath = $helper->ask($input, $output, $outputQuestion);

        // 5. Filter unused schemas
        $filterQuestion = new ConfirmationQuestion(
            '<question>Filter out unused schemas from the generated documentation?</question> (Y/n): ',
            true
        );
        $filterUnused = $helper->ask($input, $output, $filterQuestion);

        // 6. Enable caching
        $cacheQuestion = new ConfirmationQuestion(
            '<question>Enable caching to speed up subsequent generations?</question> (y/N): ',
            false
        );
        $cache = $helper->ask($input, $output, $cacheQuestion);

        $config = [
            'paths' => $paths,
            'openapi_version' => $openapiVersion,
            'format' => $format,
            'output' => $outputPath,
            'filter_unused' => $filterUnused,
            'cache' => $cache,
        ];

        if ($cache) {
            $config['cache_file'] = './.phpswag-cache';
        }

        file_put_contents($configFile, Yaml::dump($config, 4, 2));

        $output->writeln([
            '',
            sprintf('<info>Successfully created configuration file: %s</info>', $configFile),
            'You can now run <comment>phpswag generate</comment> without any arguments to generate documentation.',
            '',
        ]);

        return Command::SUCCESS;
    }
}
