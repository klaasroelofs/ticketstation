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
 * The currencies an admin may charge in, stored as an ISO 4217 code in
 * #__ticketstation_config.payment_currency, passed to the payment provider as the currency of
 * every payment and used for the structured data of events. The list is limited to currencies with
 * two decimals, which is how a payment amount is formatted. The symbol shown next to prices is the
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
     * The currency to charge in, from the stored value.
     */
    public static function fromConfig(?string $stored): string
    {
        $currency = strtoupper(trim((string) $stored));

        return isset(self::CURRENCIES[$currency]) ? $currency : self::DEFAULT;
    }
}
