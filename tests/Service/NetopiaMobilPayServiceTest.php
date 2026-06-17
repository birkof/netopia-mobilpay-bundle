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

namespace birkof\NetopiaMobilPay\Tests\Service;

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayService;
use Mobilpay\Payment\Address;
use Mobilpay\Payment\Instrument\Card as CardInstrument;
use Mobilpay\Payment\Request\Card as CardRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

final class NetopiaMobilPayServiceTest extends TestCase
{
    /**
     * Fields the Mobilpay\Payment\Address class does NOT support and which must
     * never be set by the bundle (they are silently dropped by the library and
     * trigger dynamic-property deprecations on PHP 8.2+).
     *
     * @var string[]
     */
    private const UNSUPPORTED_ADDRESS_FIELDS = [
        'fiscalNumber',
        'identityNumber',
        'country',
        'county',
        'city',
        'zipCode',
        'bank',
        'iban',
    ];

    private function createService(): NetopiaMobilPayService
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => 'https://example.test/'.$name
        );

        return new NetopiaMobilPayService(
            new NetopiaMobilPayConfiguration($router),
            $router,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function invokeComposeAddress(NetopiaMobilPayService $service, array $address): Address
    {
        $method = new \ReflectionMethod($service, 'composeAddressObject');
        $method->setAccessible(true);

        return $method->invoke($service, $address);
    }

    public function testComposeAddressObjectSetsOnlyLibrarySupportedFields(): void
    {
        $address = $this->invokeComposeAddress($this->createService(), [
            'type'        => 'person',
            'firstName'   => 'Ion',
            'lastName'    => 'Popescu',
            'address'     => 'Str. Exemplu 1',
            'email'       => 'ion@example.test',
            'mobilePhone' => '0700000000',
            // Unsupported keys that must be ignored.
            'fiscalNumber'   => 'RO12345',
            'identityNumber' => 'XS999999',
            'country'        => 'Romania',
            'county'         => 'Bucuresti',
            'city'           => 'Bucuresti',
            'zipCode'        => '010101',
            'bank'           => 'Test Bank',
            'iban'           => 'RO49AAAA1B31007593840000',
        ]);

        self::assertSame('person', $address->type);
        self::assertSame('Ion', $address->firstName);
        self::assertSame('Popescu', $address->lastName);
        self::assertSame('Str. Exemplu 1', $address->address);
        self::assertSame('ion@example.test', $address->email);
        self::assertSame('0700000000', $address->mobilePhone);

        foreach (self::UNSUPPORTED_ADDRESS_FIELDS as $field) {
            self::assertFalse(
                property_exists($address, $field),
                sprintf('Address must not carry the unsupported property "%s".', $field)
            );
        }
    }

    public function testComposedAddressXmlOmitsUnsupportedFields(): void
    {
        $address = $this->invokeComposeAddress($this->createService(), [
            'type'         => 'person',
            'firstName'    => 'Ion',
            'address'      => 'Str. Exemplu 1',
            'fiscalNumber' => 'RO12345',
            'zipCode'      => '010101',
            'iban'         => 'RO49AAAA1B31007593840000',
        ]);

        $doc = new \DOMDocument();
        $xml = $doc->saveXML($address->createXmlElement($doc, 'billing'));

        self::assertStringContainsString('<first_name>', $xml);
        self::assertStringContainsString('Ion', $xml);

        // Dropped field names and values must not reach the payment XML.
        self::assertStringNotContainsString('fiscalNumber', $xml);
        self::assertStringNotContainsString('RO12345', $xml);
        self::assertStringNotContainsString('010101', $xml);
        self::assertStringNotContainsString('RO49AAAA1B31007593840000', $xml);
    }

    public function testComposeCreditCardObjectThrowsWhenNameMissing(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'composeCreditCardObject');
        $method->setAccessible(true);

        $this->expectException(NetopiaMobilPayException::class);

        $method->invoke($service, ['number' => '4111111111111111']);
    }

    public function testComposeCreditCardObjectMapsFields(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'composeCreditCardObject');
        $method->setAccessible(true);

        /** @var CardInstrument $card */
        $card = $method->invoke($service, [
            'number'   => '4111111111111111',
            'expYear'  => '2030',
            'expMonth' => '12',
            'cvv2'     => '123',
            'name'     => 'ION POPESCU',
        ]);

        self::assertSame('4111111111111111', $card->number);
        self::assertSame('2030', $card->expYear);
        self::assertSame('12', $card->expMonth);
        self::assertSame('123', $card->cvv2);
        self::assertSame('ION POPESCU', $card->name);
    }

    public function testCreateCreditCardPaymentObjectEncryptsRequest(): void
    {
        $service = $this->createService();
        $service->getMobilPayConfiguration()
            ->setProjectDir(sys_get_temp_dir())
            ->setSignature('AAAA-BBBB-CCCC-DDDD-EEEE')
            ->setPaymentUrl('https://sandboxsecure.mobilpay.ro')
            ->setPublicCert($this->generateSelfSignedCertificate());

        $request = $service->createCreditCardPaymentObject(
            'ORDER-001',
            '49.99',
            NetopiaMobilPayConfiguration::CURRENCY_RON,
            'Test order',
            [],
            [],
            [
                'number'   => '4111111111111111',
                'expYear'  => '2030',
                'expMonth' => '12',
                'cvv2'     => '123',
                'name'     => 'ION POPESCU',
            ]
        );

        self::assertInstanceOf(CardRequest::class, $request);
        self::assertSame('ORDER-001', $request->orderId);
        self::assertNotEmpty($request->getEncData(), 'Encrypted payload must be produced.');
        self::assertNotEmpty($request->getEnvKey(), 'Sealed envelope key must be produced.');
        self::assertNotEmpty($request->getCipher(), 'OpenSSL seal cipher must be recorded.');
    }

    public function testCreateCreditCardPaymentObjectRejectsEmptyOrderId(): void
    {
        $this->expectException(NetopiaMobilPayException::class);

        $this->createService()->createCreditCardPaymentObject('', '49.99');
    }

    public function testCreateCreditCardPaymentObjectRejectsNonPositiveAmount(): void
    {
        $this->expectException(NetopiaMobilPayException::class);

        $this->createService()->createCreditCardPaymentObject('ORDER-001', '0');
    }

    public function testCreateCreditCardPaymentObjectRejectsUnsupportedCurrency(): void
    {
        $this->expectException(NetopiaMobilPayException::class);

        $this->createService()->createCreditCardPaymentObject('ORDER-001', '49.99', 'GBP');
    }

    private function generateSelfSignedCertificate(): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey, 'Unable to generate an RSA key for the test.');

        $csr  = openssl_csr_new(['commonName' => 'netopia-test'], $privateKey);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 1);
        openssl_x509_export($x509, $certificate);

        return $certificate;
    }
}
