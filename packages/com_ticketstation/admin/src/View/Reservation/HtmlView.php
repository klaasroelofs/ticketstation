<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Reservation;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;

/**
 * Admin "new reservation" wizard view - one layout per step.
 *
 * @since 1.8.0
 */
class HtmlView extends BaseHtmlView
{
    private const SESSION_KEY = 'ticketstation.reservation';

    public function display($tpl = null)
    {
        $app       = Factory::getApplication();
        $session   = $app->getSession();
        $ordercode = $session->get('ordercode');
        $layout    = $this->getLayout();

        // Step 1 (the menu/control panel entry point) is self-sufficient: quietly start a
        // fresh reservation the first time it's reached with no ordercode in the session yet,
        // rather than sending the admin through a separate controller=reservation&task=start
        // link first. That extra hop is also why the admin menu wouldn't stay expanded on this
        // view - Joomla's sidebar matches the current page against each submenu's declared
        // option+view, and a controller/task-only link never matches any option+view.
        if (! $ordercode && $layout === 'default')
        {
            $ordercode = (new Ordercode)->getTemporaryOrdercode();
            (new Ordercode)->setOrdercode($ordercode);
        }

        // No reservation in progress yet (or it was lost) - send the admin back to step 1.
        if (! $ordercode && $layout !== 'default')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_SESSION_EXPIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=reservation', false));

            return;
        }

        $model = $this->getModel('Reservation');
        $state = $session->get(self::SESSION_KEY, []);

        // Plain links (not Toolbar::custom()) for cancel/control panel: this wizard's steps
        // each POST to their own action URL rather than sharing one adminForm, so the usual
        // Joomla.submitbutton() toolbar mechanism (which re-targets a single adminForm's hidden
        // task field) has no form to attach to here.
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_RESERVATION_TITLE'), 'fa fa-calendar-plus');

        $this->ordercode = $ordercode;
        $this->config    = (new Config)->get(['priceformat', 'valuta']);

        switch ($layout)
        {
            case 'quantity':
                $ticketid     = (int) ($state['ticketid'] ?? 0);
                $this->ticket = (new Ticket)->getTicketDetailsById($ticketid);
                break;

            case 'seatplan':
                $ticketid      = (int) ($state['ticketid'] ?? 0);
                $this->ticket  = (new Ticket)->getTicketDetailsById($ticketid);
                $this->seats   = $ticketid ? $model->getSeats($ticketid) : [];
                $this->summary = $model->getOrderSummary((string) $ordercode);
                break;

            case 'customer':
                $ticketid          = (int) ($state['ticketid'] ?? 0);
                $this->ticket      = $ticketid ? (new Ticket)->getTicketDetailsById($ticketid) : null;
                $this->summary     = $model->getOrderSummary((string) $ordercode);
                $this->customerData = $state['customer'] ?? [];
                break;

            case 'confirm':
                $this->summary       = $model->getOrderSummary((string) $ordercode);
                $this->customerData  = $state['customer'] ?? [];
                break;

            default:
                $eventid       = $app->getInput()->getInt('eventid', 0);
                $this->events  = $model->getUpcomingEvents();
                $this->eventid = $eventid;
                $this->tickets = $eventid ? $model->getTicketsForEvent($eventid) : [];
                break;
        }

        parent::display($tpl);
    }
}
