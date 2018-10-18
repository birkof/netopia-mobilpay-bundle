<?php
/*
 * This file is part of the NetopiaMobilPayBundle.
 *
 * (c) Daniel STANCU <birkof@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace birkof\NetopiaMobilPay\DependencyInjection;

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\NetopiaMobilPayBundle;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class NetopiaMobilPayExtension
 * @package birkof\NetopiaMobilPay\DependencyInjection
 */
class NetopiaMobilPayExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // Load bundle's services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // Process bundle's configurations
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->inflateServicesInConfig($config);
        $this->assignParametersToContainer($container, $config);

        // Services definition with configurations
        $this->injectAndConfigureServices($container, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return NetopiaMobilPayBundle::ALIAS;
    }

    /**
     * @param array $config
     */
    private function inflateServicesInConfig(array &$config)
    {
        array_walk(
            $config,
            function (&$value) {
                if (is_array($value)) {
                    $this->inflateServicesInConfig($value);
                }
                if (is_string($value) && 0 === strpos($value, '@')) {
                    // this is either a service reference or a string meant to
                    // start with an '@' symbol. In any case, lop off the first '@'
                    $value = substr($value, 1);
                    if (0 !== strpos($value, '@')) {
                        // this is a service reference, not a string literal
                        $value = new Reference($value);
                    }
                }
            }
        );
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $config
     */
    private function assignParametersToContainer(ContainerBuilder $container, array $config)
    {
        $container->setParameter(sprintf('%s.payment_url', NetopiaMobilPayBundle::ALIAS), $config['payment_url']);
        $container->setParameter(sprintf('%s.public_cert', NetopiaMobilPayBundle::ALIAS), $config['public_cert']);
        $container->setParameter(sprintf('%s.private_key', NetopiaMobilPayBundle::ALIAS), $config['private_key']);
        $container->setParameter(sprintf('%s.signature', NetopiaMobilPayBundle::ALIAS), $config['signature']);
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $config
     */
    private function injectAndConfigureServices(ContainerBuilder $container, array $config)
    {
        /** @var Definition $paymentConfigurationDefinition */
        $paymentConfigurationDefinition = (new Definition(NetopiaMobilPayConfiguration::class))
            ->addMethodCall('setPaymentUrl', [$config['payment_url']])
            ->addMethodCall('setProjectDir', ['%kernel.project_dir%'])
            ->addMethodCall('setPublicCert', [$config['public_cert']])
            ->addMethodCall('setPrivateKey', [$config['private_key']])
            ->addMethodCall('setSignature', [$config['signature']])
            ->addArgument(new Reference('router'))
            ->setPublic(false);

        /** @var Definition $paymentConfigurationDefinition */
        $paymentServiceDefinition = (new Definition(NetopiaMobilPayService::class))
            ->addArgument($paymentConfigurationDefinition)
            ->addArgument(new Reference('router'))
            ->addArgument(new Reference('logger'))
            ->setPublic(true);

        $container->setDefinition(sprintf('%s.payment', NetopiaMobilPayBundle::ALIAS), $paymentServiceDefinition);
    }
}
