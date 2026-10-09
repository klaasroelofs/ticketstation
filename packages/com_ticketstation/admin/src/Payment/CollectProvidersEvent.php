<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\Event\Event;

defined('_JEXEC') or die;

/**
 * Dispatched as "onTicketstationPaymentCollect" to the enabled plugins of the group
 * "ticketstationpayment". Each plugin adds its provider with addProvider().
 */
final class CollectProvidersEvent extends Event
{
    public const NAME = 'onTicketstationPaymentCollect';

    /** @var PaymentProviderInterface[] */
    private array $providers = [];

    private bool $testMode;

    /**
     * @param   bool  $testMode  Whether the shop is in test mode (the switch on the control panel).
     */
    public function __construct(bool $testMode = false)
    {
        parent::__construct(self::NAME);

        $this->testMode = $testMode;
    }

    /**
     * Whether the shop is in test mode. A provider with a test environment (TestModeAwareInterface)
     * uses it exactly then.
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function addProvider(PaymentProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return  PaymentProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
