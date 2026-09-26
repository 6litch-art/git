<?php

namespace Git\DependencyInjection;

use Git\Repository\RepositoryProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class GitExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/Resources/config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('git.route_prefix', $config['route_prefix']);
        $container->setParameter('git.access_role', $config['access_role']);
        $container->setParameter('git.repository_attribute', $config['repository_attribute']);

        $container->registerForAutoconfiguration(RepositoryProviderInterface::class)
            ->addTag('git.repository_provider');
        $container->setParameter('git.repositories', $config['repositories']);
    }
}
