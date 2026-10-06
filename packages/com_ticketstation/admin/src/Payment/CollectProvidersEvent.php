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

    public function __construct()
    {
        parent::__construct(self::NAME);
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
