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
 * Only the required interface is implemented. Add RefundCapableInterface to make and report refunds
 * and chargebacks, and MethodAwareInterface to let the customer choose a payment method.
 */
final class ExampleProvider implements PaymentProviderInterface
{
    private Registry $params;

    public function __construct(Registry $params)
    {
        $this->params = $params;
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
     * Test mode is for staff: the shop is closed to the public while it is on, and tickets made
     * in it are marked as test tickets.
     */
    public function isTestMode(): bool
    {
        return $this->params->get('test_mode', 1) == 1;
    }

    /**
     * Whether an order gets the status "waiting for payment" when the customer is sent away.
     */
    public function marksOrderPendingOnStart(): bool
    {
        return true;
    }

    /**
     * Shown under "Needs attention" on the control panel while this provider takes payments.
     * The keys are language keys of this plugin.
     */
    public function getHealthWarnings(): array
    {
        return $this->isTestMode()
            ? [['key' => 'PLG_TICKETSTATIONPAYMENT_EXAMPLE_ATTENTION_TEST', 'level' => 'danger']]
            : [];
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
        // $request->currency, $request->ordercode, $request->returnUrl and $request->webhookUrl.
        return new PaymentRedirect($request->returnUrl, 'ex_' . $request->ordercode);
    }

    /**
     * A report from the payment service. Prove it is genuine first: anyone can call this address.
     * Then say what it is about, in the neutral form of PaymentUpdate; Ticketstation decides what
     * it means for the order (amount check, tickets, mail).
     */
    public function handleWebhook(Input $input, string $rawBody): PaymentUpdate
    {
        $secret    = (string) $this->params->get('secret', '');
        $signature = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');

        if ($secret === '' || !hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
            throw new PaymentException('The signature does not match.');
        }

        $data = json_decode($rawBody, true);

        if (!is_array($data) || empty($data['id']) || empty($data['order'])) {
            throw new PaymentException('The report is incomplete.');
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
