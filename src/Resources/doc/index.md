# Netopia MobilPay Payment Gateway Symfony Bundle
> A Symfony 3, 4 bundle to implement MobilPay.ro payment gateway


## Installation

You just require using composer and you're good to go!
````bash
composer require birkof/netopia-mobilpay-bundle
````

If you don't use Flex, you need to manually enable bundle in your kernel:

```$php
<?php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = [
        // ...
        new birkof\NetopiaMobilPay\NetopiaMobilPayBundle(),
    ];
}
```


## Configuration

Configuration typically lives in the config/packages/netopia_mobilpay.yaml file for a Symfony 4 application.
```
# config/packages/netopia_mobilpay.yaml

netopia_mobilpay:
    payment_url:    '%env(NETOPIA_MOBILPAY_PAYMENT_URL)%'
    public_cert:    '%env(NETOPIA_MOBILPAY_PUBLIC_CERT)%' // Allowed to pass the certificate content directly as well as its file path
    private_key:    '%env(NETOPIA_MOBILPAY_PRIVATE_KEY)%' // Allowed to pass the key content directly as well as its file path
    signature:      '%env(NETOPIA_MOBILPAY_SIGNATURE)%'
```
You should define ``NETOPIA_MOBILPAY_PAYMENT_URL``, ``NETOPIA_MOBILPAY_PUBLIC_CERT``, ``NETOPIA_MOBILPAY_PRIVATE_KEY`` and ``NETOPIA_MOBILPAY_SIGNATURE`` in your environment variables.

If you're still using the old, non-environment system:
```
# app/config/config.yml

netopia_mobilpay:
    payment_url:  '%netopia_mobilpay_payment_url%'
    public_cert:  '%netopia_mobilpay_public_cert%'
    private_key:  '%netopia_mobilpay_private_key%'
    signature:    '%netopia_mobilpay_signature%'
```
And define ``netopia_mobilpay_payment_url``, ``netopia_mobilpay_public_cert``, ``netopia_mobilpay_private_key`` and ``netopia_mobilpay_signature`` parameters in app/config/parameters.yml file.


## Usage

> Follow the interface birkof\NetopiaMobilPay\Service\NetopiaMobilPayServiceInterface to see avaialable methods.


## Handling the IPN (payment confirmation)

Netopia POSTs the encrypted notification to your `confirm_url`. Decrypt it with the
`NetopiaMobilPayIpnHandlerInterface` service (autowired by interface):

```php
use birkof\NetopiaMobilPay\Exception\NetopiaMobilPayException;
use birkof\NetopiaMobilPay\Notification\NetopiaMobilPayIpnHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

public function ipn(Request $request, NetopiaMobilPayIpnHandlerInterface $ipn): Response
{
    try {
        $result = $ipn->decrypt(
            (string) $request->request->get('env_key'),
            (string) $request->request->get('data'),
            $request->request->get('cipher'), // null for legacy RC4 payloads
            $request->request->get('iv')      // null for legacy RC4 payloads
        );
    } catch (NetopiaMobilPayException $e) {
        // Transient failure on our side: ask Netopia to retry later.
        return new Response(
            $ipn->errorResponse('cannot process', $ipn::ERROR_TYPE_TEMPORARY),
            200,
            ['Content-Type' => 'application/xml']
        );
    }

    if ($result->isConfirmed()) {
        // SECURITY: the bundle proves the notification came from Netopia, but it
        // cannot know the expected amount. Load the order by $result->purchaseId and
        // verify $result->processedAmount matches BEFORE marking it paid.
        // ... your fulfilment logic ...
    }

    return new Response(
        $ipn->confirmResponse(),
        200,
        ['Content-Type' => 'application/xml']
    );
}
```

**Security checklist for the IPN endpoint**

- Successful `decrypt()` authenticates the sender (only Netopia can seal to your public cert).
- You MUST cross-check `purchaseId` against a real, unfulfilled order and confirm
  `processedAmount` equals the amount you expected. The bundle cannot do this for you.
- Only acknowledge with `confirmResponse()` after your own checks pass. On a transient
  failure return `errorResponse(..., ERROR_TYPE_TEMPORARY)` so Netopia retries.


## Card data and PCI scope

`createCreditCardPaymentObject()` accepts an optional `$creditCard` array (PAN, CVV,
expiry). **Passing raw card data through your server puts the entire application in
PCI-DSS SAQ-D scope (full audit).**

The recommended Netopia integration is the **hosted payment page**: leave `$creditCard`
empty and let the gateway collect the card details, so no PAN/CVV ever touches your
server. Only use the raw-card path if you are already PCI-DSS certified for server-side
card handling.
