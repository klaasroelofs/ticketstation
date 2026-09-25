<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access

defined('_JEXEC') or die('Restricted access');

class Price
{
    public static function _($price)
    {
        $config = (new Config)->getPartialConfig(['priceformat', 'valuta']);

        if ($config->priceformat == 1)
        {
            $price = $config->valuta . ' ' . number_format($price, 2, ',', '.');
        }

        if ($config->priceformat == 2)
        {
            $price = number_format($price, 2, ',', '.') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 3)
        {
            $price = $config->valuta . ' ' . number_format($price, 2, ',', '');
        }
        if ($config->priceformat == 4)
        {
            $price = number_format($price, 2, ',', '') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 5)
        {
            $price = $config->valuta . ' ' . number_format($price, 2, '.', '');
        }
        if ($config->priceformat == 6)
        {
            $price = number_format($price, 2, '.', '') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 7)
        {
            $price = number_format($price, '2', '.', ',') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 8)
        {
            $price = $config->valuta . ' ' . number_format($price, '2', '.', ',');
        }
        if ($config->priceformat == 9)
        {
            $price = number_format($price, '0', '', ',') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 10)
        {
            $price = $config->valuta . ' ' . number_format($price, '0', '', ',');
        }
        if ($config->priceformat == 11)
        {
            $price = number_format($price, '0', '', '.') . ' ' . $config->valuta;
        }
        if ($config->priceformat == 12)
        {
            $price = $config->valuta . ' ' . number_format($price, '0', '', '.');
        }

        return $price;
    }
}