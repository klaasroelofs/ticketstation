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
 * What the core asks a provider to collect for an order. The provider adds its own settings
 * (description, language, methods) to it.
 */
final class PaymentRequest
{
    /**
     * @param   int     $ordercode   The order the payment is for.
     * @param   string  $amount      The amount with two decimals and a point, e.g. "12.50".
     * @param   string  $currency    ISO 4217 code.
     * @param   string  $returnUrl   Where the provider sends the customer back to.
     * @param   string  $webhookUrl  Where the provider reports the outcome.
     */
    public function __construct(
        public readonly int $ordercode,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $returnUrl,
        public readonly string $webhookUrl,
    ) {
    }
}
