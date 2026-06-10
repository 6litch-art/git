<?php

namespace Git\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('git');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('route_prefix')
                    ->info('URL prefix for all git routes')
                    ->defaultValue('/git')
                ->end()
                ->scalarNode('access_role')
                    ->info('Role required to access the git viewer')
                    ->defaultValue('ROLE_ADMIN')
                ->end()
                ->arrayNode('repositories')
                    ->info('Named repositories to expose')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')
                                ->info('Absolute path to the git repository')
                                ->isRequired()
                            ->end()
                            ->scalarNode('label')
                                ->info('Human-readable name shown in the UI')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('description')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('default_branch')
                                ->defaultValue('HEAD')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
