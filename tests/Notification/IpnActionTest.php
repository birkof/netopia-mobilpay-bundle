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

use birkof\NetopiaMobilPay\Notification\IpnAction;
use PHPUnit\Framework\TestCase;

final class IpnActionTest extends TestCase
{
    public function testFromValueMapsKnownActions(): void
    {
        self::assertSame(IpnAction::Confirmed, IpnAction::fromValue('confirmed'));
        self::assertSame(IpnAction::ConfirmedPending, IpnAction::fromValue('confirmed_pending'));
        self::assertSame(IpnAction::PaidPending, IpnAction::fromValue('paid_pending'));
        self::assertSame(IpnAction::Paid, IpnAction::fromValue('paid'));
        self::assertSame(IpnAction::Canceled, IpnAction::fromValue('canceled'));
        self::assertSame(IpnAction::Credit, IpnAction::fromValue('credit'));
    }

    public function testFromValueReturnsUnknownForUnrecognizedOrNull(): void
    {
        self::assertSame(IpnAction::Unknown, IpnAction::fromValue('something_new'));
        self::assertSame(IpnAction::Unknown, IpnAction::fromValue(null));
        self::assertSame(IpnAction::Unknown, IpnAction::fromValue(''));
    }
}
