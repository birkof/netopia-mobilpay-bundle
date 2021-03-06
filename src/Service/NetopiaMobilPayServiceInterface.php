<?php
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
    public function getMobilPayConfiguration();

    /**
     * @param        $orderId
     * @param        $amount
     * @param        $currency
     * @param string $details
     * @param array  $billing
     * @param array  $shipping
     * @param array  $creditCard
     * @param array  $extraParameters
     *
     * @return mixed
     */
    public function createCreditCardPaymentObject(
        $orderId,
        $amount,
        $currency = self::CURRENCY_RON,
        $details = '',
        array $billing = [],
        array $shipping = [],
        array $creditCard = [],
        array $extraParameters = []
    );

    /**
     * @param $orderId
     * @param $amount
     *
     * @return mixed
     */
    public function createSmsPaymentObject(
        $orderId,
        $amount
    );
}
