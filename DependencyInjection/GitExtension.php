<?php

namespace Git\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\Definition\Processor;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class GitExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        // Format XML
        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__, 1).'/Resources/config'));
        $loader->load('services.xml');

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());
    }

    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = "")
    {
        foreach ($config as $key => $value) {
            if (!empty($globalKey)) {
                $key = $globalKey.".".$key;
            }

            if (is_array($value)) {
                $this->setConfiguration($container, $value, $key);
            } else {
                $container->setParameter($key, $value);
            }
        }
    }
}
