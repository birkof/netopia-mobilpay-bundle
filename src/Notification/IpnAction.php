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

namespace birkof\NetopiaMobilPay\Notification;

/**
 * The payment action reported by a Netopia IPN notification.
 */
enum IpnAction: string
{
    case Confirmed        = 'confirmed';         // funds captured
    case ConfirmedPending = 'confirmed_pending'; // under review (e.g. fraud check)
    case PaidPending      = 'paid_pending';      // pending
    case Paid             = 'paid';              // pre-authorized, funds reserved not captured
    case Canceled         = 'canceled';
    case Credit           = 'credit';            // refund / chargeback
    case Unknown          = 'unknown';           // any value the gateway adds later

    public static function fromValue(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Unknown;
        }

        return self::tryFrom($value) ?? self::Unknown;
    }
}
