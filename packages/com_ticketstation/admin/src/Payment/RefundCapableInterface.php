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
 * A provider that can refund payments and report refunds and chargebacks. Without it only
 * manual refunds (registered by hand) are possible for its payments.
 */
interface RefundCapableInterface
{
    /**
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function canRefund(string $providerPaymentId): RefundCheck;

    /**
     * Refunds an amount of a payment.
     *
     * @param   array  $meta  Extra data to keep with the refund at the provider (source, ordercode).
     *
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function createRefund(string $providerPaymentId, float $amount, string $description, array $meta): ProviderRefund;

    /**
     * The refunds and chargebacks of a payment.
     *
     * @return  ProviderRefund[]
     *
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function getRefunds(string $providerPaymentId): array;

    /**
     * The latest refunds and chargebacks of the whole account, for a provider that doesn't report
     * every change by webhook. The core may call it every few minutes; [] when not supported.
     *
     * @return  ProviderRefund[]
     *
     * @throws  ProviderNotConfiguredException
     * @throws  PaymentException
     */
    public function pollRecent(): array;
}
