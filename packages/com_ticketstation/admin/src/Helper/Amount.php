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
 * Splits a ticket price including VAT into its parts. The amounts of an order (tickets,
 * discount, service fee, VAT) come from OrderTotals.
 */
class Amount
{
    /**
     * Returns an array with data for the price.
     *
     * @param $price
     * @param $vat_amt
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function calculateVatFromPrice($price, $vat_amt)
    {
        return [
            'vat_amount'          => $price / (100 + $vat_amt) * $vat_amt,
            'vat_percentage'      => $vat_amt,
            'price_excluding_vat' => $price - ($price / (100 + $vat_amt) * $vat_amt),
        ];
    }
}
