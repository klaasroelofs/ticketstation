<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Ticketstation\Component\Ticketstation\Administrator\Payment\Provider\MollieProvider;

defined('_JEXEC') or die;

/**
 * The payment providers the core knows about, and which one takes new payments.
 *
 * For now Mollie is the only provider and is built in. The registry is the one place that changes
 * when providers come from plugins.
 */
final class ProviderRegistry
{
    /** @var PaymentProviderInterface[]|null  id => provider */
    private static ?array $providers = null;

    /**
     * All providers, by id.
     *
     * @return  PaymentProviderInterface[]
     */
    public static function all(): array
    {
        if (self::$providers === null) {
            $mollie = new MollieProvider();

            self::$providers = [$mollie->getId() => $mollie];
        }

        return self::$providers;
    }

    public static function get(string $id): ?PaymentProviderInterface
    {
        return self::all()[$id] ?? null;
    }

    /**
     * The provider that takes new payments, null when there is none.
     */
    public static function active(): ?PaymentProviderInterface
    {
        return self::all()[MollieProvider::ID] ?? null;
    }

    /**
     * The provider a payment was made through, from the provider's own payment id. Payments don't
     * record their provider yet; everything so far went through Mollie.
     */
    public static function forPayment(string $providerPaymentId): ?PaymentProviderInterface
    {
        return $providerPaymentId !== '' ? self::get(MollieProvider::ID) : null;
    }

    /**
     * The name of a payment method for the invoice and the Box Office, from the value stored with
     * the transaction. A provider without method names gives the stored value back.
     */
    public static function methodLabel(string $storedMethod): string
    {
        $provider = self::active();

        return $provider instanceof MethodAwareInterface ? $provider->methodLabel($storedMethod) : $storedMethod;
    }
}
