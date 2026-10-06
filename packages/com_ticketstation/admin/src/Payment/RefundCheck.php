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
 * Whether, and how much of, a payment can still be refunded.
 */
final class RefundCheck
{
    public function __construct(
        public readonly bool $refundable,
        public readonly float $amountRemaining,
        public readonly string $currency,
    ) {
    }
}
