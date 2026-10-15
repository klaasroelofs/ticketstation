<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_system_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\System\Ticketstation\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Portal;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletAppleService;

/**
 * Hands the requests of Apple Wallet for passes that can be updated to Ticketstation. Apple adds
 * fixed paths (/v1/devices/..., /v1/passes/...) to the address in the pass, which a Joomla
 * component can't answer by itself; this plugin recognises them before Joomla's router does.
 * Every other request passes through at the cost of one string search.
 */
final class Ticketstation extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onUserAfterDelete' => 'onUserAfterDelete',
        ];
    }

    /**
     * An account that is deleted takes the addresses it had linked in the customer portal with it.
     */
    public function onUserAfterDelete($event = null): void
    {
        if (!class_exists(Portal::class) || !is_object($event))
        {
            return;
        }

        // 'subject' holds the deleted account's data (the first argument of the event)
        $user    = $event->getArgument('subject') ?? $event->getArgument('user');
        $success = $event->getArgument('deletingResult') ?? $event->getArgument('success') ?? true;

        if ($success && is_array($user) && !empty($user['id']))
        {
            Portal::forgetUser((int) $user['id']);
        }
    }

    public function onAfterInitialise($event = null): void
    {
        // The same path as WalletAppleService::PATH, written out so a missing component costs nothing
        if (!str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/ticketstation-wallet/v1/')) {
            return;
        }

        if (!$this->getApplication()->isClient('site') || !class_exists(WalletAppleService::class)) {
            return;
        }

        WalletAppleService::handle();
    }
}
