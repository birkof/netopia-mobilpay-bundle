# Netopia MobilPay Bundle — Architecture Overview

A thin, typed Symfony integration layer over the [`birkof/netopia-mobilpay`](https://packagist.org/packages/birkof/netopia-mobilpay) low-level SDK. It wires the SDK into the Symfony service container and exposes two responsibilities of the Netopia (MobilPay) card-payment protocol:

- **Outbound** — build an RSA‑encrypted payment request to redirect a customer to the gateway.
- **Inbound** — decrypt and authenticate the gateway's IPN (Instant Payment Notification) callback and produce the `<crc>` acknowledgement it expects.

The bundle holds **no state, no persistence, and no HTTP controllers**. It builds request objects and parses notifications; the consuming application owns routing, fulfilment, and storage.

> Source of truth for this document: the files under `src/` (14 PHP files, ~1090 LOC), `composer.json`, `phpunit.xml.dist`, and `.github/workflows/ci.yml`. Usage examples live in [`src/Resources/doc/index.md`](./src/Resources/doc/index.md).

---

## Requirements

From `composer.json`:

| Dependency | Constraint | Role |
|---|---|---|
| `php` | `^8.3` | runtime |
| `birkof/netopia-mobilpay` | `^4.0` | low-level Netopia SDK (`Mobilpay\…`): crypto + XML protocol |
| `symfony/routing` | `^4.4 \|\| ^5.0 \|\| ^6.0` | generates the absolute confirm/return URLs |
| `symfony/yaml` | `^4.4 \|\| ^5.0 \|\| ^6.0` | loads `Resources/config/services.yaml` |
| `symfony/monolog-bundle` | `^3.7` | provides the `logger` service injected into both services |
| `phpunit/phpunit` (dev) | `^11.5` | test suite |

Autoload (PSR‑4): `birkof\NetopiaMobilPay\` → `src/`.

---

## Installation

### 1. Require the package

```bash
composer require birkof/netopia-mobilpay-bundle
```

### 2. Register the bundle

With Symfony Flex this is automatic. Otherwise add it to `config/bundles.php`:

```php
// config/bundles.php
return [
    // ...
    birkof\NetopiaMobilPay\NetopiaMobilPayBundle::class => ['all' => true],
];
```

### 3. Configure the gateway

`signature` is **required** — the bundle fails to boot without it. `payment_url`,
`public_cert` and `private_key` are optional (defaults: sandbox URL, `null`, `null`).
Both `public_cert` and `private_key` accept **either a file path** (resolved relative to
`%kernel.project_dir%`) **or the inline PEM content**.

```yaml
# config/packages/netopia_mobilpay.yaml
netopia_mobilpay:
    payment_url: '%env(NETOPIA_MOBILPAY_PAYMENT_URL)%'   # e.g. https://secure.mobilpay.ro (prod)
    public_cert: '%env(NETOPIA_MOBILPAY_PUBLIC_CERT)%'   # path or PEM — seals outbound requests
    private_key: '%env(NETOPIA_MOBILPAY_PRIVATE_KEY)%'   # path or PEM — opens inbound IPNs
    signature:   '%env(NETOPIA_MOBILPAY_SIGNATURE)%'     # REQUIRED
```

```dotenv
# .env (or .env.local)
NETOPIA_MOBILPAY_PAYMENT_URL=http://sandboxsecure.mobilpay.ro
NETOPIA_MOBILPAY_PUBLIC_CERT=%kernel.project_dir%/config/netopia/sandbox.public.cer
NETOPIA_MOBILPAY_PRIVATE_KEY=%kernel.project_dir%/config/netopia/sandbox.private.key
NETOPIA_MOBILPAY_SIGNATURE=XXXX-XXXX-XXXX-XXXX-XXXX
```

### 4. Define the confirm and return routes

The bundle generates absolute URLs from two route **names** it expects you to define
(`NetopiaMobilPayConfiguration::CONFIRM_URL` / `::RETURN_URL`). These names are mandatory —
missing them breaks URL generation at container build time:

```yaml
# config/routes.yaml
netopia_mobilpay_confirm_url:        # server-to-server IPN endpoint
    path: /payment/netopia/confirm
    controller: App\Controller\PaymentController::ipn
    methods: [POST]

netopia_mobilpay_return_url:         # browser lands here after the gateway
    path: /payment/netopia/return
    controller: App\Controller\PaymentController::return
```

---

## Usage

Both entry points are autowired by their interface:

```php
public function __construct(
    private NetopiaMobilPayServiceInterface $payments,   // outbound — build a request
    private NetopiaMobilPayIpnHandlerInterface $ipn,      // inbound  — handle the callback
) {}
```

### Start a payment (outbound)

`createCreditCardPaymentObject()` validates the input, builds and RSA‑seals the request,
and returns the SDK request object exposing the sealed `env_key` / `data` (and `cipher` / `iv`
for block ciphers). Render an auto‑submitting form that POSTs them to the gateway URL.

```php
use birkof\NetopiaMobilPay\Configuration\NetopiaMobilPayConfiguration;
use birkof\NetopiaMobilPay\Service\NetopiaMobilPayServiceInterface;
use Symfony\Component\HttpFoundation\Response;

public function checkout(NetopiaMobilPayServiceInterface $payments): Response
{
    $request = $payments->createCreditCardPaymentObject(
        'ORDER-1001',                            // orderId  (required, non-empty)
        '49.99',                                 // amount   (required, positive numeric)
        NetopiaMobilPayConfiguration::CURRENCY_RON, // currency (RON | EUR | USD)
        'Order #1001',                           // details
        [                                        // billing address (optional)
            'type'        => 'person',
            'firstName'   => 'Ion',
            'lastName'    => 'Popescu',
            'address'     => 'Str. Exemplu 1',
            'email'       => 'ion@example.com',
            'mobilePhone' => '0700000000',
        ],
        // [], shipping   [], creditCard   [], extraParameters (token payments)
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

> **PCI note:** leave the `creditCard` argument empty and let the gateway's hosted page
> collect the card details. Passing a raw PAN/CVV through your server places the whole
> application in PCI‑DSS SAQ‑D scope.

### Handle the confirmation (inbound IPN)

Netopia POSTs the encrypted notification to your `netopia_mobilpay_confirm_url` route.
Decrypt it, verify the amount/order **yourself**, then acknowledge with `<crc>`:

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
            $request->request->get('iv'),      // null for legacy RC4 payloads
        );
    } catch (NetopiaMobilPayException $e) {
        // transient failure on our side → ask Netopia to retry
        return new Response(
            $ipn->errorResponse('cannot process', $ipn::ERROR_TYPE_TEMPORARY),
            200, ['Content-Type' => 'application/xml'],
        );
    }

    if ($result->isConfirmed()) {
        // SECURITY: decrypt() proves the sender is Netopia, NOT the amount.
        // Load the order by $result->purchaseId and confirm $result->processedAmount
        // matches what you expected BEFORE marking it paid.
    }

    return new Response(
        $ipn->confirmResponse(),
        200, ['Content-Type' => 'application/xml'],
    );
}
```

`IpnResult` predicates: `isConfirmed()`, `isPaid()`, `isPending()`, `isCanceled()`, `isError()`.
See [`src/Resources/doc/index.md`](./src/Resources/doc/index.md) for the full integration guide.

---

## Architecture at a glance

```
                 Symfony application
                         │
   ┌─────────────────────┼──────────────────────┐
   │ outbound            │            inbound    │
   ▼                     │                       ▼
NetopiaMobilPayService   │     NetopiaMobilPayIpnHandler
 (netopia_mobilpay.      │      (netopia_mobilpay.
        payment)         │            ipn_handler)
   │                     │                       │
   │   both depend on    ▼                       │
   │        NetopiaMobilPayConfiguration  ◄───────┘
   │        (netopia_mobilpay.configuration, private)
   │         · signature, certs, payment URL, routes
   ▼                                             ▼
        birkof/netopia-mobilpay  (Mobilpay\…)
   Request\Card / Request\Sms        Request\RequestAbstract::factoryFromEncrypted
   ::encrypt()  ──► openssl_seal     ::decrypt ──► openssl_open ──► Request\Notify
```

The SDK does the cryptography and XML; this bundle adapts it to Symfony DI and gives the inbound side a typed, vendor‑agnostic result.

---

## Components

Grouped by directory under `src/`:

### Bundle + DI

| File | Responsibility |
|---|---|
| `NetopiaMobilPayBundle.php` | The bundle. `const ALIAS = 'netopia_mobilpay'`, `const VERSION = '1.4.0'`; returns `NetopiaMobilPayExtension` from `getContainerExtension()`. |
| `DependencyInjection/Configuration.php` | Config tree (`getConfigTreeBuilder`): nodes `payment_url`, `public_cert`, `private_key`, `signature`. `signature` is `isRequired()->cannotBeEmpty()` — a missing signature fails config processing rather than booting with a placeholder. |
| `DependencyInjection/NetopiaMobilPayExtension.php` | Loads `services.yaml`, processes config, and registers the service graph (below). `inflateServicesInConfig()` turns any `@service`‑prefixed config string into a `Reference`. Secrets are passed to the configuration object **only via method calls** — they are never written to container parameters (which Symfony would dump to the compiled‑container cache in cleartext). |

**Service graph built by the extension:**

```
netopia_mobilpay.configuration   (NetopiaMobilPayConfiguration, private)
    ← Reference('router')
    ← setPaymentUrl, setProjectDir('%kernel.project_dir%'),
      setPublicCert, setPrivateKey, setSignature
        │
        ├─► netopia_mobilpay.payment       (NetopiaMobilPayService, public)
        │       ← configuration, router, logger
        │
        └─► netopia_mobilpay.ipn_handler   (NetopiaMobilPayIpnHandler, public)
                ← configuration, logger
```

`Resources/config/services.yaml` aliases each interface to its service for autowiring:
`NetopiaMobilPayServiceInterface → netopia_mobilpay.payment`,
`NetopiaMobilPayIpnHandlerInterface → netopia_mobilpay.ipn_handler`.

### Configuration value object

`Configuration/NetopiaMobilPayConfiguration.php` — runtime holder for the gateway settings, built once and shared by both services.

- Reads `public_cert` / `private_key` as **either a file path (resolved under `%kernel.project_dir%`) or the literal PEM content** (`setPublicCert`/`setPrivateKey` fall back to the raw value when it is not a readable file).
- In its constructor it generates the absolute **confirm** and **return** URLs via the router from two route names it expects the application to define:
  - `netopia_mobilpay_confirm_url` (`const CONFIRM_URL`)
  - `netopia_mobilpay_return_url` (`const RETURN_URL`)
- `resolvePaymentUrl(bool $useTokenEndpoint)` derives the per‑request endpoint from an **immutable base** URL: the base for a normal payment, `base + '/card4'` for a token payment. The base is never mutated, so repeated/token calls cannot accumulate `/card4` or leak across requests on long‑running workers.
- Currency constants: `CURRENCY_RON`, `CURRENCY_EUR`, `CURRENCY_USD`.

### Outbound — payment request building

`Service/NetopiaMobilPayServiceInterface.php` + `NetopiaMobilPayService.php`.

- `createCreditCardPaymentObject($orderId, $amount, $currency, $details, $billingAddress, $shippingAddress, $creditCard, $extraParameters)`:
  1. validates `orderId` (non‑empty), `amount` (positive numeric) and `currency` (one of the `CURRENCY_*` constants) **before** the try/catch, so a specific input error is not masked;
  2. builds a `Mobilpay\Payment\Request\Card` — `signature`, `confirmUrl`, `returnUrl`, a `Mobilpay\Payment\Invoice` (currency/amount/details), optional billing/shipping `Mobilpay\Payment\Address`, optional `Mobilpay\Payment\Instrument\Card`, and token `extraParameters`;
  3. resolves the payment URL for the request;
  4. calls `->encrypt($publicCert)` (RSA envelope) and returns the request object.
- `createSmsPaymentObject($orderId, $serviceId)` — the SMS‑payment equivalent.
- `composeAddressObject()` sets only the fields the SDK's `Address` actually serializes (`type`, `firstName`, `lastName`, `address`, `email`, `mobilePhone`).
- `composeCreditCardObject()` builds a raw‑PAN `CardInstrument`. **Its docblock warns that this server‑side card path places the application in PCI‑DSS SAQ‑D scope;** the hosted payment page (leave `$creditCard` empty) is the recommended flow.

The returned request object exposes the sealed `env_key` / `data`; the application renders an auto‑submitting HTML form POSTing them to `getPaymentUrl()`.

### Inbound — IPN handling (`src/Notification/`)

| File | Responsibility |
|---|---|
| `IpnAction.php` | Backed `enum IpnAction: string` of gateway actions (`confirmed`, `confirmed_pending`, `paid_pending`, `paid`, `canceled`, `credit`) plus an `Unknown` fallback. `fromValue(?string)` maps unrecognized/null to `Unknown` (forward‑compatible — never throws on a new action). |
| `IpnResult.php` | `final readonly` DTO — an immutable, vendor‑agnostic view of a decrypted notification. `fromNotify(Notify)` maps the SDK object (money kept as string, `errorCode` cast to int). Predicates: `isError()`, `isConfirmed()`, `isPaid()`, `isPending()`, `isCanceled()`. |
| `NetopiaMobilPayIpnHandlerInterface.php` / `NetopiaMobilPayIpnHandler.php` | `decrypt(string $envKey, string $encData, ?string $cipher = null, ?string $iv = null): IpnResult` opens the envelope via `RequestAbstract::factoryFromEncrypted()` using the configured private key, then returns `IpnResult::fromNotify()`. `confirmResponse()` / `errorResponse($message, $errorType, $errorCode)` build the `<crc>` reply with `DOMDocument` (values XML‑escaped). Constants `ERROR_TYPE_TEMPORARY = 1` (gateway retries) and `ERROR_TYPE_PERMANENT = 2`. Decrypt failures log only the error **code** (never the vendor message, key material, or ciphertext) and surface a generic `NetopiaMobilPayException`. |

### Errors

`Exception/NetopiaMobilPayException.php` — the single `extends \Exception` type thrown by the bundle (invalid input, failed encryption, failed/empty/garbage IPN payload).

All `src/` classes declare `declare(strict_types=1)`.

---

## Request / response flows

**Outbound (start a payment):**

```
app ──► NetopiaMobilPayServiceInterface::createCreditCardPaymentObject(...)
          → validate input → build Card(Invoice, [Address], [CardInstrument], [token])
          → resolvePaymentUrl()          → Card::encrypt(public_cert)  [openssl_seal]
        ◄ returns sealed request (env_key + data)
app renders auto-submit <form> POST → getPaymentUrl()   ──► Netopia hosted page
```

**Inbound (gateway confirmation / IPN):**

```
Netopia ──POST env_key,data[,cipher,iv]──► your route `netopia_mobilpay_confirm_url`
your controller ──► NetopiaMobilPayIpnHandlerInterface::decrypt(...)
          factoryFromEncrypted(private_key)  [openssl_open] → Notify → IpnResult
your controller verifies purchaseId + processedAmount against YOUR order, fulfils,
          then returns confirmResponse()  ──► "<crc></crc>"
          (on a transient failure: errorResponse(..., ERROR_TYPE_TEMPORARY) → gateway retries)
```

---

## Security model

- **Authenticity is the RSA envelope.** Netopia seals the IPN against your public certificate; only the holder of the matching private key can `openssl_open` it. A successful `decrypt()` therefore authenticates the sender — there is no separate signature on the callback.
- **The bundle cannot verify the amount/order.** It proves the message came from Netopia; it does not know what you expected. The consumer **must** cross‑check `IpnResult::$purchaseId` against a real, unfulfilled order and confirm `IpnResult::$processedAmount` before fulfilling. This boundary is documented in `src/Resources/doc/index.md`.
- **Secrets stay out of the compiled container.** The extension passes `private_key`/`signature` to the configuration object via method calls, not container parameters.
- **No card data by default.** The hosted‑page flow keeps PAN/CVV off your server; the raw‑card path is documented as PCI SAQ‑D scope.

---

## Project layout

```
src/
├── NetopiaMobilPayBundle.php              # bundle entry point
├── Configuration/
│   └── NetopiaMobilPayConfiguration.php   # runtime settings value object
├── DependencyInjection/
│   ├── Configuration.php                  # config tree (signature required)
│   └── NetopiaMobilPayExtension.php       # service graph wiring
├── Exception/
│   └── NetopiaMobilPayException.php
├── Notification/                          # inbound IPN
│   ├── IpnAction.php
│   ├── IpnResult.php
│   ├── NetopiaMobilPayIpnHandlerInterface.php
│   └── NetopiaMobilPayIpnHandler.php
├── Service/                               # outbound requests
│   ├── NetopiaMobilPayServiceInterface.php
│   └── NetopiaMobilPayService.php
└── Resources/
    ├── config/services.yaml               # interface → service aliases
    └── doc/index.md                       # usage / integration guide

tests/                                     # mirrors src/ (Configuration, DependencyInjection,
                                           # Notification, Service)
```

---

## Configuration reference

Defined in `config/packages/netopia_mobilpay.yaml` (see `src/Resources/doc/index.md` for full examples):

| Key | Required | Default | Notes |
|---|---|---|---|
| `signature` | **yes** | — | merchant signature; processing fails if absent |
| `payment_url` | no | `http://sandboxsecure.mobilpay.ro` | gateway base URL (set the production URL in prod) |
| `public_cert` | no | `null` | file path **or** inline PEM; used to seal outbound requests |
| `private_key` | no | `null` | file path **or** inline PEM; used to open inbound IPNs |

The application must also define two routes the configuration generates URLs for: **`netopia_mobilpay_confirm_url`** (server‑to‑server IPN) and **`netopia_mobilpay_return_url`** (browser return).

Inject by interface:

```php
public function __construct(
    private NetopiaMobilPayServiceInterface $payments,        // outbound
    private NetopiaMobilPayIpnHandlerInterface $ipn,          // inbound
) {}
```

---

## Testing & CI

- **PHPUnit 11** (`phpunit.xml.dist`): bootstraps `vendor/autoload.php`, runs the `tests/` suite, declares `src/` as the coverage source, and is strict — `failOnWarning="true"`, `failOnRisky="true"`. Run with `vendor/bin/phpunit` (or `composer test`).
- Current suite: **33 tests / 134 assertions**, mirroring `src/` (config tree, DI wiring, value object, outbound service, IPN enum/DTO/handler). The IPN handler test performs a real `openssl_seal` → `decrypt` round‑trip with a generated key pair.
- **CI** (`.github/workflows/ci.yml`): on push to `main` and on pull requests; matrix **PHP 8.3 and 8.4** (extensions `openssl, mbstring, dom`); steps: `composer validate --strict`, install, `vendor/bin/phpunit`, and a non‑blocking `composer audit`.

---

## License

MIT — see [`LICENSE.md`](./LICENSE.md).
