<?php
/*
 * This file is part of the NetopiaMobilPayBundle.
 *
 * (c) Daniel STANCU <birkof@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace birkof\NetopiaMobilPay\DependencyInjection;

use birkof\NetopiaMobilPay\NetopiaMobilPayBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package birkof\NetopiaMobilPay\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(NetopiaMobilPayBundle::ALIAS);

        $rootNode
            ->children()
            ->scalarNode('payment_url')->defaultValue('http://sandboxsecure.mobilpay.ro')->end()
            ->scalarNode('public_cert')->defaultNull()->end()
            ->scalarNode('private_key')->defaultNull()->end()
            ->scalarNode('signature')->cannotBeEmpty()->defaultValue('XXXX-XXXX-XXXX-XXXX-XXXX')->end();

        return $treeBuilder;
    }
}
