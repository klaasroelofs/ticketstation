<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\Input\Input;

defined('_JEXEC') or die;

/**
 * A payment provider. The core owns the order: it decides what a payment means for an order,
 * its tickets and its mails. A provider only talks to the payment service and reports back in
 * the value objects of this namespace.
 *
 * Optional abilities are separate interfaces (RefundCapableInterface, MethodAwareInterface).
 */
interface PaymentProviderInterface
{
    /**
     * Version of the provider API, raised when this interface or the value objects change in a
     * way that breaks a provider.
     */
    public const API_VERSION = 1;

    /**
     * Machine name, stored with payments: lower case letters, digits and underscores.
     */
    public function getId(): string;

    /**
     * Name for the admin.
     */
    public function getTitle(): string;

    /**
     * The "Online payments" switch: whether customers can pay through this provider.
     */
    public function isEnabled(): bool;

    /**
     * Whether the provider has what it needs to take real payments (live credentials), for the
     * setup steps on the control panel.
     */
    public function isConfigured(): bool;

    /**
     * Whether the provider is in test mode. Test mode is for staff only.
     */
    public function isTestMode(): bool;

    /**
     * ISO 4217 code of the currency payments are made in.
     */
    public function getCurrency(): string;

    /**
     * Whether an order is marked as waiting for payment (paid = 3) as soon as the customer is
     * sent to the provider.
     */
    public function marksOrderPendingOnStart(): bool;

    /**
     * The screen where the admin sets the provider up, as a Joomla route.
     */
    public function getSettingsLink(): string;

    /**
     * Problems the admin should know about, for "Needs attention" on the control panel.
     * Only while the provider is enabled.
     *
     * @return  array  Items of ['key' => language key, 'level' => 'danger'|'warning'].
     */
    public function getHealthWarnings(): array;

    /**
     * Starts a payment.
     *
     * @throws  ProviderNotConfiguredException  when there are no credentials.
     * @throws  PaymentException                when the provider refuses the payment or can't be reached.
     */
    public function createPayment(PaymentRequest $request): PaymentRedirect;

    /**
     * Reads a webhook call and finds out what it is about. Verifying it is genuine is the
     * provider's job (Mollie: asking the payment from Mollie with its own key; others: a signature
     * over $rawBody).
     *
     * @throws  PaymentException  when the call is not genuine or the payment can't be retrieved.
     */
    public function handleWebhook(Input $input, string $rawBody): PaymentUpdate;
}
