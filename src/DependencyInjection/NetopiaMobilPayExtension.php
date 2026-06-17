<?php

declare(strict_types=1);

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
use birkof\NetopiaMobilPay\Notification\NetopiaMobilPayIpnHandler;
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

        // Services definition with configurations.
        // NOTE: secrets (private_key, signature) are intentionally NOT exposed as
        // container parameters — Symfony dumps those to the compiled container
        // cache in cleartext. They are passed straight to the configuration
        // service via method calls in injectAndConfigureServices() instead.
        $this->injectAndConfigureServices($container, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias(): string
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
    private function injectAndConfigureServices(ContainerBuilder $container, array $config)
    {
        $configurationId = sprintf('%s.configuration', NetopiaMobilPayBundle::ALIAS);

        // Register the configuration ONCE as a private shared service so both the
        // payment service and the IPN handler consume the same instance.
        $configurationDefinition = (new Definition(NetopiaMobilPayConfiguration::class))
            ->addArgument(new Reference('router'))
            ->addMethodCall('setPaymentUrl', [$config['payment_url']])
            ->addMethodCall('setProjectDir', ['%kernel.project_dir%'])
            ->addMethodCall('setPublicCert', [$config['public_cert']])
            ->addMethodCall('setPrivateKey', [$config['private_key']])
            ->addMethodCall('setSignature', [$config['signature']])
            ->setPublic(false);

        $container->setDefinition($configurationId, $configurationDefinition);

        $paymentServiceDefinition = (new Definition(NetopiaMobilPayService::class))
            ->addArgument(new Reference($configurationId))
            ->addArgument(new Reference('router'))
            ->addArgument(new Reference('logger'))
            ->setPublic(true);

        $container->setDefinition(sprintf('%s.payment', NetopiaMobilPayBundle::ALIAS), $paymentServiceDefinition);

        $ipnHandlerDefinition = (new Definition(NetopiaMobilPayIpnHandler::class))
            ->addArgument(new Reference($configurationId))
            ->addArgument(new Reference('logger'))
            ->setPublic(true);

        $container->setDefinition(sprintf('%s.ipn_handler', NetopiaMobilPayBundle::ALIAS), $ipnHandlerDefinition);
    }
}
