<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_example
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Example\Plugin\TicketstationPayment\Example\Extension;

defined('_JEXEC') or die;

use Example\Plugin\TicketstationPayment\Example\Provider\ExampleProvider;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\CollectProvidersEvent;

/**
 * The whole plugin: it answers one event and hands Ticketstation its provider. Ticketstation asks
 * for the providers when it needs one (checkout, webhook, refunds), and only of enabled plugins.
 */
final class Example extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        // CollectProvidersEvent::NAME is 'onTicketstationPaymentCollect'
        return [CollectProvidersEvent::NAME => 'collect'];
    }

    public function collect(CollectProvidersEvent $event): void
    {
        // $this->params are the settings of this plugin, as the admin saved them.
        // $event->isTestMode() is whether the shop is in test mode; the provider follows it.
        $event->addProvider(new ExampleProvider($this->params, $event->isTestMode()));
    }
}
