<?php

namespace Git\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->treeBuilder = new TreeBuilder('git');

        $rootNode = $this->treeBuilder->getRootNode();
        $this->addGlobalOptionsSection($rootNode);

        return $this->treeBuilder;
    }

    private $treeBuilder;
    public function getTreeBuilder(): TreeBuilder
    {
        return $this->treeBuilder;
    }

    private function addGlobalOptionsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode; // todo..
    }
}
