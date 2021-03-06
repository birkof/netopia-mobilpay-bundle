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
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use Mobilpay\Payment\Address;
use Mobilpay\Payment\Invoice;
use Mobilpay\Payment\Request\Card as CardRequest;
use Mobilpay\Payment\Instrument\Card as CardInstrument;
use Mobilpay\Payment\Request\Sms as SmsRequest;
use Psr\Log\LoggerInterface;
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
    public function getMobilPayConfiguration()
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
     * @param array  $extraParameters
     *
     * @return mixed|CardRequest
     * @throws NetopiaMobilPayException
     */
    public function createCreditCardPaymentObject(
        $orderId,
        $amount,
        $currency = NetopiaMobilPayConfiguration::CURRENCY_RON,
        $details = '',
        array $billingAddress = [],
        array $shippingAddress = [],
        array $creditCard = [],
        array $extraParameters = []
    ) {
        try {
            $objPmReqCard = new CardRequest();
            $objPmReqCard->orderId = $orderId;
            $objPmReqCard->signature = $this->mobilPayConfiguration->getSignature();
            $objPmReqCard->confirmUrl = $this->mobilPayConfiguration->getConfirmUrl();
            $objPmReqCard->returnUrl = $this->mobilPayConfiguration->getReturnUrl();

            $objPmReqCard->invoice = new Invoice();
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

            // In case of having payment extra parameters.
            if (!empty($extraParameters)) {
                $objPmReqCard->params = $extraParameters;

                // PLEASE STORE AND USE THIS TOKEN WITH MAXIMUM CARE!!!
                if (!empty($extraParameters['token_id'])) {
                    $objPmReqCard->invoice->tokenId = $extraParameters['token_id'];

                    // Payment with Token need a special route.
                    $this->mobilPayConfiguration->setPaymentUrl(
                        $this->mobilPayConfiguration->getPaymentUrl().'/card4'
                    );
                }
            }

            $objPmReqCard->encrypt($this->mobilPayConfiguration->getPublicCert());

            return $objPmReqCard;
        } catch (\Exception $e) {
            $this->logger->error('Payment failed.', [$e->getMessage()]);

            throw new NetopiaMobilPayException('Payment failed.');
        }
    }

    /**
     * @param     $orderId
     * @param int $price
     *
     * @return mixed|SmsRequest
     * @throws NetopiaMobilPayException
     */
    public function createSmsPaymentObject(
        $orderId = null,
        $serviceId = null
    ) {
        try {
            $objPmReqCard = new SmsRequest();
            $objPmReqCard->orderId = $orderId;
            $objPmReqCard->service = $serviceId;
            $objPmReqCard->signature = $this->mobilPayConfiguration->getSignature();
            $objPmReqCard->confirmUrl = $this->mobilPayConfiguration->getConfirmUrl();
            $objPmReqCard->returnUrl = $this->mobilPayConfiguration->getReturnUrl();

            $objPmReqCard->encrypt($this->mobilPayConfiguration->getPublicCert());

            return $objPmReqCard;
        } catch (\Exception $e) {
            $this->logger->error('Payment failed.', [$e->getMessage()]);

            throw new NetopiaMobilPayException('Payment failed.');
        }
    }

    /**
     * @param array $creditCard
     *
     * @return CardInstrument
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

        $objPmi = new CardInstrument();

        $objPmi->number = $creditCard['number'];
        $objPmi->expYear = $creditCard['expYear'];
        $objPmi->expMonth = $creditCard['expMonth'];
        $objPmi->cvv2 = $creditCard['cvv2'];
        $objPmi->name = $creditCard['name']; //obligatoriu!!!

        return $objPmi;
    }

    /**
     * @param array $address
     *
     * @return Address
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

        $billingAddress = new Address();
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
     * @return Address
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

        $shippingAddress = new Address();
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

