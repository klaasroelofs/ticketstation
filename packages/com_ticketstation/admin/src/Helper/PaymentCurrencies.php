<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Ticketstation\Component\Ticketstation\Administrator\Payment\CurrencyAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentProviderInterface;

defined('_JEXEC') or die('Restricted access');

/**
 * The currencies an admin may charge in, stored as an ISO 4217 code in
 * #__ticketstation_config.payment_currency, passed to the payment provider as the currency of
 * every payment and used for the structured data of events. The list is limited to currencies with
 * two decimals, which is how a payment amount is formatted (JPY has none, KWD and BHD have three).
 * A provider can narrow it down with CurrencyAwareInterface. The symbol shown next to prices is the
 * separate "valuta" setting in the Configuration.
 */
class PaymentCurrencies
{
    /**
     * ISO code => name.
     */
    public const CURRENCIES = [
        'EUR' => 'Euro',
        'GBP' => 'British pound',
        'USD' => 'US dollar',
        'CHF' => 'Swiss franc',
        'DKK' => 'Danish krone',
        'SEK' => 'Swedish krona',
        'NOK' => 'Norwegian krone',
        'PLN' => 'Polish złoty',
        'CZK' => 'Czech koruna',
        'HUF' => 'Hungarian forint',
        'RON' => 'Romanian leu',
        'BGN' => 'Bulgarian lev',
        'TRY' => 'Turkish lira',
        'SAR' => 'Saudi riyal',
        'AED' => 'UAE dirham',
        'QAR' => 'Qatari riyal',
        'EGP' => 'Egyptian pound',
        'ILS' => 'Israeli new shekel',
        'ZAR' => 'South African rand',
        'CAD' => 'Canadian dollar',
        'AUD' => 'Australian dollar',
        'NZD' => 'New Zealand dollar',
        'HKD' => 'Hong Kong dollar',
        'SGD' => 'Singapore dollar',
        'CNY' => 'Chinese yuan',
        'INR' => 'Indian rupee',
        'MYR' => 'Malaysian ringgit',
        'PHP' => 'Philippine peso',
        'THB' => 'Thai baht',
        'TWD' => 'New Taiwan dollar',
        'BRL' => 'Brazilian real',
        'MXN' => 'Mexican peso',
    ];

    public const DEFAULT = 'EUR';

    /**
     * The currency to charge in, from the stored value.
     */
    public static function fromConfig(?string $stored): string
    {
        $currency = strtoupper(trim((string) $stored));

        return isset(self::CURRENCIES[$currency]) ? $currency : self::DEFAULT;
    }

    /**
     * The currencies a provider can be chosen for: its own list of supported currencies within
     * CURRENCIES, or all of CURRENCIES when it doesn't say.
     *
     * @return  array  ISO code => name
     */
    public static function forProvider(?PaymentProviderInterface $provider): array
    {
        if (!$provider instanceof CurrencyAwareInterface) {
            return self::CURRENCIES;
        }

        return array_intersect_key(self::CURRENCIES, array_flip(array_map('strtoupper', $provider->getSupportedCurrencies())));
    }
}
