<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_example
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Example\Plugin\TicketstationPayment\Example\Provider;

defined('_JEXEC') or die;

use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentProviderInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRedirect;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRequest;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentUpdate;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderNotConfiguredException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\TestModeAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\WebhookRejectedException;

/**
 * An example provider that shows the whole contract without a real payment service.
 *
 * The "payment page" is the return address itself: the customer comes straight back to the site and
 * waits on the "checking your payment" page. The "payment service" is you: report the payment with
 * a signed webhook call, for instance with curl (see developer/payment-plugins.md):
 *
 *   body      {"id":"ex_1","order":26001,"status":"paid","amount":"12.50","currency":"EUR","method":"example"}
 *   header    X-Signature: hash_hmac('sha256', <the body>, <the secret of the plugin>)
 *   address   index.php?option=com_ticketstation&controller=payment&task=webhook&provider=example
 *
 * A real provider replaces createPayment() with a call to the payment service, and
 * handleWebhook() with the way that service proves a report is genuine.
 *
 * The required interface plus TestModeAwareInterface, as the example pretends its payment service has
 * a test environment. Leave TestModeAwareInterface out when yours has none: while the shop is in test
 * mode Ticketstation then does not send paid orders to your provider. Add RefundCapableInterface to
 * make and report refunds and chargebacks, and MethodAwareInterface to let the customer choose a
 * payment method.
 */
final class ExampleProvider implements PaymentProviderInterface, TestModeAwareInterface
{
    private Registry $params;

    private bool $testMode;

    /**
     * @param   bool  $testMode  Whether the shop is in test mode: the plugin passes on what it got
     *                           from CollectProvidersEvent::isTestMode().
     */
    public function __construct(Registry $params, bool $testMode = false)
    {
        $this->params   = $params;
        $this->testMode = $testMode;
    }

    /**
     * Stored with every payment. Use the element name of the plugin.
     */
    public function getId(): string
    {
        return 'example';
    }

    public function getTitle(): string
    {
        return 'Example';
    }

    /**
     * Live credentials present: the control panel's setup step is done when this is true.
     */
    public function isConfigured(): bool
    {
        return trim((string) $this->params->get('secret', '')) !== '';
    }

    /**
     * Test mode is one switch for the whole shop (on the control panel): the shop is closed to the
     * public while it is on, and tickets made in it are marked as test tickets. The provider has no
     * setting for it; it uses its test environment (test keys, a sandbox address) exactly when the
     * shop is in test mode, and Ticketstation checks that the answer here matches before it starts
     * a payment.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * Whether an order gets the status "waiting for payment" when the customer is sent away.
     */
    public function marksOrderPendingOnStart(): bool
    {
        return true;
    }

    /**
     * Shown under "Needs attention" on the control panel while this provider takes payments, for
     * example a missing key of the mode the shop is in. The keys are language keys of this plugin:
     * [['key' => 'PLG_TICKETSTATIONPAYMENT_EXAMPLE_ATTENTION_KEY', 'level' => 'danger']].
     * The shop being in test mode is not something to report: the control panel shows that itself.
     */
    public function getHealthWarnings(): array
    {
        return [];
    }

    /**
     * Starts a payment: ask the payment service for one and give back where the customer goes.
     * $request->returnUrl is where the service must send the customer afterwards, and
     * $request->webhookUrl where it must report the result. Throw PaymentException (a message for
     * the admin) when the service refuses or can't be reached.
     */
    public function createPayment(PaymentRequest $request): PaymentRedirect
    {
        if (!$this->isConfigured()) {
            throw new ProviderNotConfiguredException('The example provider has no secret yet.');
        }

        // A real provider would call the payment service here with $request->amount,
        // $request->currency, $request->ordercode, $request->returnUrl and $request->webhookUrl;
        // in test mode ($this->isTestMode()) at the sandbox address and with the test credentials.
        return new PaymentRedirect($request->returnUrl, 'ex_' . $request->ordercode);
    }

    /**
     * A report from the payment service. Prove it is genuine first: anyone can call this address.
     * Throw WebhookRejectedException for a report that is not genuine or incomplete (answered with
     * HTTP 400: no use repeating it), and PaymentException when you can't check it right now (HTTP 503:
     * the service calls again later).
     * Then say what it is about, in the neutral form of PaymentUpdate; Ticketstation decides what
     * it means for the order (amount check, tickets, mail).
     */
    public function handleWebhook(Input $input, string $rawBody): PaymentUpdate
    {
        $secret    = (string) $this->params->get('secret', '');
        $signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');

        if ($secret === '' || !hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            throw new WebhookRejectedException('The signature does not match.');
        }

        $data = json_decode($rawBody, true);

        if (!is_array($data) || empty($data['id']) || empty($data['order'])) {
            throw new WebhookRejectedException('The report is incomplete.');
        }

        $states = [
            'paid'      => PaymentUpdate::PAID,
            'open'      => PaymentUpdate::OPEN,
            'pending'   => PaymentUpdate::PENDING,
            'failed'    => PaymentUpdate::FAILED,
            'cancelled' => PaymentUpdate::CANCELLED,
            'expired'   => PaymentUpdate::EXPIRED,
        ];

        return new PaymentUpdate(
            (int) $data['order'],
            (string) $data['id'],
            $states[$data['status'] ?? ''] ?? PaymentUpdate::UNKNOWN,
            (string) ($data['amount'] ?? '0.00'),
            (string) ($data['currency'] ?? ''),
            (string) ($data['method'] ?? 'example'),
            $rawBody
        );
    }
}
