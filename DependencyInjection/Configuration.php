<?php

namespace Intaro\FileUploaderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('intaro_file_uploader');
        if (\method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            // @phpstan-ignore-next-line
            $rootNode = $treeBuilder->root('intaro_file_uploader');
        }

        $rootNode
            ->children()
                ->arrayNode('uploaders')
                    ->children()
                        ->arrayNode('local')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->arrayNode('allowed_types')
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode('directory')->end()
                                    ->scalarNode('path')->end()
                                    ->scalarNode('create')->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('aws_s3')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->children()
                                    ->arrayNode('allowed_types')
                                        ->prototype('scalar')->end()
                                    ->end()
                                    ->scalarNode('bucket_name')->end()
                                    ->scalarNode('service_id')->end()
                                    ->scalarNode('path')->end()
                                    ->arrayNode('options')
                                        ->children()
                                            ->scalarNode('directory')->end()
                                            ->scalarNode('create')->end()
                                            ->scalarNode('acl')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
