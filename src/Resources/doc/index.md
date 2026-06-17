# Netopia MobilPay Payment Gateway Symfony Bundle

> Integration guide. For an architecture overview see the project [README](../../../README.md).

The bundle wraps the low-level `birkof/netopia-mobilpay` SDK and exposes two Symfony services:

- `netopia_mobilpay.payment` — builds an RSA‑encrypted **outbound** payment request (autowired by `NetopiaMobilPayServiceInterface`).
- `netopia_mobilpay.ipn_handler` — decrypts and acknowledges the **inbound** IPN callback (autowired by `NetopiaMobilPayIpnHandlerInterface`).

Requires **PHP 8.3+** and Symfony **4.4 / 5 / 6** (`symfony/routing`, `symfony/yaml`, `symfony/monolog-bundle`).

---

## Installation

### 1. Require the package

```bash
composer require birkof/netopia-mobilpay-bundle
```

### 2. Register the bundle

Automatic with Symfony Flex. Otherwise add it to `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    birkof\NetopiaMobilPay\NetopiaMobilPayBundle::class => ['all' => true],
];
```

---

## Configuration

```yaml
# config/packages/netopia_mobilpay.yaml
netopia_mobilpay:
    payment_url: '%env(NETOPIA_MOBILPAY_PAYMENT_URL)%'   # gateway base URL
    public_cert: '%env(NETOPIA_MOBILPAY_PUBLIC_CERT)%'   # file path OR inline PEM — seals outbound requests
    private_key: '%env(NETOPIA_MOBILPAY_PRIVATE_KEY)%'   # file path OR inline PEM — opens inbound IPNs
    signature:   '%env(NETOPIA_MOBILPAY_SIGNATURE)%'     # REQUIRED
```

| Key | Required | Default | Notes |
|-----|----------|---------|-------|
| `signature` | **yes** | — | merchant signature; the bundle **fails to boot** if it is missing or empty |
| `payment_url` | no | `http://sandboxsecure.mobilpay.ro` | set your production URL in prod |
| `public_cert` | no | `null` | a readable file path (resolved under `%kernel.project_dir%`) or the literal PEM content |
| `private_key` | no | `null` | a readable file path (resolved under `%kernel.project_dir%`) or the literal PEM content |

```dotenv
# .env.local
NETOPIA_MOBILPAY_PAYMENT_URL=http://sandboxsecure.mobilpay.ro
NETOPIA_MOBILPAY_PUBLIC_CERT=%kernel.project_dir%/config/netopia/sandbox.public.cer
NETOPIA_MOBILPAY_PRIVATE_KEY=%kernel.project_dir%/config/netopia/sandbox.private.key
NETOPIA_MOBILPAY_SIGNATURE=XXXX-XXXX-XXXX-XXXX-XXXX
```

### Required routes

The configuration generates absolute confirm/return URLs from two route **names** it expects
your application to define (`NetopiaMobilPayConfiguration::CONFIRM_URL` and `::RETURN_URL`).
These exact names are mandatory:

```yaml
# config/routes.yaml
netopia_mobilpay_confirm_url:        # server-to-server IPN endpoint (POST from Netopia)
    path: /payment/netopia/confirm
    controller: App\Controller\PaymentController::ipn
    methods: [POST]

netopia_mobilpay_return_url:         # browser returns here after the gateway
    path: /payment/netopia/return
    controller: App\Controller\PaymentController::return
```

---

## Usage — starting a payment (outbound)

Inject `NetopiaMobilPayServiceInterface` and call `createCreditCardPaymentObject()`. It
validates the input (non-empty `orderId`, positive numeric `amount`, supported `currency`),
builds and RSA‑seals the request, and returns the SDK request object. Render an
auto‑submitting form that POSTs the sealed `env_key` / `data` (+ `cipher` / `iv` for block
ciphers) to the gateway URL.

```php
use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayServiceInterface;
use Symfony\Component\HttpFoundation\Response;

public function checkout(NetopiaMobilPayServiceInterface $payments): Response
{
    $request = $payments->createCreditCardPaymentObject(
        'ORDER-1001',                                 // orderId  (required, non-empty)
        '49.99',                                      // amount   (required, positive numeric)
        NetopiaMobilPayConfiguration::CURRENCY_RON,   // currency (CURRENCY_RON | _EUR | _USD)
        'Order #1001',                                // details
        [                                             // billing address (optional)
            'type'        => 'person',                //   'person' or 'company'
            'firstName'   => 'Ion',
            'lastName'    => 'Popescu',
            'address'     => 'Str. Exemplu 1',
            'email'       => 'ion@example.com',
            'mobilePhone' => '0700000000',
        ],
        // [] shippingAddress, [] creditCard, [] extraParameters
    );

    return $this->render('payment/redirect.html.twig', [
        'paymentUrl' => $payments->getMobilPayConfiguration()->getPaymentUrl(),
        'envKey'     => $request->getEnvKey(),
        'data'       => $request->getEncData(),
        'cipher'     => $request->getCipher(),
        'iv'         => $request->getIv(),
    ]);
}
```

```twig
{# templates/payment/redirect.html.twig — auto-submits to Netopia #}
<form id="netopia" method="post" action="{{ paymentUrl }}">
    <input type="hidden" name="env_key" value="{{ envKey }}">
    <input type="hidden" name="data"    value="{{ data }}">
    {% if cipher %}<input type="hidden" name="cipher" value="{{ cipher }}">{% endif %}
    {% if iv %}<input type="hidden" name="iv" value="{{ iv }}">{% endif %}
</form>
<script>document.getElementById('netopia').submit();</script>
```

Notes:

- Only billing/shipping `Address` fields the SDK serializes are sent: `type`, `firstName`,
  `lastName`, `address`, `email`, `mobilePhone` (other keys are ignored).
- **Token (one-click) payments:** pass `['token_id' => '<token>']` as the `extraParameters`
  argument. The bundle sets the invoice token and routes the request to the gateway's
  `/card4` endpoint for that call only.
- **SMS payments:** `createSmsPaymentObject($orderId, $serviceId)` produces the SMS-payment
  equivalent.
- Invalid input (empty `orderId`, non-positive `amount`, unsupported `currency`) throws
  `NetopiaMobilPayException`.

> **PCI scope:** leave the `creditCard` argument empty and let the gateway's hosted page
> collect the card details. Passing a raw PAN/CVV through your server places the whole
> application in PCI-DSS SAQ-D scope. See [Card data and PCI scope](#card-data-and-pci-scope).

---

## Handling the IPN (payment confirmation, inbound)

Netopia POSTs the encrypted notification to your `netopia_mobilpay_confirm_url` route.
Decrypt it with the `NetopiaMobilPayIpnHandlerInterface` service:

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

`IpnResult` exposes the decrypted notification as typed, read-only data — `action`
(an `IpnAction` enum), `errorCode`, `errorMessage`, `purchaseId`, `originalAmount`,
`processedAmount`, `tokenId`, `panMasked`, `timestamp` — plus the predicates
`isError()`, `isConfirmed()`, `isPaid()`, `isPending()`, `isCanceled()`.

**Security checklist for the IPN endpoint**

- Successful `decrypt()` authenticates the sender — only Netopia can seal a payload to your
  public certificate, so opening it with your private key proves origin. There is no separate
  signature on the callback.
- You MUST cross-check `purchaseId` against a real, unfulfilled order and confirm
  `processedAmount` equals the amount you expected. The bundle cannot do this for you.
- Only acknowledge with `confirmResponse()` after your own checks pass. On a transient
  failure return `errorResponse(..., ERROR_TYPE_TEMPORARY)` so Netopia retries
  (`ERROR_TYPE_PERMANENT` tells it to stop).

---

## Card data and PCI scope

`createCreditCardPaymentObject()` accepts an optional `$creditCard` array (PAN, CVV,
expiry). **Passing raw card data through your server puts the entire application in
PCI-DSS SAQ-D scope (full audit).**

The recommended Netopia integration is the **hosted payment page**: leave `$creditCard`
empty and let the gateway collect the card details, so no PAN/CVV ever touches your
server. Only use the raw-card path if you are already PCI-DSS certified for server-side
card handling.
