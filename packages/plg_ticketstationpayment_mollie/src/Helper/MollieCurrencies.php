<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_mollie
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Mollie\Helper;

defined('_JEXEC') or die;

/**
 * What Mollie offers per currency. The currency itself is a setting of Ticketstation.
 */
class MollieCurrencies
{
    /**
     * The currencies with two decimals that Mollie accepts for payments. Each must also be enabled
     * for the Mollie account.
     */
    public const SUPPORTED = [
        'EUR', 'GBP', 'USD', 'CHF', 'DKK', 'SEK', 'NOK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'ILS', 'AED',
        'ZAR', 'CAD', 'AUD', 'NZD', 'HKD', 'SGD', 'MYR', 'PHP', 'THB', 'TWD', 'BRL', 'MXN',
    ];

    /**
     * Payment methods Mollie only offers for payments in euros.
     */
    public const EURO_ONLY_METHODS = ['ideal', 'bancontact', 'kbc', 'belfius', 'giftcard'];

    /**
     * Whether Mollie offers a payment method for payments in this currency.
     */
    public static function supportsMethod(string $currency, string $method): bool
    {
        return $currency === 'EUR' || !in_array($method, self::EURO_ONLY_METHODS, true);
    }

    /**
     * Keeps the methods Mollie offers for payments in this currency.
     */
    public static function filterMethods(string $currency, array $methods): array
    {
        return array_values(array_filter($methods, fn ($method) => self::supportsMethod($currency, $method)));
    }
}
