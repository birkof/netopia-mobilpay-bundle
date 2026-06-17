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

namespace birkof\NetopiaMobilPay\Tests\DependencyInjection;

use birkof\NetopiaMobilPay\DependencyInjection\NetopiaMobilPayExtension;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayService;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouterInterface;

final class NetopiaMobilPayExtensionTest extends TestCase
{
    public function testGetAliasMatchesBundleAlias(): void
    {
        self::assertSame('netopia_mobilpay', (new NetopiaMobilPayExtension())->getAlias());
    }

    public function testLoadRegistersServiceDefinitionAndAliasWithoutExposingSecrets(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');

        (new NetopiaMobilPayExtension())->load([], $container);

        // Secrets (and other config) must NOT be exposed as container parameters:
        // Symfony dumps the parameter bag to the compiled container cache in
        // cleartext. Configuration is passed to the service via method calls instead.
        self::assertFalse($container->hasParameter('netopia_mobilpay.private_key'));
        self::assertFalse($container->hasParameter('netopia_mobilpay.signature'));
        self::assertFalse($container->hasParameter('netopia_mobilpay.public_cert'));
        self::assertFalse($container->hasParameter('netopia_mobilpay.payment_url'));

        // Public payment service definition.
        self::assertTrue($container->hasDefinition('netopia_mobilpay.payment'));
        $definition = $container->getDefinition('netopia_mobilpay.payment');
        self::assertSame(NetopiaMobilPayService::class, $definition->getClass());
        self::assertTrue($definition->isPublic());

        // Interface alias declared in services.yaml.
        self::assertTrue($container->hasAlias(NetopiaMobilPayServiceInterface::class));
        self::assertSame(
            'netopia_mobilpay.payment',
            (string) $container->getAlias(NetopiaMobilPayServiceInterface::class)
        );
    }

    public function testCompiledContainerExposesConfiguredPaymentService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('router', RouterInterface::class)->setSynthetic(true)->setPublic(true);
        $container->register('logger', LoggerInterface::class)->setSynthetic(true)->setPublic(true);

        (new NetopiaMobilPayExtension())->load([
            [
                'payment_url' => 'https://secure.mobilpay.ro',
                'public_cert' => 'INLINE-PUBLIC-CERT',
                'private_key' => 'INLINE-PRIVATE-KEY',
                'signature'   => 'AAAA-BBBB-CCCC-DDDD-EEEE',
            ],
        ], $container);

        $container->compile();

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => 'https://example.test/'.$name
        );
        $container->set('router', $router);
        $container->set('logger', new NullLogger());

        $service = $container->get('netopia_mobilpay.payment');

        self::assertInstanceOf(NetopiaMobilPayService::class, $service);

        $configuration = $service->getMobilPayConfiguration();
        self::assertSame('https://secure.mobilpay.ro', $configuration->getPaymentUrl());
        self::assertSame('AAAA-BBBB-CCCC-DDDD-EEEE', $configuration->getSignature());
        self::assertSame('INLINE-PUBLIC-CERT', $configuration->getPublicCert());
    }
}
