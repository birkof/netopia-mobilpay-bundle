<?php
declare(strict_types = 1);
/*
 * This file is part of the NetopiaMobilPayBundle.
 *
 * (c) Daniel STANCU <birkof@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace birkof\NetopiaMobilPay\Service;

use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Interface NetopiaMobilPayServiceInterface
 * @package birkof\NetopiaMobilPay\Service
 */
interface NetopiaMobilPayServiceInterface
{
    /**
     * @return NetopiaMobilPayConfiguration
     */
    public function getMobilPayConfiguration(): NetopiaMobilPayConfiguration;

    /**
     * @param        $orderId
     * @param        $amount
     * @param        $currency
     * @param string $details
     * @param array  $billing
     * @param array  $shipping
     * @param array  $creditCard
     *
     * @return mixed
     */
    public function createPaymentObject(
        $orderId,
        $amount,
        $currency = self::CURRENCY_RON,
        $details = '',
        array $billing = [],
        array $shipping = [],
        array $creditCard = []
    );
}
