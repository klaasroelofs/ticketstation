<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_mollie
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Mollie\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\CollectProvidersEvent;
use Ticketstation\Plugin\TicketstationPayment\Mollie\Provider\MollieProvider;

/**
 * Offers Mollie to Ticketstation as a payment provider. The settings are the parameters of this
 * plugin; whether Mollie takes new payments is chosen on Ticketstation's Payments screen.
 */
final class Mollie extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [CollectProvidersEvent::NAME => 'collect'];
    }

    public function collect(CollectProvidersEvent $event): void
    {
        $event->addProvider(new MollieProvider($this->params));
    }
}
