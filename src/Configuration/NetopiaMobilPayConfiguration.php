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

namespace birkof\NetopiaMobilPay\Configuration;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class NetopiaMobilPayConfiguration
 * @package birkof\NetopiaMobilPay\Configuration
 */
final class NetopiaMobilPayConfiguration
{
    const CURRENCY_RON = 'RON';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';

    /** @var RouterInterface */
    private $router;

    /** @var string */
    private $paymentUrl;

    /** @var string */
    private $publicCert;

    /** @var string */
    private $privateKey;

    /** @var string */
    private $signature;

    /** @var string */
    private $confirmUrl;

    /** @var string */
    private $returnUrl;

    /**
     * NetopiaMobilPayConfiguration constructor.
     *
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @return string
     */
    public function getPaymentUrl(): string
    {
        return $this->paymentUrl;
    }

    /**
     * @param string $paymentUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPaymentUrl(string $paymentUrl): NetopiaMobilPayConfiguration
    {
        $this->paymentUrl = $paymentUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getPublicCert(): string
    {
        return $this->publicCert;
    }

    /**
     * @param string $publicCert
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPublicCert(string $publicCert): NetopiaMobilPayConfiguration
    {
        $this->publicCert = $publicCert;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    /**
     * @param string $privateKey
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPrivateKey(string $privateKey): NetopiaMobilPayConfiguration
    {
        $this->privateKey = $privateKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setSignature(string $signature): NetopiaMobilPayConfiguration
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * @return string
     */
    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    /**
     * @param string $confirmUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setConfirmUrl(string $confirmUrl): NetopiaMobilPayConfiguration
    {
        $this->confirmUrl = $this->router->generate(
            $confirmUrl,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    /**
     * @param string $returnUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setReturnUrl(string $returnUrl): NetopiaMobilPayConfiguration
    {
        $this->returnUrl = $this->router->generate(
            $returnUrl,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this;
    }
}
