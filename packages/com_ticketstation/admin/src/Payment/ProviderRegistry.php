<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;

defined('_JEXEC') or die;

/**
 * The payment providers Ticketstation knows about, and which one takes new payments.
 *
 * Providers come from the enabled plugins of the group "ticketstationpayment": each one adds its
 * provider to the CollectProvidersEvent. A plugin that is switched off or uninstalled offers no
 * provider. Which provider takes new payments, the currency and test mode are settings of
 * Ticketstation (#__ticketstation_config.payment_provider, payment_currency and test_mode); test
 * mode reaches the plugins in the event.
 */
final class ProviderRegistry
{
    /** The plugin group providers live in. */
    public const GROUP = 'ticketstationpayment';

    /** @var array<int, PaymentProviderInterface[]>  test mode (0 or 1) => id => provider */
    private static array $providers = [];

    /** @var object|null  payment_provider, payment_currency and test_mode */
    private static ?object $settings = null;

    /**
     * All providers, by id.
     *
     * @param   bool|null  $testMode  The environment the providers work in; null for the mode the
     *                                shop is in. A payment is always handled in the environment it
     *                                was made in, which is the mode of its order (TestData::ofOrder()).
     *
     * @return  PaymentProviderInterface[]
     */
    public static function all(?bool $testMode = null): array
    {
        $mode = (int) ($testMode ?? self::testMode());

        if (!isset(self::$providers[$mode])) {
            self::$providers[$mode] = [];

            PluginHelper::importPlugin(self::GROUP);

            $event = new CollectProvidersEvent($mode === 1);
            Factory::getApplication()->getDispatcher()->dispatch(CollectProvidersEvent::NAME, $event);

            foreach ($event->getProviders() as $provider) {
                self::$providers[$mode][$provider->getId()] = $provider;
            }
        }

        return self::$providers[$mode];
    }

    public static function get(string $id, ?bool $testMode = null): ?PaymentProviderInterface
    {
        return self::all($testMode)[$id] ?? null;
    }

    /**
     * The id of the provider chosen to take new payments, '' when online payments are off. The
     * provider itself may be missing (its plugin switched off or removed).
     */
    public static function activeId(): string
    {
        return (string) self::settings()->payment_provider;
    }

    /**
     * The provider that takes new payments, null when online payments are off or the chosen
     * provider is not available.
     */
    public static function active(): ?PaymentProviderInterface
    {
        $id = self::activeId();

        return $id !== '' ? self::get($id) : null;
    }

    /**
     * Whether the shop is in test mode: the switch on the control panel. It decides for the whole
     * shop, the payment provider included.
     */
    public static function testMode(): bool
    {
        return (int) self::settings()->test_mode === 1;
    }

    /**
     * The currency payments are made in, an ISO 4217 code.
     */
    public static function currency(): string
    {
        return PaymentCurrencies::fromConfig(self::settings()->payment_currency);
    }

    /**
     * The name of a payment method for the invoice and the Box Office, from the value stored with
     * the transaction. A provider without method names, or one that is no longer available, gives
     * the stored value back with a capital.
     */
    public static function methodLabel(string $storedMethod, string $providerId): string
    {
        $provider = $providerId !== '' ? self::get($providerId) : null;

        return $provider instanceof MethodAwareInterface ? $provider->methodLabel($storedMethod) : ucfirst($storedMethod);
    }

    /**
     * The name of the provider a payment went through, from the id stored with the transaction.
     * Empty for a payment without one (box office sales); a provider that is no longer available
     * gives its stored id with a capital.
     */
    public static function providerLabel(string $providerId): string
    {
        if ($providerId === '') {
            return '';
        }

        $provider = self::get($providerId);

        return $provider !== null ? $provider->getTitle() : ucfirst($providerId);
    }

    /**
     * Forgets what was loaded, so the next call looks again. For tests and for after a setting changed.
     */
    public static function reset(): void
    {
        self::$providers = [];
        self::$settings  = null;
    }

    private static function settings(): object
    {
        if (self::$settings === null) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['payment_provider', 'payment_currency', 'test_mode']))
                ->from($db->quoteName('#__ticketstation_config'))
                ->where($db->quoteName('configid') . ' = 1');

            $db->setQuery($query);

            self::$settings = $db->loadObject() ?: (object) ['payment_provider' => '', 'payment_currency' => PaymentCurrencies::DEFAULT, 'test_mode' => 0];
        }

        return self::$settings;
    }
}
