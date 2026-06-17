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

namespace birkof\NetopiaMobilPay\Tests\Notification;

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use birkof\NetopiaMobilPay\Notification\IpnAction;
use birkof\NetopiaMobilPay\Notification\NetopiaMobilPayIpnHandler;
use birkof\NetopiaMobilPay\Notification\NetopiaMobilPayIpnHandlerInterface;
use Mobilpay\MobilpayGlobal;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;

final class NetopiaMobilPayIpnHandlerTest extends TestCase
{
    private function createHandler(string $privateKeyPem): NetopiaMobilPayIpnHandler
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => 'https://example.test/'.$name
        );

        $configuration = (new NetopiaMobilPayConfiguration($router))
            ->setProjectDir(sys_get_temp_dir());
        $configuration->setPrivateKey($privateKeyPem);

        return new NetopiaMobilPayIpnHandler($configuration, new NullLogger());
    }

    /**
     * @return array{private: string, cert: string}
     */
    private function generateKeyPair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey, 'Unable to generate an RSA key for the test.');

        $csr  = openssl_csr_new(['commonName' => 'netopia-test'], $privateKey);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 1);
        openssl_x509_export($x509, $certificate);
        openssl_pkey_export($privateKey, $privateKeyPem);

        return ['private' => $privateKeyPem, 'cert' => $certificate];
    }

    /**
     * @return array{envKey: string, data: string, cipher: string, iv: ?string}
     */
    private function sealIpn(string $certificate, string $action = 'confirmed'): array
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<order type="sms" id="ORDER-001">
    <signature>AAAA-BBBB-CCCC-DDDD-EEEE</signature>
    <service>netopia-test-service</service>
    <mobilpay timestamp="20260616120000" crc="dummycrc">
        <action>{$action}</action>
        <purchase>123</purchase>
        <original_amount>49.99</original_amount>
        <processed_amount>49.99</processed_amount>
        <error code="0"></error>
    </mobilpay>
</order>
XML;

        $publicKey = openssl_pkey_get_public($certificate);
        self::assertNotFalse($publicKey);

        $cipher  = MobilpayGlobal::getSealCipher();
        $iv      = null;
        $sealed  = null;
        $envKeys = null;
        $result  = openssl_seal($xml, $sealed, $envKeys, [$publicKey], $cipher, $iv);
        self::assertNotFalse($result, 'openssl_seal failed.');

        return [
            'envKey' => base64_encode($envKeys[0]),
            'data'   => base64_encode($sealed),
            'cipher' => $cipher,
            'iv'     => $iv !== null ? base64_encode($iv) : null,
        ];
    }

    public function testDecryptRoundTripExposesNotificationData(): void
    {
        $keys    = $this->generateKeyPair();
        $sealed  = $this->sealIpn($keys['cert']);
        $handler = $this->createHandler($keys['private']);

        $result = $handler->decrypt($sealed['envKey'], $sealed['data'], $sealed['cipher'], $sealed['iv']);

        self::assertSame(IpnAction::Confirmed, $result->action);
        self::assertSame(0, $result->errorCode);
        self::assertSame('123', $result->purchaseId);
        self::assertSame('49.99', $result->processedAmount);
        self::assertTrue($result->isConfirmed());
    }

    public function testDecryptThrowsOnEmptyPayload(): void
    {
        $handler = $this->createHandler($this->generateKeyPair()['private']);

        $this->expectException(NetopiaMobilPayException::class);

        $handler->decrypt('', '');
    }

    public function testDecryptThrowsOnGarbagePayload(): void
    {
        $keys    = $this->generateKeyPair();
        $handler = $this->createHandler($keys['private']);

        $this->expectException(NetopiaMobilPayException::class);

        $handler->decrypt('not-a-real-env-key', 'not-real-data', MobilpayGlobal::getSealCipher(), null);
    }

    public function testConfirmResponseIsEmptyCrcXml(): void
    {
        $handler = $this->createHandler($this->generateKeyPair()['private']);

        $xml = $handler->confirmResponse();

        self::assertStringContainsString('<?xml', $xml);
        self::assertStringContainsString('<crc/>', $xml);
        self::assertStringNotContainsString('error_type', $xml);
    }

    public function testErrorResponseSetsTypeCodeAndEscapesMessage(): void
    {
        $handler = $this->createHandler($this->generateKeyPair()['private']);

        $xml = $handler->errorResponse(
            'bad <order> & "quote"',
            NetopiaMobilPayIpnHandlerInterface::ERROR_TYPE_PERMANENT,
            0x10
        );

        self::assertStringContainsString('error_type="2"', $xml);
        self::assertStringContainsString('error_code="16"', $xml);
        // Message must be XML-escaped, never injected as raw markup.
        self::assertStringContainsString('&lt;order&gt;', $xml);
        self::assertStringNotContainsString('<order>', $xml);
    }

    public function testErrorResponseDefaultsToPermanentType(): void
    {
        $handler = $this->createHandler($this->generateKeyPair()['private']);

        $xml = $handler->errorResponse('nope');

        self::assertStringContainsString('error_type="2"', $xml);
    }
}
