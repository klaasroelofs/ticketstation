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
 * A refund or chargeback of a payment, as reported by a provider.
 */
final class ProviderRefund
{
    public const REFUND     = 'refund';
    public const CHARGEBACK = 'chargeback';

    /**
     * @param   string  $id                 The provider's id of the refund or chargeback.
     * @param   string  $paymentId          The provider's id of the payment it belongs to.
     * @param   string  $type               REFUND or CHARGEBACK.
     * @param   float   $amount
     * @param   string  $currency           ISO 4217 code.
     * @param   string  $description
     * @param   string  $status             queued, pending, processing, refunded, failed, cancelled, charged_back or reversed.
     * @param   string  $createdAt          Any date string; empty for now.
     * @param   bool    $fromTicketstation  Whether Ticketstation made it (as opposed to the provider's dashboard).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $paymentId,
        public readonly string $type,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $status,
        public readonly string $createdAt = '',
        public readonly bool $fromTicketstation = false,
    ) {
    }
}
