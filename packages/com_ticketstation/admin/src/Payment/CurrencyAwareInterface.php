<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

defined('_JEXEC') or die;

/**
 * A provider that can only collect payments in some currencies. The currency is one setting of
 * Ticketstation (prices, invoices and structured data use it too); this interface tells the
 * Payments screen which currencies the provider can be chosen for. A provider without it is
 * offered every currency in PaymentCurrencies::CURRENCIES.
 */
interface CurrencyAwareInterface
{
    /**
     * The ISO 4217 codes of the currencies the provider can collect, a subset of the keys of
     * PaymentCurrencies::CURRENCIES (currencies with two decimals); other codes are ignored.
     *
     * @return  string[]
     */
    public function getSupportedCurrencies(): array;
}
