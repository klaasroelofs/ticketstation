<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_mollie
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Mollie\Provider;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Mollie\Api\Exceptions\MollieException;
use Mollie\Api\Exceptions\NotFoundException;
use Mollie\Api\Exceptions\ValidationException;
use Mollie\Api\MollieApiClient;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Payment\WebhookRejectedException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\CurrencyAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\MethodAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentMethodOption;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentProviderInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRedirect;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentRequest;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentUpdate;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderNotConfiguredException;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRefund;
use Ticketstation\Component\Ticketstation\Administrator\Payment\RefundCapableInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\RefundCheck;
use Ticketstation\Component\Ticketstation\Administrator\Payment\TestModeAwareInterface;
use Ticketstation\Plugin\TicketstationPayment\Mollie\Helper\MollieCurrencies;
use Ticketstation\Plugin\TicketstationPayment\Mollie\Helper\MolliePaymentMethods;

/**
 * Mollie, through Mollie's PHP library. Its settings are the parameters of the plugin.
 */
final class MollieProvider implements PaymentProviderInterface, RefundCapableInterface, MethodAwareInterface, CurrencyAwareInterface, TestModeAwareInterface
{
    public const ID = 'mollie';

    /** Where Mollie keeps the logos of its methods. */
    private const ICON_URL = 'https://www.mollie.com/external/icons/payment-methods/%s.svg';

    private Registry $params;

    private bool $testMode;

    /**
     * @param   bool  $testMode  Whether the shop is in test mode (CollectProvidersEvent::isTestMode()).
     */
    public function __construct(Registry $params, bool $testMode = false)
    {
        $this->params   = $params;
        $this->testMode = $testMode;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return 'Mollie';
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->params->get('api_key', '')) !== '';
    }

    /**
     * Whether Mollie's test API is used: exactly while the shop is in test mode (the switch on the
     * control panel, passed in by the plugin).
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function marksOrderPendingOnStart(): bool
    {
        return $this->params->get('mark_pending', 1) == 1;
    }

    public function getHealthWarnings(): array
    {
        $warnings = [];
        $key      = (string) $this->params->get('api_key', '');

        // The shop is in test mode (the control panel says so): only the test key is used, so a
        // missing one is the problem. The live key is checked when the shop goes live.
        if ($this->isTestMode()) {
            if (trim((string) $this->params->get('api_key_test', '')) === '') {
                $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_ATTENTION_TEST_KEY_MISSING', 'level' => 'danger'];
            }

            return $warnings;
        }

        if ($key == '') {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_ATTENTION_KEY_MISSING', 'level' => 'danger'];
        } elseif (substr($key, 0, 5) === 'test_') {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_ATTENTION_KEY_IS_TEST', 'level' => 'danger'];
        } elseif (substr($key, 0, 5) !== 'live_') {
            $warnings[] = ['key' => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_ATTENTION_KEY_INVALID', 'level' => 'danger'];
        }

        return $warnings;
    }

    /**
     * The methods the admin allows, as set in the plugin.
     *
     * @return  string[]
     */
    public function getAllowedMethods(): array
    {
        return MolliePaymentMethods::fromConfig($this->params->get('payment_methods', 'ideal'));
    }

    public function getSupportedCurrencies(): array
    {
        return MollieCurrencies::SUPPORTED;
    }

    public function getCheckoutMethods(string $currency): array
    {
        $options = [];

        foreach (MollieCurrencies::filterMethods($currency, $this->getAllowedMethods()) as $method) {
            $options[] = new PaymentMethodOption($method, MolliePaymentMethods::label($method), sprintf(self::ICON_URL, $method));
        }

        return $options;
    }

    public function methodLabel(string $storedMethod): string
    {
        return MolliePaymentMethods::label($storedMethod);
    }

    public function createPayment(PaymentRequest $request): PaymentRedirect
    {
        $mollie  = $this->client();
        $methods = MollieCurrencies::filterMethods($request->currency, $this->getAllowedMethods());

        // The method the customer chose, when it is one of the allowed ones.
        if ($request->method !== null && in_array($request->method, $methods, true)) {
            $methods = [$request->method];
        }

        try {
            $payment = $mollie->payments->create([
                'amount'      => [
                    'value'    => $request->amount,
                    'currency' => $request->currency,
                ],
                // One method is passed as that method: Mollie then skips its method screen, so a
                // cancelled or failed payment returns to the site. With a list Mollie keeps the
                // customer on its own method screen.
                'method'      => count($methods) === 1 ? reset($methods) : $methods,
                'description' => $this->params->get('description', '') . ' ' . $request->ordercode,
                'redirectUrl' => $request->returnUrl,
                'webhookUrl'  => $request->webhookUrl,
                'locale'      => $this->params->get('locale', 'en_GB'),
                'metadata'    => [
                    'order_id' => $request->ordercode,
                ],
            ]);
        } catch (MollieException $e) {
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }

        return new PaymentRedirect((string) $payment->getCheckoutUrl(), (string) $payment->id);
    }

    public function handleWebhook(Input $input, string $rawBody): PaymentUpdate
    {
        // Mollie only sends the id of the payment. Asking the payment from Mollie, with our own
        // key, is what makes the call genuine: a made-up id returns nothing.
        $id = $input->post->getString('id', '');

        if ($id === '') {
            throw new WebhookRejectedException('No payment id was sent.');
        }

        $mollie = $this->client();

        try {
            $payment = $mollie->payments->get($id);
        } catch (NotFoundException | ValidationException $e) {
            // Mollie doesn't know this payment: the call is not genuine. Repeating it is pointless.
            throw new WebhookRejectedException($this->plainMessage($e), 0, $e);
        } catch (MollieException $e) {
            // Mollie can't be reached or has an error: it should call again later.
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }

        if ($payment->isPaid()) {
            $state = PaymentUpdate::PAID;
        } elseif ($payment->isOpen()) {
            $state = PaymentUpdate::OPEN;
        } elseif ($payment->isPending()) {
            $state = PaymentUpdate::PENDING;
        } elseif ($payment->isFailed()) {
            $state = PaymentUpdate::FAILED;
        } elseif ($payment->isCanceled()) {
            $state = PaymentUpdate::CANCELLED;
        } elseif ($payment->isExpired()) {
            $state = PaymentUpdate::EXPIRED;
        } else {
            $state = PaymentUpdate::UNKNOWN;
        }

        return new PaymentUpdate(
            (int) ($payment->metadata->order_id ?? 0),
            (string) $payment->id,
            $state,
            (string) $payment->amount->value,
            (string) $payment->amount->currency,
            $this->value($payment->method),
            http_build_query($payment),
            $payment->hasRefunds() || $payment->hasChargebacks()
        );
    }

    public function canRefund(string $providerPaymentId): RefundCheck
    {
        $mollie = $this->client();

        try {
            $payment = $mollie->payments->get($providerPaymentId);

            return new RefundCheck((bool) $payment->canBeRefunded(), (float) $payment->getAmountRemaining(), (string) $payment->amount->currency);
        } catch (MollieException $e) {
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }
    }

    public function createRefund(string $providerPaymentId, float $amount, string $description, array $meta): ProviderRefund
    {
        $mollie = $this->client();

        try {
            $payment  = $mollie->payments->get($providerPaymentId);
            $currency = (string) $payment->amount->currency;

            $refund = $payment->refund([
                'amount'      => ['currency' => $currency, 'value' => PaymentCurrencies::format($amount, $currency)],
                'description' => $description,
                'metadata'    => $meta,
            ]);
        } catch (MollieException $e) {
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }

        return new ProviderRefund((string) $refund->id, $providerPaymentId, ProviderRefund::REFUND, $amount, $currency,
            $description, $this->value($refund->status), (string) ($refund->createdAt ?? ''), true);
    }

    public function getRefunds(string $providerPaymentId): array
    {
        $mollie = $this->client();

        try {
            $payment = $mollie->payments->get($providerPaymentId);
            $found   = [];

            foreach ($payment->refunds() as $refund) {
                $found[] = $this->refund($refund);
            }

            if ($payment->hasChargebacks()) {
                foreach ($payment->chargebacks() as $chargeback) {
                    $found[] = $this->chargeback($chargeback);
                }
            }

            return $found;
        } catch (MollieException $e) {
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }
    }

    public function pollRecent(): array
    {
        $mollie = $this->client();

        try {
            $found = [];

            foreach ($mollie->refunds->page(null, 50) as $refund) {
                $found[] = $this->refund($refund);
            }

            foreach ($mollie->chargebacks->page(null, 50) as $chargeback) {
                $found[] = $this->chargeback($chargeback);
            }

            return $found;
        } catch (MollieException $e) {
            throw new PaymentException($this->plainMessage($e), 0, $e);
        }
    }

    /**
     * A Mollie refund as the core knows it.
     */
    private function refund($refund): ProviderRefund
    {
        return new ProviderRefund(
            (string) $refund->id,
            (string) $refund->paymentId,
            ProviderRefund::REFUND,
            (float) $refund->amount->value,
            (string) $refund->amount->currency,
            (string) $refund->description,
            $this->value($refund->status),
            (string) $refund->createdAt,
            isset($refund->metadata->source) && $refund->metadata->source === 'ticketstation'
        );
    }

    /**
     * A Mollie chargeback as the core knows it.
     */
    private function chargeback($chargeback): ProviderRefund
    {
        return new ProviderRefund(
            (string) $chargeback->id,
            (string) $chargeback->paymentId,
            ProviderRefund::CHARGEBACK,
            (float) $chargeback->amount->value,
            (string) $chargeback->amount->currency,
            (string) ($chargeback->reason->description ?? ''),
            $chargeback->reversedAt ? 'reversed' : 'charged_back',
            (string) $chargeback->createdAt
        );
    }

    /**
     * A Mollie client with the key of the current mode (test or live).
     *
     * @throws  ProviderNotConfiguredException  when no key is set.
     */
    private function client(): MollieApiClient
    {
        $key = $this->isTestMode() ? $this->params->get('api_key_test', '') : $this->params->get('api_key', '');

        if (empty($key)) {
            throw new ProviderNotConfiguredException(Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_ERROR_NO_KEY'));
        }

        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        $mollie = new MollieApiClient();

        try {
            $mollie->setApiKey(trim($key));
        } catch (\Throwable $e) {
            // Not a Mollie key at all (wrong prefix or length): Mollie's library refuses it before asking Mollie.
            throw new ProviderNotConfiguredException(Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_ERROR_INVALID_KEY'), 0, $e);
        }

        return $mollie;
    }

    /**
     * A status, method or other value from Mollie as a plain string. Since version 4 of Mollie's
     * library such fields hold an enum case for the values it knows and a string for the rest.
     */
    private function value($value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    private function plainMessage(\Throwable $e): string
    {
        return method_exists($e, 'getPlainMessage') ? $e->getPlainMessage() : $e->getMessage();
    }
}
