# Netopia MobilPay Payment Gateway Symfony Bundle

> This bundle provides an easy way to integrate MobilPay.ro Payment Gateway into your Symfony application.


## Compatibility

It's compatible with Symfony 3.4 LTS and Symfony 4.0 (and later).


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



## Documentation

The bulk of the documentation is stored in the `./src/Resources/doc/index.md` file in this bundle:

[Read the Documentation](./src/Resources/doc/index.md)


## License

This bundle is under the MIT license. See the complete license in the bundle:

[Read the License](./LICENSE.md)
