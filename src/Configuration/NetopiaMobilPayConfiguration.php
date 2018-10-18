<?php
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

    const RETURN_URL = 'netopia_mobilpay_return_url';
    const CONFIRM_URL = 'netopia_mobilpay_confirm_url';

    /** @var RouterInterface */
    private $router;

    /** @var string */
    private $projectDir;

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

        $this->setConfirmUrl(self::CONFIRM_URL);
        $this->setReturnUrl(self::RETURN_URL);
    }

    /**
     * @return string
     */
    public function getProjectDir()
    {
        return $this->projectDir;
    }

    /**
     * @param string $projectDir
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setProjectDir($projectDir)
    {
        $this->projectDir = $projectDir.'/';

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }

    /**
     * @param string $paymentUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPaymentUrl($paymentUrl)
    {
        $this->paymentUrl = $paymentUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getPublicCert()
    {
        return $this->publicCert;
    }

    /**
     * @param string $publicCert
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPublicCert($publicCert)
    {
        $publicCertFile = $this->composeFilePath($publicCert);
        $this->publicCert = is_file($publicCertFile) && is_readable($publicCertFile) ? file_get_contents($publicCertFile) : $publicCert;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * @param string $privateKey
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setPrivateKey($privateKey)
    {
        $privateKeyFile = $this->composeFilePath($privateKey);
        $this->privateKey = is_file($privateKeyFile) && is_readable($privateKeyFile) ? file_get_contents($privateKeyFile) : $privateKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * @return string
     */
    public function getConfirmUrl()
    {
        return $this->confirmUrl;
    }

    /**
     * @param string $confirmUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setConfirmUrl($confirmUrl)
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
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * @param string $returnUrl
     *
     * @return NetopiaMobilPayConfiguration
     */
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $this->router->generate(
            $returnUrl,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this;
    }

    /**
     * @param $file
     *
     * @return string
     */
    protected function composeFilePath($file)
    {
        return $this->projectDir.ltrim($file, '/');
    }
}
