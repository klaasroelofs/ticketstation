<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment\Provider;

use Joomla\CMS\Factory;
use Joomla\Input\Input;
use Mollie\Api\Exceptions\MollieException;
use Mollie\Api\MollieApiClient;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MollieCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MolliePaymentMethods;
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
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 * Mollie, through Mollie's PHP library. Its settings are the one row of #__ticketstation_mollie.
 */
final class MollieProvider implements PaymentProviderInterface, RefundCapableInterface, MethodAwareInterface
{
    public const ID = 'mollie';

    /** What a missing settings row, or a missing column, falls back to. */
    private const DEFAULTS = [
        'enabled'              => '1',
        'test_mode'            => '0',
        'api_key'              => '',
        'api_key_test'         => '',
        'description'          => '',
        'mollie_language'      => 'en',
        'change_payment_state' => '1',
        'payment_methods'      => 'ideal',
        'currency'             => 'EUR',
    ];

    private ?object $settings = null;

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return 'Mollie';
    }

    public function isEnabled(): bool
    {
        return $this->setting('enabled') == '1';
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->setting('api_key')) !== '';
    }

    public function isTestMode(): bool
    {
        return $this->setting('test_mode') == '1';
    }

    public function getCurrency(): string
    {
        return MollieCurrencies::fromConfig((string) $this->setting('currency'));
    }

    public function marksOrderPendingOnStart(): bool
    {
        return $this->setting('change_payment_state') == 1;
    }

    public function getSettingsLink(): string
    {
        return 'index.php?option=com_ticketstation&view=mollie';
    }

    public function getHealthWarnings(): array
    {
        // None while online payments are switched off: the site may have no Mollie account.
        if (!$this->isEnabled()) {
            return [];
        }

        $warnings = [];
        $key      = (string) $this->setting('api_key');

        if ($this->isTestMode()) {
            $warnings[] = ['key' => 'COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_TEST', 'level' => 'danger'];
        }

        if ($key == '') {
            $warnings[] = ['key' => 'COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_MISSING', 'level' => 'danger'];
        } elseif (substr($key, 0, 5) === 'test_') {
            $warnings[] = ['key' => 'COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_IS_TEST', 'level' => 'danger'];
        } elseif (substr($key, 0, 5) !== 'live_') {
            $warnings[] = ['key' => 'COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_INVALID', 'level' => 'danger'];
        }

        return $warnings;
    }

    public function getAllowedMethods(): array
    {
        return MolliePaymentMethods::fromConfig((string) $this->setting('payment_methods'));
    }

    public function methodLabel(string $storedMethod): string
    {
        return MolliePaymentMethods::label($storedMethod);
    }

    public function createPayment(PaymentRequest $request): PaymentRedirect
    {
        $mollie = $this->client();

        try {
            $payment = $mollie->payments->create([
                'amount'      => [
                    'value'    => $request->amount,
                    'currency' => $request->currency,
                ],
                'method'      => MollieCurrencies::filterMethods($request->currency, $this->getAllowedMethods()),
                'description' => $this->setting('description') . ' ' . $request->ordercode,
                'redirectUrl' => $request->returnUrl,
                'webhookUrl'  => $request->webhookUrl,
                'locale'      => $this->setting('mollie_language'),
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
            throw new PaymentException('No payment id was sent.');
        }

        $mollie = $this->client();

        try {
            $payment = $mollie->payments->get($id);
        } catch (MollieException $e) {
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
                'amount'      => ['currency' => $currency, 'value' => number_format($amount, 2, '.', '')],
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
        $key = $this->isTestMode() ? $this->setting('api_key_test') : $this->setting('api_key');

        if (empty($key)) {
            throw new ProviderNotConfiguredException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NO_KEY'));
        }

        require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

        $mollie = new MollieApiClient();
        $mollie->setApiKey($key);

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

    /**
     * One setting from the Mollie row, read once per request.
     */
    private function setting(string $name)
    {
        if ($this->settings === null) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ticketstation_mollie'))
                ->where($db->quoteName('configid') . ' = 1');

            $db->setQuery($query);

            $this->settings = $db->loadObject() ?: (object) [];
        }

        return $this->settings->$name ?? self::DEFAULTS[$name] ?? null;
    }
}
