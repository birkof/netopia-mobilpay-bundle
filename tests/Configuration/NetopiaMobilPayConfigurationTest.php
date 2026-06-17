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

namespace birkof\NetopiaMobilPay\Tests\Configuration;

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

final class NetopiaMobilPayConfigurationTest extends TestCase
{
    private function createConfiguration(): NetopiaMobilPayConfiguration
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => 'https://example.test/'.$name
        );

        return new NetopiaMobilPayConfiguration($router);
    }

    public function testConstructorGeneratesConfirmAndReturnUrls(): void
    {
        $config = $this->createConfiguration();

        self::assertSame(
            'https://example.test/'.NetopiaMobilPayConfiguration::CONFIRM_URL,
            $config->getConfirmUrl()
        );
        self::assertSame(
            'https://example.test/'.NetopiaMobilPayConfiguration::RETURN_URL,
            $config->getReturnUrl()
        );
    }

    public function testSetProjectDirAppendsTrailingSlash(): void
    {
        $config = $this->createConfiguration();

        self::assertSame($config, $config->setProjectDir('/var/www/app'));
        self::assertSame('/var/www/app/', $config->getProjectDir());
    }

    public function testScalarSettersAreFluentAndStoreValues(): void
    {
        $config = $this->createConfiguration();

        self::assertSame($config, $config->setPaymentUrl('https://secure.mobilpay.ro'));
        self::assertSame('https://secure.mobilpay.ro', $config->getPaymentUrl());

        self::assertSame($config, $config->setSignature('AAAA-BBBB-CCCC-DDDD-EEEE'));
        self::assertSame('AAAA-BBBB-CCCC-DDDD-EEEE', $config->getSignature());
    }

    public function testResolvePaymentUrlDerivesTokenEndpointFromBaseWithoutAccumulating(): void
    {
        $config = $this->createConfiguration();
        $config->setPaymentUrl('https://secure.mobilpay.ro');

        // Token payments use the dedicated /card4 endpoint.
        $config->resolvePaymentUrl(true);
        self::assertSame('https://secure.mobilpay.ro/card4', $config->getPaymentUrl());

        // Repeated token resolves must NOT stack "/card4/card4".
        $config->resolvePaymentUrl(true);
        self::assertSame('https://secure.mobilpay.ro/card4', $config->getPaymentUrl());

        // A subsequent non-token payment falls back to the base URL,
        // proving the previous "/card4" did not contaminate shared state.
        $config->resolvePaymentUrl(false);
        self::assertSame('https://secure.mobilpay.ro', $config->getPaymentUrl());
    }

    public function testSetPublicCertReadsFileContentsWhenPathExists(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cert');
        file_put_contents($file, 'PUBLIC-CERT-CONTENT');

        $config = $this->createConfiguration();
        $config->setProjectDir(dirname($file));
        $config->setPublicCert(basename($file));

        self::assertSame('PUBLIC-CERT-CONTENT', $config->getPublicCert());

        unlink($file);
    }

    public function testSetPublicCertKeepsInlineValueWhenNotAFile(): void
    {
        $inlineCert = '-----BEGIN CERTIFICATE-----INLINE-----END CERTIFICATE-----';

        $config = $this->createConfiguration();
        $config->setProjectDir(sys_get_temp_dir());
        $config->setPublicCert($inlineCert);

        self::assertSame($inlineCert, $config->getPublicCert());
    }

    public function testSetPrivateKeyReadsFileContentsWhenPathExists(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'key');
        file_put_contents($file, 'PRIVATE-KEY-CONTENT');

        $config = $this->createConfiguration();
        $config->setProjectDir(dirname($file));
        $config->setPrivateKey(basename($file));

        self::assertSame('PRIVATE-KEY-CONTENT', $config->getPrivateKey());

        unlink($file);
    }
}
