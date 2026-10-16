<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_testmode
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Module\TicketstationTestmode\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;

/**
 * Dispatcher for mod_ticketstation_testmode: a bar at the top of every backend page while the shop
 * is in test mode. Test mode is one switch for the whole shop, and one left on by mistake closes
 * the shop to the public, which nobody notices until a customer asks.
 *
 * @since  2.37.0
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Renders the bar, or nothing when the shop is live, the user may not open Ticketstation or the
     * page is one of Ticketstation itself (unless the module is set to show it there too).
     *
     * @return  array|false  The layout data, or false to render nothing.
     *
     * @since   2.37.0
     */
    protected function getLayoutData(): array|false
    {
        if (!class_exists(Shop::class) || !AclGate::can('core.manage') || !Shop::inTestMode()) {
            return false;
        }

        $data = parent::getLayoutData();

        // The screens of Ticketstation say it themselves (the switch of the Control Panel), so the bar is
        // left out there unless the module is set to show it everywhere.
        if ($data['params']->get('hide_in_component', 1) && $this->getApplication()->getInput()->getCmd('option') === 'com_ticketstation') {
            return false;
        }

        $this->getApplication()->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR);

        return $data;
    }
}
