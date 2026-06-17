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

use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;

interface NetopiaMobilPayIpnHandlerInterface
{
    /** The merchant hit a transient error; Netopia should retry the IPN. */
    public const ERROR_TYPE_TEMPORARY = 1;

    /** The notification is permanently rejected; Netopia must stop retrying. */
    public const ERROR_TYPE_PERMANENT = 2;

    /**
     * Decrypt and parse a Netopia IPN callback. Successful decryption with the
     * merchant's private key is the authenticity guarantee.
     *
     * @param string      $envKey  base64 env_key posted by the gateway
     * @param string      $encData base64 data posted by the gateway
     * @param string|null $cipher  symmetric cipher used to seal (null = legacy RC4)
     * @param string|null $iv      base64 IV for block ciphers (null for stream ciphers)
     *
     * @throws NetopiaMobilPayException when the payload is empty or cannot be decrypted
     */
    public function decrypt(string $envKey, string $encData, ?string $cipher = null, ?string $iv = null): IpnResult;

    /** Build the success `<crc>` acknowledgement XML. */
    public function confirmResponse(): string;

    /** Build the `<crc error_type error_code>` negative acknowledgement XML. */
    public function errorResponse(
        string $message,
        int $errorType = self::ERROR_TYPE_PERMANENT,
        int $errorCode = 0
    ): string;
}
