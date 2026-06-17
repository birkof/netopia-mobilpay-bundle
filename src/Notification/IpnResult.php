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

use Mobilpay\Payment\Request\Notify;

/**
 * Immutable, vendor-agnostic view of a decrypted Netopia IPN notification.
 */
final readonly class IpnResult
{
    public function __construct(
        public IpnAction $action,
        public ?string $rawAction,
        public int $errorCode,
        public ?string $errorMessage,
        public ?string $purchaseId,
        public ?string $originalAmount,
        public ?string $processedAmount,
        public ?string $tokenId,
        public ?string $panMasked,
        public ?string $timestamp,
    ) {
    }

    public static function fromNotify(Notify $notify): self
    {
        return new self(
            action: IpnAction::fromValue($notify->action),
            rawAction: $notify->action,
            errorCode: (int) $notify->errorCode,
            errorMessage: $notify->errorMessage,
            purchaseId: $notify->purchaseId,
            originalAmount: $notify->originalAmount,
            processedAmount: $notify->processedAmount,
            tokenId: $notify->token_id,
            panMasked: $notify->pan_masked,
            timestamp: $notify->timestamp,
        );
    }

    public function isError(): bool
    {
        return $this->errorCode !== 0;
    }

    public function isConfirmed(): bool
    {
        return !$this->isError() && $this->action === IpnAction::Confirmed;
    }

    public function isPaid(): bool
    {
        return !$this->isError() && $this->action === IpnAction::Paid;
    }

    public function isPending(): bool
    {
        return !$this->isError()
            && \in_array($this->action, [IpnAction::ConfirmedPending, IpnAction::PaidPending], true);
    }

    public function isCanceled(): bool
    {
        return !$this->isError() && $this->action === IpnAction::Canceled;
    }
}
