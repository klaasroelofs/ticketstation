<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

/**
 * The currencies an admin may charge in through Mollie, stored as an ISO 4217 code in
 * #__ticketstation_mollie.currency and passed as the amount's currency when a payment is
 * created. The list is limited to currencies Mollie accepts with two decimals, which is
 * how PaymentController formats the amount. The symbol shown next to prices is the
 * separate "valuta" setting in the Configuration.
 */
class MollieCurrencies
{
    /**
     * ISO code => name.
     */
    public const CURRENCIES = [
        'EUR' => 'Euro',
        'GBP' => 'British pound',
        'CHF' => 'Swiss franc',
        'USD' => 'US dollar',
        'DKK' => 'Danish krone',
        'SEK' => 'Swedish krona',
        'NOK' => 'Norwegian krone',
        'PLN' => 'Polish złoty',
        'CZK' => 'Czech koruna',
        'HUF' => 'Hungarian forint',
    ];

    public const DEFAULT = 'EUR';

    /**
     * Payment methods Mollie only offers for payments in euros.
     */
    public const EURO_ONLY_METHODS = ['ideal', 'bancontact', 'kbc', 'belfius', 'giftcard'];

    /**
     * The currency to charge in, from the stored value.
     */
    public static function fromConfig(?string $stored): string
    {
        $currency = strtoupper(trim((string) $stored));

        return isset(self::CURRENCIES[$currency]) ? $currency : self::DEFAULT;
    }

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
