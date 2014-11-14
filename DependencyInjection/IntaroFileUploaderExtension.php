<?php

namespace Intaro\FileUploaderBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class IntaroFileUploaderExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        $gaufretteConfig = $this->generateGaufretteConfig($config);
        $container->prependExtensionConfig('knp_gaufrette', $gaufretteConfig);

        foreach ($config['uploaders'] as $uploaderType => $uploaders) {
            foreach ($uploaders as $name => $options) {
                if($uploaderType === 'local'){
                    $directory = $options['directory'];
                } elseif ($uploaderType === 'aws_s3') {
                    $directory = $options['options']['directory'];
                }
                $container->setDefinition("intaro.{$name}_uploader",
                    new Definition(
                    '%intaro_file_uploader.class%',
                    [
                        new Reference("gaufrette.{$name}_filesystem"),
                        new Reference("router"),
                        $directory,
                        '%kernel.root_dir%/../web',
                        $options['allowed_types']
                    ]
                ));
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    protected function generateGaufretteConfig($config)
    {
        $filesystems = [];
        $adapters = [];
        foreach (array_filter($config['uploaders']) as $uploaderType => $uploaders) {
            foreach ($uploaders as $name => $options) {
                unset($options['allowed_types']);
                $filesystems[$name] = [
                    'adapter' => $name
                ];
                $adapters[$name] = [
                    $uploaderType => $options
                ];
            }
        }
        $gaufretteConfig = [
            'adapters' => $adapters,
            'filesystems' => $filesystems
        ];

        return $gaufretteConfig;
    }
}
