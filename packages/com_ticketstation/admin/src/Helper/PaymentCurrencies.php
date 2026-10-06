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
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

defined('_JEXEC') or die('Restricted access');

/**
 * The currencies an admin may charge in, stored as an ISO 4217 code in
 * #__ticketstation_config.payment_currency, passed to the payment provider as the currency of
 * every payment and used for the structured data of events. The list holds currencies with two
 * decimals and a few without (JPY, ISK, KRW, VND, CLP); currencies with three decimals (KWD, BHD,
 * OMR) are not offered, as prices are stored with two decimals. A provider can narrow the list down
 * with CurrencyAwareInterface. The symbol shown next to prices is the separate "valuta" setting in
 * the Configuration, and how many decimals prices show is "Prices" there.
 *
 * The number of decimals decides how order totals, discounts, service fees and VAT are rounded, and
 * how a payment amount is written: "12.50" in euros, "1250" in yen.
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
        'JPY' => 'Japanese yen',
        'ISK' => 'Icelandic króna',
        'KRW' => 'South Korean won',
        'VND' => 'Vietnamese dong',
        'CLP' => 'Chilean peso',
    ];

    public const DEFAULT = 'EUR';

    /**
     * The currencies without decimals; every other currency in CURRENCIES has two.
     */
    public const NO_DECIMALS = ['JPY', 'ISK', 'KRW', 'VND', 'CLP'];

    /**
     * The currency to charge in, from the stored value.
     */
    public static function fromConfig(?string $stored): string
    {
        $currency = strtoupper(trim((string) $stored));

        return isset(self::CURRENCIES[$currency]) ? $currency : self::DEFAULT;
    }

    /**
     * The number of decimals of a currency: 0 or 2.
     */
    public static function digits(string $currency): int
    {
        return in_array(strtoupper($currency), self::NO_DECIMALS, true) ? 0 : 2;
    }

    /**
     * The number of decimals of the currency Ticketstation charges in.
     */
    public static function decimals(): int
    {
        return self::digits(ProviderRegistry::currency());
    }

    /**
     * An amount as a payment provider wants it: rounded to the decimals of the currency, with a
     * point and no thousands separator ("12.50", or "1250" for yen).
     */
    public static function format(float $amount, string $currency): string
    {
        return number_format($amount, self::digits($currency), '.', '');
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
