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
 * A payment provider, offered to Ticketstation by a plugin of the group "ticketstationpayment"
 * (see CollectProvidersEvent). The core owns the order: it decides what a payment means for an
 * order, its tickets and its mails. A provider only talks to the payment service and reports back
 * in the value objects of this namespace.
 *
 * Optional abilities are separate interfaces (RefundCapableInterface, MethodAwareInterface,
 * TestModeAwareInterface for a service with a test environment).
 * Whether a provider exists is the plugin's published state; which provider takes new payments is
 * a setting of the core. A provider keeps its own settings in the parameters of its plugin.
 */
interface PaymentProviderInterface
{
    /**
     * Version of the provider API, raised when this interface or the value objects change in a
     * way that breaks a provider.
     */
    public const API_VERSION = 1;

    /**
     * Machine name, stored with payments: lower case letters, digits and underscores. Use the
     * element name of the plugin.
     */
    public function getId(): string;

    /**
     * Name for the admin.
     */
    public function getTitle(): string;

    /**
     * Whether the provider has what it needs to take real payments (live credentials), for the
     * setup steps on the control panel.
     */
    public function isConfigured(): bool;

    /**
     * Whether an order is marked as waiting for payment (paid = 3) as soon as the customer is
     * sent to the provider.
     */
    public function marksOrderPendingOnStart(): bool;

    /**
     * Problems the admin should know about, for "Needs attention" on the control panel. Only
     * asked of the provider that takes new payments. The plugin loads its own language files.
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
