<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_stripe
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Stripe\Provider;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Payment\CurrencyAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\MethodAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentProviderInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRedirect;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRequest;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentUpdate;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderNotConfiguredException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRefund;
use Ticketstation\Component\Ticketstation\Administrator\Payment\RefundCapableInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\RefundCheck;
use Ticketstation\Component\Ticketstation\Administrator\Payment\WebhookRejectedException;
use Ticketstation\Plugin\TicketstationPayment\Stripe\Api\StripeClient;
use Ticketstation\Plugin\TicketstationPayment\Stripe\Api\StripeNotFoundException;
use Ticketstation\Plugin\TicketstationPayment\Stripe\Api\WebhookSignature;
use Ticketstation\Plugin\TicketstationPayment\Stripe\Helper\StripeMethods;

/**
 * Stripe Checkout (Stripe's hosted payment page). The customer pays at Stripe and comes back to
 * the site; Stripe shows the methods that are switched on in the Stripe Dashboard. Its settings are
 * the parameters of the plugin.
 *
 * The id of a payment is the PaymentIntent ("pi_..."), as that is what refunds and disputes belong
 * to. Starting a payment only knows the Checkout Session ("cs_..."); the webhook that reports the
 * payment carries the PaymentIntent, and that is what Ticketstation stores.
 */
final class StripeProvider implements PaymentProviderInterface, RefundCapableInterface, CurrencyAwareInterface, MethodAwareInterface
{
    public const ID = 'stripe';

    /** A Checkout Session can only be paid for this long (Stripe's minimum is 30 minutes). */
    private const SESSION_LIFETIME = 3600;

    private Registry $params;

    private ?\Closure $transport;

    /**
     * @param   \Closure|null  $transport  Replaces the HTTP call to Stripe (see StripeClient), for tests.
     */
    public function __construct(Registry $params, ?\Closure $transport = null)
    {
        $this->params    = $params;
        $this->transport = $transport;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return 'Stripe';
    }

    public function isConfigured(): bool
    {
        return $this->keyMatches((string) $this->params->get('secret_key', ''), 'live')
            && $this->webhookSecret(false) !== '';
    }

    public function isTestMode(): bool
    {
        return $this->params->get('test_mode', 0) == 1;
    }

    public function marksOrderPendingOnStart(): bool
    {
        return $this->params->get('mark_pending', 1) == 1;
    }

    public function getHealthWarnings(): array
    {
        $warnings = [];
        $test     = $this->isTestMode();

        if ($test) {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_TEST', 'level' => 'danger'];
        } else {
            $key = trim((string) $this->params->get('secret_key', ''));

            if ($key === '') {
                $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_KEY_MISSING', 'level' => 'danger'];
            } elseif ($this->keyMatches($key, 'test')) {
                $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_KEY_IS_TEST', 'level' => 'danger'];
            } elseif (!$this->keyMatches($key, 'live')) {
                $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_KEY_INVALID', 'level' => 'danger'];
            }
        }

        // Without the signing secret every report from Stripe is refused: payments would never complete.
        $secret = $this->webhookSecret($test);

        if ($secret === '') {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_WEBHOOK_SECRET_MISSING', 'level' => 'danger'];
        } elseif (strpos($secret, 'whsec_') !== 0) {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_STRIPE_ATTENTION_WEBHOOK_SECRET_INVALID', 'level' => 'danger'];
        }

        return $warnings;
    }

    /**
     * Everything Ticketstation offers, except the Icelandic króna: Stripe writes it with two
     * decimals although it has none.
     */
    public function getSupportedCurrencies(): array
    {
        return array_values(array_diff(array_keys(PaymentCurrencies::CURRENCIES), ['ISK']));
    }

    /**
     * The customer doesn't choose a method on our payment page: Stripe's own page shows the
     * methods that are switched on in the Dashboard.
     */
    public function getCheckoutMethods(string $currency): array
    {
        return [];
    }

    public function methodLabel(string $storedMethod): string
    {
        return StripeMethods::label($storedMethod);
    }

    public function createPayment(PaymentRequest $request): PaymentRedirect
    {
        $text = trim($this->params->get('description', '') . ' ' . $request->ordercode);

        $params = [
            'mode'                => 'payment',
            'client_reference_id' => (string) $request->ordercode,
            // The customer comes back to the same address whether they paid or cancelled: the wait
            // page there finds out what happened from Stripe's report.
            'success_url'         => $request->returnUrl,
            'cancel_url'          => $request->returnUrl,
            'expires_at'          => time() + self::SESSION_LIFETIME,
            'line_items'          => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower($request->currency),
                    'unit_amount'  => $this->minorUnits((float) $request->amount, $request->currency),
                    'product_data' => ['name' => $text],
                ],
            ]],
            'metadata'            => $this->metadata($request->ordercode),
            'payment_intent_data' => [
                'description' => $text,
                'metadata'    => $this->metadata($request->ordercode),
            ],
        ];

        $locale = (string) $this->params->get('locale', 'auto');

        if ($locale !== '' && $locale !== 'auto') {
            $params['locale'] = $locale;
        }

        $session = $this->client()->post('checkout/sessions', $params);

        if (empty($session['url']) || empty($session['id'])) {
            throw new PaymentException('Stripe did not return a payment page.');
        }

        return new PaymentRedirect((string) $session['url'], (string) $session['id']);
    }

    public function handleWebhook(Input $input, string $rawBody): PaymentUpdate
    {
        // Stripe signs every call. Without the right signature the call is not from Stripe.
        $header = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $secret = $this->webhookSecret($this->isTestMode());

        if ($secret === '') {
            throw new ProviderNotConfiguredException(Text::_('PLG_TICKETSTATIONPAYMENT_STRIPE_ERROR_NO_WEBHOOK_SECRET'));
        }

        if (!WebhookSignature::verify($rawBody, $header, $secret)) {
            throw new WebhookRejectedException('The signature does not match.');
        }

        $event = json_decode($rawBody, true);
        $type  = is_array($event) ? (string) ($event['type'] ?? '') : '';
        $id    = is_array($event) ? (string) ($event['data']['object']['id'] ?? '') : '';

        if ($type === '' || $id === '') {
            throw new WebhookRejectedException('The report is incomplete.');
        }

        // The report only says that something happened. What it is about is read from Stripe, with
        // our own key: that also gives the current state when reports arrive out of order.
        try {
            switch ($type) {
                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                case 'checkout.session.async_payment_failed':
                case 'checkout.session.expired':
                    return $this->updateFromSession(
                        $this->client()->get('checkout/sessions/' . rawurlencode($id), ['expand' => ['payment_intent.latest_charge']]),
                        $type
                    );

                case 'charge.refunded':
                case 'charge.refund.updated':
                case 'charge.dispute.created':
                case 'charge.dispute.updated':
                case 'charge.dispute.closed':
                case 'charge.dispute.funds_withdrawn':
                case 'charge.dispute.funds_reinstated':
                    $intent = (string) ($event['data']['object']['payment_intent'] ?? '');

                    if ($intent === '') {
                        return $this->ignored();
                    }

                    return $this->updateFromIntent($this->intent($intent));

                default:
                    return $this->ignored();
            }
        } catch (StripeNotFoundException $e) {
            // A genuine report about something Stripe doesn't (or no longer) know: repeating it can't help.
            throw new WebhookRejectedException($e->getMessage(), 0, $e);
        }
    }

    public function canRefund(string $providerPaymentId): RefundCheck
    {
        $intent   = $this->intent($providerPaymentId);
        $charge   = $this->charge($intent);
        $currency = strtoupper((string) ($intent['currency'] ?? ''));
        $left     = ((int) ($charge['amount_captured'] ?? $charge['amount'] ?? 0)) - (int) ($charge['amount_refunded'] ?? 0);

        $refundable = ($intent['status'] ?? '') === 'succeeded' && $charge && empty($charge['disputed']) && $left > 0;

        return new RefundCheck($refundable, $this->majorUnits($left, $currency), $currency);
    }

    public function createRefund(string $providerPaymentId, float $amount, string $description, array $meta): ProviderRefund
    {
        $intent   = $this->intent($providerPaymentId);
        $currency = strtoupper((string) ($intent['currency'] ?? ''));

        $metadata = [];

        foreach ($meta as $key => $value) {
            $metadata[(string) $key] = (string) $value;
        }

        if ($description !== '') {
            $metadata['description'] = mb_substr($description, 0, 500);
        }

        $refund = $this->client()->post('refunds', [
            'payment_intent' => $providerPaymentId,
            'amount'         => $this->minorUnits($amount, $currency),
            'metadata'       => $metadata,
        ]);

        return new ProviderRefund((string) $refund['id'], $providerPaymentId, ProviderRefund::REFUND, $amount, $currency,
            $description, $this->refundStatus((string) ($refund['status'] ?? '')), $this->date($refund['created'] ?? null), true);
    }

    public function getRefunds(string $providerPaymentId): array
    {
        $found  = [];
        $client = $this->client();

        foreach (($client->get('refunds', ['payment_intent' => $providerPaymentId, 'limit' => 100])['data'] ?? []) as $refund) {
            $currency = strtoupper((string) ($refund['currency'] ?? ''));

            $found[] = new ProviderRefund(
                (string) $refund['id'],
                $providerPaymentId,
                ProviderRefund::REFUND,
                $this->majorUnits((int) ($refund['amount'] ?? 0), $currency),
                $currency,
                (string) ($refund['metadata']['description'] ?? ''),
                $this->refundStatus((string) ($refund['status'] ?? '')),
                $this->date($refund['created'] ?? null),
                ($refund['metadata']['source'] ?? '') === 'ticketstation'
            );
        }

        foreach (($client->get('disputes', ['payment_intent' => $providerPaymentId, 'limit' => 100])['data'] ?? []) as $dispute) {
            $status = (string) ($dispute['status'] ?? '');

            // "warning_..." is an inquiry from the bank: no money has been taken back (yet).
            if (strpos($status, 'warning_') === 0) {
                continue;
            }

            $currency = strtoupper((string) ($dispute['currency'] ?? ''));

            $found[] = new ProviderRefund(
                (string) $dispute['id'],
                $providerPaymentId,
                ProviderRefund::CHARGEBACK,
                $this->majorUnits((int) ($dispute['amount'] ?? 0), $currency),
                $currency,
                (string) ($dispute['reason'] ?? ''),
                $status === 'won' ? 'reversed' : 'charged_back',
                $this->date($dispute['created'] ?? null)
            );
        }

        return $found;
    }

    /**
     * Stripe reports every refund and dispute by webhook, so there is nothing to poll.
     */
    public function pollRecent(): array
    {
        return [];
    }

    /**
     * What a Checkout Session says about the payment, as an update for the order.
     */
    private function updateFromSession(array $session, string $eventType): PaymentUpdate
    {
        $ordercode = $this->ordercode($session['metadata'] ?? []);

        if ($ordercode === 0) {
            return $this->ignored();
        }

        $intent = is_array($session['payment_intent'] ?? null) ? $session['payment_intent'] : ['id' => (string) ($session['payment_intent'] ?? '')];

        $status        = (string) ($session['status'] ?? '');
        $paymentStatus = (string) ($session['payment_status'] ?? '');

        // A session that expired before the customer tried to pay has no PaymentIntent. Its own id
        // then stands in for the payment id, which is only used for the log of the order.
        if (($intent['id'] ?? '') === '') {
            if ($status !== 'expired') {
                return $this->ignored();
            }

            $intent = ['id' => (string) ($session['id'] ?? '')];
        }

        if ($eventType === 'checkout.session.async_payment_failed') {
            $state = PaymentUpdate::FAILED;
        } elseif ($status === 'expired') {
            $state = PaymentUpdate::EXPIRED;
        } elseif ($status === 'complete') {
            // "unpaid" after completing: the customer did their part, the money follows (a bank debit).
            $state = in_array($paymentStatus, ['paid', 'no_payment_required'], true) ? PaymentUpdate::PAID : PaymentUpdate::PENDING;
        } else {
            $state = PaymentUpdate::OPEN;
        }

        $currency = strtoupper((string) ($session['currency'] ?? ''));
        $charge   = $this->charge($intent);
        $method   = (string) ($charge['payment_method_details']['type'] ?? ($session['payment_method_types'][0] ?? ''));

        return new PaymentUpdate(
            $ordercode,
            (string) $intent['id'],
            $state,
            PaymentCurrencies::format($this->majorUnits((int) ($session['amount_total'] ?? 0), $currency), $currency),
            $currency,
            str_replace('_', ' ', $method),
            http_build_query([
                'session'        => $session['id'] ?? '',
                'payment_intent' => $intent['id'],
                'status'         => $status,
                'payment_status' => $paymentStatus,
                'amount_total'   => $session['amount_total'] ?? '',
                'currency'       => $currency,
                'method'         => $method,
                'livemode'       => !empty($session['livemode']) ? 'live' : 'test',
                'event'          => $eventType,
            ]),
            $this->hasRefundsOrDispute($charge)
        );
    }

    /**
     * What a PaymentIntent says, for a report about a refund or a dispute. The payment itself was
     * reported before; this tells the order to look at the refunds and disputes of the payment.
     */
    private function updateFromIntent(array $intent): PaymentUpdate
    {
        $ordercode = $this->ordercode($intent['metadata'] ?? []);

        if ($ordercode === 0 || ($intent['status'] ?? '') !== 'succeeded') {
            return $this->ignored();
        }

        $currency = strtoupper((string) ($intent['currency'] ?? ''));
        $charge   = $this->charge($intent);
        $method   = (string) ($charge['payment_method_details']['type'] ?? '');

        return new PaymentUpdate(
            $ordercode,
            (string) $intent['id'],
            PaymentUpdate::PAID,
            PaymentCurrencies::format($this->majorUnits((int) ($intent['amount_received'] ?? 0), $currency), $currency),
            $currency,
            str_replace('_', ' ', $method),
            http_build_query(['payment_intent' => $intent['id'], 'status' => $intent['status'], 'event' => 'refund or dispute']),
            true
        );
    }

    private function ignored(): PaymentUpdate
    {
        return new PaymentUpdate(0, '', PaymentUpdate::IGNORE, '0', '', '', '');
    }

    private function hasRefundsOrDispute(array $charge): bool
    {
        return (int) ($charge['amount_refunded'] ?? 0) > 0 || !empty($charge['disputed']);
    }

    /**
     * The PaymentIntent with its latest charge expanded.
     */
    private function intent(string $id): array
    {
        return $this->client()->get('payment_intents/' . rawurlencode($id), ['expand' => ['latest_charge']]);
    }

    /**
     * The charge of a PaymentIntent fetched with latest_charge expanded; [] when there is none.
     */
    private function charge(array $intent): array
    {
        $charge = $intent['latest_charge'] ?? null;

        return is_array($charge) ? $charge : [];
    }

    /**
     * The order a payment was made for, from the metadata that was put on it when it started. A
     * payment that isn't ours (another website using the same Stripe account, a payment made by
     * hand in the Dashboard) has none, or the host of another website, and is left alone.
     */
    private function ordercode(array $metadata): int
    {
        if (($metadata['shop'] ?? '') !== $this->shop()) {
            return 0;
        }

        return (int) ($metadata['ordercode'] ?? 0);
    }

    private function metadata(int $ordercode): array
    {
        return ['ordercode' => (string) $ordercode, 'shop' => $this->shop()];
    }

    /**
     * Stripe sends every report to every endpoint of the account, and Stripe's CLI forwards the
     * reports of the whole test account to a local website. Ordercodes are only unique per website.
     */
    private function shop(): string
    {
        return (string) parse_url(Uri::root(), PHP_URL_HOST);
    }

    /**
     * An amount in the smallest unit Stripe works with: cents, or whole units for currencies
     * without decimals.
     */
    private function minorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * (10 ** PaymentCurrencies::digits($currency)));
    }

    private function majorUnits(int $minor, string $currency): float
    {
        return $minor / (10 ** PaymentCurrencies::digits($currency));
    }

    private function refundStatus(string $status): string
    {
        switch ($status) {
            case 'succeeded':
                return 'refunded';
            case 'failed':
                return 'failed';
            case 'canceled':
                return 'cancelled';
            default:
                // pending, requires_action
                return 'pending';
        }
    }

    private function date($timestamp): string
    {
        return $timestamp ? gmdate('Y-m-d H:i:s', (int) $timestamp) : '';
    }

    /**
     * Whether a key is a secret (sk_) or restricted (rk_) key of the mode: live keys can't be used
     * in test mode and the other way round, so a mix-up can't charge real money by accident.
     */
    private function keyMatches(string $key, string $mode): bool
    {
        return preg_match('/^(sk|rk)_' . $mode . '_[A-Za-z0-9]+$/', trim($key)) === 1;
    }

    private function webhookSecret(bool $test): string
    {
        return trim((string) $this->params->get($test ? 'webhook_secret_test' : 'webhook_secret', ''));
    }

    /**
     * A Stripe client with the key of the current mode (test or live).
     *
     * @throws  ProviderNotConfiguredException  when no usable key is set.
     */
    private function client(): StripeClient
    {
        $test = $this->isTestMode();
        $key  = trim((string) $this->params->get($test ? 'secret_key_test' : 'secret_key', ''));

        if ($key === '') {
            throw new ProviderNotConfiguredException(Text::_('PLG_TICKETSTATIONPAYMENT_STRIPE_ERROR_NO_KEY'));
        }

        if (!$this->keyMatches($key, $test ? 'test' : 'live')) {
            throw new ProviderNotConfiguredException(Text::_($test ? 'PLG_TICKETSTATIONPAYMENT_STRIPE_ERROR_INVALID_KEY_TEST' : 'PLG_TICKETSTATIONPAYMENT_STRIPE_ERROR_INVALID_KEY_LIVE'));
        }

        return new StripeClient($key, $this->transport);
    }
}
