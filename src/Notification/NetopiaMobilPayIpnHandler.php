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

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use Mobilpay\Payment\Request\Notify;
use Mobilpay\Payment\Request\RequestAbstract;
use Psr\Log\LoggerInterface;

final class NetopiaMobilPayIpnHandler implements NetopiaMobilPayIpnHandlerInterface
{
    public function __construct(
        private readonly NetopiaMobilPayConfiguration $configuration,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function decrypt(string $envKey, string $encData, ?string $cipher = null, ?string $iv = null): IpnResult
    {
        if ($envKey === '' || $encData === '') {
            throw new NetopiaMobilPayException('Invalid IPN notification payload.');
        }

        try {
            $request = RequestAbstract::factoryFromEncrypted(
                $envKey,
                $encData,
                $this->configuration->getPrivateKey(),
                null,
                $cipher,
                $iv,
            );
        } catch (\Throwable $e) {
            // Generic message only: never log key material or raw ciphertext.
            $this->logger->error('Unable to decrypt IPN notification.', ['reason' => $e->getMessage()]);

            throw new NetopiaMobilPayException('Unable to decrypt IPN notification.');
        }

        if (!$request->objPmNotify instanceof Notify) {
            throw new NetopiaMobilPayException('IPN notification missing payment data.');
        }

        return IpnResult::fromNotify($request->objPmNotify);
    }

    public function confirmResponse(): string
    {
        return $this->buildCrc(null, null, null);
    }

    public function errorResponse(
        string $message,
        int $errorType = self::ERROR_TYPE_PERMANENT,
        int $errorCode = 0
    ): string {
        return $this->buildCrc($message, $errorType, $errorCode);
    }

    /**
     * Build the `<crc>` response. DOMDocument guarantees the message and
     * attribute values are XML-escaped (no injection through $message).
     */
    private function buildCrc(?string $message, ?int $errorType, ?int $errorCode): string
    {
        $document = new \DOMDocument('1.0', 'utf-8');
        $crc = $document->createElement('crc');

        if ($errorType !== null) {
            $crc->setAttribute('error_type', (string) $errorType);
        }
        if ($errorCode !== null) {
            $crc->setAttribute('error_code', (string) $errorCode);
        }
        if ($message !== null && $message !== '') {
            $crc->appendChild($document->createTextNode($message));
        }

        $document->appendChild($crc);

        $xml = $document->saveXML();

        return $xml !== false ? $xml : '';
    }
}
