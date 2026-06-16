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

use birkof\NetopiaMobilPay\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfigurationIsApplied(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame('http://sandboxsecure.mobilpay.ro', $processed['payment_url']);
        self::assertSame('XXXX-XXXX-XXXX-XXXX-XXXX', $processed['signature']);
        self::assertNull($processed['public_cert']);
        self::assertNull($processed['private_key']);
    }

    public function testUserConfigurationOverridesDefaults(): void
    {
        $processed = (new Processor())->processConfiguration(new Configuration(), [
            [
                'payment_url' => 'https://secure.mobilpay.ro',
                'public_cert' => '/certs/public.cer',
                'private_key' => '/certs/private.key',
                'signature'   => 'AAAA-BBBB-CCCC-DDDD-EEEE',
            ],
        ]);

        self::assertSame('https://secure.mobilpay.ro', $processed['payment_url']);
        self::assertSame('/certs/public.cer', $processed['public_cert']);
        self::assertSame('/certs/private.key', $processed['private_key']);
        self::assertSame('AAAA-BBBB-CCCC-DDDD-EEEE', $processed['signature']);
    }
}
