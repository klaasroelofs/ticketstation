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
    /** @var object|null The notation settings, read once per request */
    private static ?object $settings = null;

    /**
     * An amount in the configured notation, with the currency symbol of the configuration.
     */
    public static function _($price)
    {
        $config = (new Config)->getPartialConfig(['valuta']);

        return self::format($price, (string) ($config->valuta ?? ''));
    }

    /**
     * An amount in the notation of Configuration > Prices: decimals, decimal point, thousands
     * separator and the place of the currency symbol.
     *
     * @param   mixed   $price     The amount
     * @param   string  $currency  The currency symbol
     */
    public static function format($price, $currency): string
    {
        $settings = self::settings();
        $price    = (float) $price;
        $decimals = (int) $settings->price_decimals;

        // "Whole amounts without decimals": 12 stays 12, 12.5 becomes 12.50
        if ($decimals < 0) {
            $decimals = abs($price - round($price)) < 0.005 ? 0 : 2;
        }

        $number   = number_format($price, $decimals, (string) $settings->price_decimal_sep, (string) $settings->price_thousands_sep);
        $currency = trim((string) $currency);

        if ($currency === '') {
            return $number;
        }

        return $settings->price_symbol_after ? $number . ' ' . $currency : $currency . ' ' . $number;
    }

    private static function settings(): object
    {
        if (self::$settings === null) {
            $settings = (new Config)->getPartialConfig(['price_decimals', 'price_decimal_sep', 'price_thousands_sep', 'price_symbol_after']);

            self::$settings = (object) [
                'price_decimals'      => $settings->price_decimals ?? 2,
                'price_decimal_sep'   => $settings->price_decimal_sep ?? ',',
                'price_thousands_sep' => $settings->price_thousands_sep ?? '.',
                'price_symbol_after'  => $settings->price_symbol_after ?? 0,
            ];
        }

        return self::$settings;
    }
}
