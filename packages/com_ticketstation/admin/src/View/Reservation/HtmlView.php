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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

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

        // Every step page counts as activity for the cleanup of unfinished orders, as does the
        // ping from reservation.js below.
        $rows = $model->touchOrderRows((string) $ordercode);

        // Steps 3 and 4 are only reached with tickets in the reservation: none left means the
        // cleanup removed them (see ReservationController::restartIfExpired()).
        if ($rows === 0 && in_array($layout, ['customer', 'confirm'], true))
        {
            $eventid = (int) ($state['eventid'] ?? 0);

            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_EXPIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ticketstation&controller=reservation&task=start' . ($eventid ? '&eventid=' . $eventid : ''), false));

            return;
        }

        // Plain links (not Toolbar::custom()) for cancel/control panel: this wizard's steps
        // each POST to their own action URL rather than sharing one adminForm, so the usual
        // Joomla.submitbutton() toolbar mechanism (which re-targets a single adminForm's hidden
        // task field) has no form to attach to here.
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_RESERVATION_TITLE'), 'fa fa-calendar-plus');

        // The ordercode and wizard state live in the admin session, so an admin who lingers on
        // one step (typing customer details during a phone call) would otherwise be logged out
        // and lose the reservation in progress. Pings the server while the page is open.
        HTMLHelper::_('behavior.keepalive');

        $this->getDocument()->addScriptOptions('com_ticketstation.reservation', [
            'url'   => Route::_('index.php?option=com_ticketstation&controller=reservation&task=touch&format=raw', false),
            'token' => Session::getFormToken(),
        ]);
        $this->getDocument()->getWebAssetManager()->registerAndUseScript(
            'com_ticketstation.reservation',
            Uri::base() . 'components/com_ticketstation/assets/js/reservation.js',
            [],
            ['defer' => true],
            ['core']
        );

        Docs::toolbarButton('reservation');

        $this->ordercode = $ordercode;
        $this->lines     = $model->getReservationLines((string) $ordercode);
        $this->config    = (new Config)->get(['priceformat', 'valuta']);

        switch ($layout)
        {
            case 'quantity':
                $ticketid     = (int) ($state['ticketid'] ?? 0);
                $this->ticket    = (new Ticket)->getTicketDetailsById($ticketid);
                $this->available = Availability::forTicket($ticketid);
                break;

            case 'seatplan':
                $ticketid      = (int) ($state['ticketid'] ?? 0);
                $this->ticket    = (new Ticket)->getTicketDetailsById($ticketid);
                $this->available = Availability::forTicket($ticketid);
                $this->seats      = $ticketid ? $model->getSeats($ticketid) : [];
                $this->categories = $ticketid ? SeatplanSettings::priceCategories($ticketid) : [];
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
                $this->events = $model->getUpcomingEvents();

                // Coming back (Back button, or another ticket for the same event) the event of
                // the ticket chosen last is selected again. A chosen event, also "none", wins.
                $eventid = $app->getInput()->get('eventid', null) !== null
                    ? $app->getInput()->getInt('eventid', 0)
                    : (int) ($state['eventid'] ?? 0);

                if (! in_array($eventid, array_map('intval', array_column($this->events, 'eventid')), true))
                {
                    $eventid = 0;
                }

                $this->eventid = $eventid;
                $this->tickets = $eventid ? $model->getTicketsForEvent($eventid) : [];
                break;
        }

        parent::display($tpl);
    }
}
