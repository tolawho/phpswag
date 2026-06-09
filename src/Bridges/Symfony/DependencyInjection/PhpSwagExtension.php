<?php

namespace PhpSwag\Bridges\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class PhpSwagExtension extends Extension
{
    /**
     * Loads a specific configuration.
     *
     * @param array<mixed> $configs
     * @param ContainerBuilder $container
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('phpswag.paths', $config['paths']);
        $container->setParameter('phpswag.output', $config['output']);
        $container->setParameter('phpswag.format', $config['format']);
        $container->setParameter('phpswag.title', $config['title']);
        $container->setParameter('phpswag.version', $config['version']);
        $container->setParameter('phpswag.description', $config['description']);
        $container->setParameter('phpswag.host', $config['host']);
        $container->setParameter('phpswag.servers', $config['servers'] ?? []);
        $container->setParameter('phpswag.contact', $config['contact'] ?? []);
        $container->setParameter('phpswag.license', $config['license'] ?? []);
        $container->setParameter('phpswag.cache', $config['cache']);
        $container->setParameter('phpswag.cache_file', $config['cache_file']);
    }
}
