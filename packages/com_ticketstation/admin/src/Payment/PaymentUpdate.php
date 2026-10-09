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
 * What a provider knows about a payment, in the provider-neutral form the core's order handling
 * works with. The provider has checked it is genuine; the core decides what it means for the order.
 */
final class PaymentUpdate
{
    public const PAID      = 'paid';
    public const OPEN      = 'open';
    public const PENDING   = 'pending';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED   = 'expired';

    /** Anything else, such as Mollie's "authorized": handled as a failed attempt. */
    public const UNKNOWN   = 'unknown';

    /**
     * A genuine report that is not about a payment of an order (a service that sends many kinds
     * of events): answered with 200 and otherwise ignored. The other fields are not used.
     */
    public const IGNORE    = 'ignore';

    /**
     * @param   int     $ordercode          The order the payment belongs to.
     * @param   string  $providerPaymentId  The provider's id of the payment.
     * @param   string  $state              One of the constants above.
     * @param   string  $amount             What was paid, with two decimals and a point.
     * @param   string  $currency           ISO 4217 code.
     * @param   string  $method             The payment method as the provider calls it (stored on the transaction).
     * @param   string  $details            The provider's own data about the payment, stored with the transaction.
     * @param   bool    $hasRefunds         Whether the payment has refunds or chargebacks.
     */
    public function __construct(
        public readonly int $ordercode,
        public readonly string $providerPaymentId,
        public readonly string $state,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $method,
        public readonly string $details,
        public readonly bool $hasRefunds = false,
    ) {
    }
}
