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

namespace birkof\NetopiaMobilPay;

use birkof\NetopiaMobilPay\DependencyInjection\NetopiaMobilPayExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class NetopiaMobilPayBundle
 * @package birkof\NetopiaMobilPay
 */
class NetopiaMobilPayBundle extends Bundle
{
    const VERSION = '1.2.1';
    const ALIAS = 'netopia_mobilpay';

    public function getContainerExtension()
    {
        return new NetopiaMobilPayExtension();
    }
}
