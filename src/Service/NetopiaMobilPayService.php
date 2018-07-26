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
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class NetopiaMobilPayService
 * @package birkof\NetopiaMobilPay\Service
 */
final class NetopiaMobilPayService implements NetopiaMobilPayServiceInterface
{
    /** @var NetopiaMobilPayConfiguration */
    private $mobilPayConfiguration;

    /** @var RouterInterface */
    private $router;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @return NetopiaMobilPayConfiguration
     */
    public function getMobilPayConfiguration(): NetopiaMobilPayConfiguration
    {
        return $this->mobilPayConfiguration;
    }

    /**
     * NetopiaMobilPayService constructor.
     *
     * @param NetopiaMobilPayConfiguration $mobilPayConfiguration
     * @param RouterInterface              $router
     * @param LoggerInterface              $logger
     */
    public function __construct(NetopiaMobilPayConfiguration $mobilPayConfiguration, RouterInterface $router, LoggerInterface $logger)
    {
        $this->mobilPayConfiguration = $mobilPayConfiguration;
        $this->router = $router;
        $this->logger = $logger;
    }

    /**
     * @param        $orderId
     * @param        $amount
     * @param string $currency
     * @param string $details
     * @param array  $billingAddress
     * @param array  $shippingAddress
     * @param array  $creditCard
     *
     * @return mixed|\Mobilpay_Payment_Request_Card|void
     * @throws NetopiaMobilPayException
     */
    public function createPaymentObject(
        $orderId,
        $amount,
        $currency = NetopiaMobilPayConfiguration::CURRENCY_RON,
        $details = '',
        array $billingAddress = [],
        array $shippingAddress  = [],
        array $creditCard = []
    ) {
        try {
            $objPmReqCard = new \Mobilpay_Payment_Request_Card();
            $objPmReqCard->orderId = $orderId;
            $objPmReqCard->signature = $this->mobilPayConfiguration->getSignature();
            $objPmReqCard->confirmUrl = $this->mobilPayConfiguration->getConfirmUrl();
            $objPmReqCard->returnUrl = $this->mobilPayConfiguration->getReturnUrl();

            $objPmReqCard->invoice = new \Mobilpay_Payment_Invoice();
            $objPmReqCard->invoice->currency = $currency;
            $objPmReqCard->invoice->amount = $amount;
            $objPmReqCard->invoice->details = $details;

            // In case of having Billing Address.
            if (!empty($billingAddress)) {
                $objPmReqCard->invoice->setBillingAddress($this->composeBillingAddressObject($billingAddress));
            }

            // In case of having Shipping Address.
            if (!empty($shippingAddress)) {
                $objPmReqCard->invoice->setShippingAddress($this->composeShippingAddressObject($shippingAddress));
            }

            // In case of having CC.
            if (!empty($creditCard)) {
                $objPmReqCard->paymentInstrument = $this->composeCreditCardObject($creditCard);
            }

            $objPmReqCard->encrypt($this->mobilPayConfiguration->getPublicCert());

            return $objPmReqCard;
        } catch (\Exception $e) {
            $this->logger->error('Payment failed.', [$e->getMessage()]);

            throw new NetopiaMobilPayException('Payment failed.');
        }

        return;
    }

    /**
     * @param array $creditCard
     *
     * @return \Mobilpay_Payment_Instrument_Card
     * @throws NetopiaMobilPayException
     */
    protected function composeCreditCardObject(array $creditCard = [])
    {
        $creditCardDefault = [
            'number'   => null,
            'expYear'  => null,
            'expMonth' => null,
            'cvv2'     => null,
            'name'     => null,
        ];

        /** @var array $address */
        $creditCard = array_merge($creditCardDefault, $creditCard);

        if (empty($creditCard['name'])) {
            throw new NetopiaMobilPayException('Credit Card configuration error.');
        }

        $objPmi = new \Mobilpay_Payment_Instrument_Card();

        $objPmi->number = $creditCard['number'];
        $objPmi->expYear = $creditCard['expYear'];
        $objPmi->expMonth = $creditCard['expMonth'];
        $objPmi->cvv2 = $creditCard['cvv2'];
        $objPmi->name = $creditCard['name']; //obligatoriu!!!
        $objPmReqCard->paymentInstrument = $objPmi;

        return $objPmReqCard;
    }

    /**
     * @param array $address
     *
     * @return \Mobilpay_Payment_Address
     */
    protected function composeBillingAddressObject(array $address = [])
    {
        $addressDefault = [
            'type'           => 'person', // 'person' or 'company'
            'firstName'      => null,
            'lastName'       => null,
            'fiscalNumber'   => null,
            'identityNumber' => null,
            'country'        => null,
            'county'         => null,
            'city'           => null,
            'zipCode'        => null,
            'address'        => null,
            'email'          => null,
            'mobilePhone'    => null,
            'bank'           => null,
            'iban'           => null,
        ];

        /** @var array $address */
        $address = array_merge($addressDefault, $address);
        dump($address);
        exit;

        $billingAddress = new \Mobilpay_Payment_Address();
        $billingAddress->type = $address['type'];
        $billingAddress->firstName = $address['firstName'];
        $billingAddress->lastName = $address['lastName'];
        $billingAddress->fiscalNumber = $address['fiscalNumber'];
        $billingAddress->identityNumber = $address['identityNumber'];
        $billingAddress->country = $address['country'];
        $billingAddress->county = $address['county'];
        $billingAddress->city = $address['city'];
        $billingAddress->zipCode = $address['zipCode'];
        $billingAddress->address = $address['address'];
        $billingAddress->email = $address['email'];
        $billingAddress->mobilePhone = $address['mobilePhone'];
        $billingAddress->bank = $address['bank'];
        $billingAddress->iban = $address['iban'];

        return $billingAddress;
    }

    /**
     * @param array $address
     *
     * @return \Mobilpay_Payment_Address
     */
    protected function composeShippingAddressObject(array $address = [])
    {
        $addressDefault = [
            'type'           => 'person', // 'person' or 'company'
            'firstName'      => null,
            'lastName'       => null,
            'fiscalNumber'   => null,
            'identityNumber' => null,
            'country'        => null,
            'county'         => null,
            'city'           => null,
            'zipCode'        => null,
            'address'        => null,
            'email'          => null,
            'mobilePhone'    => null,
            'bank'           => null,
            'iban'           => null,
        ];

        /** @var array $address */
        $address = array_merge($addressDefault, $address);

        $shippingAddress = new \Mobilpay_Payment_Address();
        $shippingAddress->type = $address['type'];
        $shippingAddress->firstName = $address['firstName'];
        $shippingAddress->lastName = $address['lastName'];
        $shippingAddress->fiscalNumber = $address['fiscalNumber'];
        $shippingAddress->identityNumber = $address['identityNumber'];
        $shippingAddress->country = $address['country'];
        $shippingAddress->county = $address['county'];
        $shippingAddress->city = $address['city'];
        $shippingAddress->zipCode = $address['zipCode'];
        $shippingAddress->address = $address['address'];
        $shippingAddress->email = $address['email'];
        $shippingAddress->mobilePhone = $address['mobilePhone'];
        $shippingAddress->bank = $address['bank'];
        $shippingAddress->iban = $address['iban'];

        return $shippingAddress;
    }
}
