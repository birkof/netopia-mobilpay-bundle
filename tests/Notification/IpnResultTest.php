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
use birkof\NetopiaMobilPay\Notification\IpnResult;
use Mobilpay\Payment\Request\Notify;
use PHPUnit\Framework\TestCase;

final class IpnResultTest extends TestCase
{
    private function notify(string $action, string $errorCode = '0'): Notify
    {
        $notify = new Notify();
        $notify->action = $action;
        $notify->errorCode = $errorCode;
        $notify->errorMessage = $errorCode === '0' ? '' : 'failed';
        $notify->purchaseId = '123';
        $notify->originalAmount = '49.99';
        $notify->processedAmount = '49.99';
        $notify->token_id = 'tok_abc';
        $notify->pan_masked = '4111********1111';
        $notify->timestamp = '20260616120000';

        return $notify;
    }

    public function testFromNotifyMapsEveryField(): void
    {
        $result = IpnResult::fromNotify($this->notify('confirmed'));

        self::assertSame(IpnAction::Confirmed, $result->action);
        self::assertSame('confirmed', $result->rawAction);
        self::assertSame(0, $result->errorCode);
        self::assertSame('', $result->errorMessage);
        self::assertSame('123', $result->purchaseId);
        self::assertSame('49.99', $result->originalAmount);
        self::assertSame('49.99', $result->processedAmount);
        self::assertSame('tok_abc', $result->tokenId);
        self::assertSame('4111********1111', $result->panMasked);
        self::assertSame('20260616120000', $result->timestamp);
    }

    public function testConfirmedPredicates(): void
    {
        $result = IpnResult::fromNotify($this->notify('confirmed'));

        self::assertFalse($result->isError());
        self::assertTrue($result->isConfirmed());
        self::assertFalse($result->isPaid());
        self::assertFalse($result->isPending());
        self::assertFalse($result->isCanceled());
    }

    public function testPaidAndPendingAndCanceledPredicates(): void
    {
        self::assertTrue(IpnResult::fromNotify($this->notify('paid'))->isPaid());
        self::assertTrue(IpnResult::fromNotify($this->notify('confirmed_pending'))->isPending());
        self::assertTrue(IpnResult::fromNotify($this->notify('paid_pending'))->isPending());
        self::assertTrue(IpnResult::fromNotify($this->notify('canceled'))->isCanceled());
    }

    public function testErrorCodeDisablesSuccessPredicates(): void
    {
        $result = IpnResult::fromNotify($this->notify('confirmed', '99'));

        self::assertTrue($result->isError());
        self::assertSame(99, $result->errorCode);
        self::assertFalse($result->isConfirmed());
    }
}
