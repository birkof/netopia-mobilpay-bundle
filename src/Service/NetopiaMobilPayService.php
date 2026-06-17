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
                $objPmReqCard->invoice->setBillingAddress($this->composeAddressObject($billingAddress));
            }

            // In case of having Shipping Address.
            if (!empty($shippingAddress)) {
                $objPmReqCard->invoice->setShippingAddress($this->composeAddressObject($shippingAddress));
            }

            // In case of having CC.
            if (!empty($creditCard)) {
                $objPmReqCard->paymentInstrument = $this->composeCreditCardObject($creditCard);
            }

            $isTokenPayment = !empty($extraParameters['token_id']);

            // In case of having payment extra parameters.
            if (!empty($extraParameters)) {
                $objPmReqCard->params = $extraParameters;

                // PLEASE STORE AND USE THIS TOKEN WITH MAXIMUM CARE!!!
                if ($isTokenPayment) {
                    $objPmReqCard->invoice->tokenId = $extraParameters['token_id'];
                }
            }

            // Resolve the gateway endpoint for THIS request from the immutable
            // base URL (token payments use "/card4") without mutating shared
            // configuration state across requests.
            $this->mobilPayConfiguration->resolvePaymentUrl($isTokenPayment);

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
     * Build a Mobilpay\Payment\Address from a plain array.
     *
     * Only the fields the library's Address actually serializes are set:
     * type, firstName, lastName, address, email, mobilePhone. Any other keys
     * (fiscalNumber, identityNumber, country, county, city, zipCode, bank,
     * iban) are NOT supported by Mobilpay\Payment\Address::createXmlElement()
     * and were previously assigned as dynamic properties that the library
     * silently dropped from the payment request (and that PHP 8.2+ deprecates).
     *
     * @param array $address
     *
     * @return Address
     */
    private function composeAddressObject(array $address = [])
    {
        $addressDefault = [
            'type'        => Address::TYPE_PERSON, // 'person' or 'company'
            'firstName'   => null,
            'lastName'    => null,
            'address'     => null,
            'email'       => null,
            'mobilePhone' => null,
        ];

        $address = array_merge($addressDefault, $address);

        $addressObject = new Address();
        $addressObject->type = $address['type'];
        $addressObject->firstName = $address['firstName'];
        $addressObject->lastName = $address['lastName'];
        $addressObject->address = $address['address'];
        $addressObject->email = $address['email'];
        $addressObject->mobilePhone = $address['mobilePhone'];

        return $addressObject;
    }
}

