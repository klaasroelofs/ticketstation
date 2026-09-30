<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\Model\OrderModel;

/**
 * Ticketstation Order Controller
 * @since  0.2.11
 */
class OrderController extends BaseController
{
    private $session;
    private $amount;
    private $id;
    private $togo;
    private $eventid;
    private $ordercode;
    private $error;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();

        $this->amount    = $jinput->get('amount', '0', 'int');
        $this->session   = $jinput->get('ordercode', '0', 'int');
        $this->id        = $jinput->get('ticketid', '0', 'int');
        $this->togo      = $jinput->get('togo', '0', 'int');
        $this->eventid   = $jinput->get('eventid', '0', 'int');

        $this->ordercode = Factory::getApplication()->getSession()->get('ordercode');
    }

    /**
     * Showing a message to the user.
     *
     * @param $type
     * @param $message
     *
     * @since 1.0.0
     */
    private function showMessage($type, $message)
    {
        $msg = '<div class="' . $type . '">' . $message . '</div>';

        // ok: the tickets were added, so the event page offers the way to the basket
        $arr = ['status' => '666', 'msg' => $msg, 'ok' => str_contains($type, 'ts-alert--success')];

        echo json_encode($arr);
        exit();
    }

    /**
     * When the client clicks on one of the add to cart buttons.
     *
     * @since 1.0.0
     */
    public function buyticket()
    {

        if ( ! $this->performOrderCheck())
        {
            $this->showMessage('ts-alert ts-alert--danger', $this->error);
        }

        $config   		= (new Config)->get(['variable_transcosts', 'transactioncosts', 'transcosts', 'pro_installed', 'show_waitinglist']);
        $tickets  		= (new Ticket)->getTicketDetailsById($this->id);
        $ticketssold 	= (new Ticket)->getTicketsSoldById($this->id);
        $pricing  		= (new Amount)->calculateVatFromPrice($tickets->ticketprice, $tickets->vat_percentage);
        $basket   		= (new Order)->getOrdersByOrdercode($this->id);
        $newTotal 		= count($basket) + $this->amount;

        if ($tickets->max_qty != 0)
        {
            if ($newTotal > $tickets->max_qty)
            {
                $this->showMessage('ts-alert ts-alert--danger', Text::sprintf('COM_TICKETSTATION_MAX_ORDER_PER_TICKET', $tickets->max_qty));
            }

            if (count($basket) >= $tickets->max_qty)
            {
                $this->showMessage('ts-alert ts-alert--danger', Text::sprintf('COM_TICKETSTATION_MAX_ORDER_PER_TICKET', $tickets->max_qty));
            }
        }

        if ($tickets->min_qty != 0)
        {
            if ($tickets->min_qty > $newTotal)
            {
                $this->showMessage('ts-alert ts-alert--danger', Text::sprintf('COM_TICKETSTATION_MIN_ORDER_PER_TICKET', $tickets->min_qty));
            }
        }

        // Tickets left for this ticket: its own Capacity, or the pool of its parent when the
        // parent shares its capacity with the child tickets.
        $available = Availability::forPurchase((int) $this->id);

        if ($this->amount > $available)
        {
            $this->showMessage('ts-alert ts-alert--danger', Text::_($config->show_waitinglist ? 'COM_TICKETSTATION_ADD_TO_WAITINGLIST' : 'COM_TICKETSTATION_EVENT_SOLD_OUT'));
        }

        if ($config->variable_transcosts == 1)
        {
            $ticket_fee = (($tickets->ticketprice / 100) * $config->transcosts);
        } else {
            $ticket_fee = 0;
        }

        $post  = Factory::getApplication()->getInput()->post->getArray();
        //$model = $this->getModel('order');
        $models = new OrderModel();

        $post['vat']                 = $pricing['vat_amount'];
        $post['price_excluding_vat'] = $pricing['price_excluding_vat'];
        $post['vat_percentage']      = $pricing['vat_percentage'];
        $post['requires_seat']       = $this->checkSeatRequirement($config, $tickets);
        $post['price']               = $tickets->ticketprice;
        $post['fees']                = $ticket_fee;
        $post['orderdate']           = date('Y-m-d H:i:s', time());
        $post['ipaddress']           = $_SERVER['REMOTE_ADDR'];
        $post['ordercode']           = $this->ordercode;
        $post['ticketid']            = $this->id;
        $post['eventid']             = $this->eventid;

        // Saving the order rows, all or nothing, while the capacity still allows it; another
        // customer may have taken the last tickets since the check above.
        if ( ! Availability::reserve((int) $this->id, (int) $this->amount, fn () => $models->store($post)))
        {
            $this->showMessage('ts-alert ts-alert--danger', Text::_('COM_TICKETSTATION_EVENT_SOLD_OUT'));
        }

        // "2 × Adult added to your basket"
        $this->showMessage('ts-alert ts-alert--success', Text::sprintf('COM_TICKETSTATION_ADDED_TO_BASKET', (int) $this->amount, htmlspecialchars($tickets->ticketname, ENT_QUOTES, 'UTF-8')));
    }

    /**
     * Adds a ticket to the waitinglist, it may be processed later on.
     *
     * @since 1.0.0
     */
    function waitinglist()
    {

        if ( ! $this->performOrderCheck())
        {
            $this->showMessage('ts-alert ts-alert--danger', $this->error);
        }

        $config  = (new Config)->getPartialConfig(['pro_installed', 'show_waitinglist']);
        $tickets = (new Ticket)->getTicketDetailsById($this->id);

        $requires_seat = $this->checkSeatRequirement($config, $tickets);
        $ip_address    = $_SERVER['REMOTE_ADDR'];

        $db = Factory::getContainer()->get('DatabaseDriver');

        // Note: OrderModel::store() only ever writes to the orders table (it ignores a
        // second table-name argument), so waiting-list signups are inserted directly here
        // instead - matching how WaitingList::processWaitingListItem() reads this table back.
        for ($i = 0, $n = $this->amount; $i < $n; $i++)
        {
            $entry = new \stdClass();
            $entry->ticketid      = $this->id;
            $entry->eventid       = $this->eventid;
            $entry->userid        = 0;
            $entry->ordercode     = $this->ordercode;
            $entry->confirmed     = 0;
            $entry->processed     = 0;
            $entry->ip_address    = $ip_address;
            $entry->requires_seat = $requires_seat;
            // validation_token is NOT NULL + UNIQUE (admin/sql/updates/mysql/2.0.14.sql) and
            // authorises the guest waitinglist-confirmation link in ValidateController::waitinglist().
            $entry->validation_token = bin2hex(random_bytes(32));

            if ( ! $db->insertObject('#__ticketstation_waitinglist', $entry))
            {
                $this->showMessage('ts-alert ts-alert--danger', Text::_('COM_TICKETSTATION_FAILED_SAVING_CART') . ' - 112');
            }
        }

        $message = str_replace('%%AMOUNT%%', $this->amount, Text::_('COM_TICKETSTATION_ADDED_TO_WAITINGLIST'));
        $this->showMessage('ts-alert ts-alert--success', $message);
    }

    /**
     * Checks if this order requires a seat.
     *
     * @param $config
     * @param $tickets
     *
     * @return int
     *
     * @since 1.0.0
     */
    private function checkSeatRequirement($config, $tickets)
    {
        if ($config->pro_installed == 1)
        {
            if ($tickets->show_seatplans == 1)
            {
                // Getting pro settings
                $type_seat = (new Ticket)->getticketstationProTicketDetails($this->id);

                // Setting the seat requirement.
                return ($type_seat->type == 1) ? 1 : 0;
            }
            else
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
    }

    /**
     * Preform pre-order check.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function performOrderCheck()
    {
        $this->error = null;

        if ($this->session == 0)
        {
            $this->error = Text::_('COM_TICKETSTATION_EVENT_FAILED_ADD_TO_CART');

            return false;
        }

        if ($this->amount == 0)
        {
            $this->error = Text::_('COM_TICKETSTATION_EVENT_NO_AMOUNT');

            return false;
        }

        if ($this->ordercode != $this->session)
        {
            $this->error = Text::_('COM_TICKETSTATION_EVENT_FAILED_ADD_TO_CART') . '#100';

            return false;
        }

        // Only what the ticket page offers this visitor: a published ticket, and in test or
        // bypass mode only to logged-in users.
        if ( ! Shop::sells((int) $this->id))
        {
            $this->error = Text::_('COM_TICKETSTATION_TICKET_NOT_AVAILABLE');

            return false;
        }

        return true;
    }

    /**
     * Updating the percentage available.
     *
     * @since 1.0.0
     */
    public function updateavailable()
    {
        ## Same figures as the availability bar in the event view (all variants of a parent)
        $availability = Availability::summary((int) $this->id);

        ## Calculate percentage available tickets
        $percentage_available = $availability->capacity > 0 ? round((($availability->available / $availability->capacity) * 100), 0) : 0;

        $update = '';

        $update = $percentage_available . '%';

        echo $update;

        exit();

    }

    /**
     * Updating the cart view.
     *
     * @since 1.0.0
     */
    public function updatecart()
    {
        $order = new Order;
        $TicketstationFunctions = new TicketstationFunctions();

        // Getting the config
        $config  = (new Config)->getPartialConfig(['priceformat', 'valuta', 'show_waitinglist', 'variable_transcosts']);
        $ordered = $order->getOrdersCountByOrdercode('#__ticketstation_orders');
        $waiting = $order->getOrdersCountByOrdercode('#__ticketstation_waitinglist');

        // Show more or one ticket(s)
        $tickets = ($ordered > 1) ? Text::_('COM_TICKETSTATION_TICKETS') : Text::_('COM_TICKETSTATION_TICKET');

        $totals     = OrderTotals::get($this->ordercode, true);
        $ordertotal = $totals->total;
        $fees       = $totals->fees;

        $update = '';

        // Transaction costs switched off in the configuration: no fees row at all.
        $feesRow = '';

        if ($totals->fee_type != OrderTotals::FEE_NONE) {
            $feesRow = '<tr>
								<td>' . Text::_('COM_TICKETSTATION_FEES') . '</td>
								<td>' . $TicketstationFunctions->showprice($config->priceformat, $fees, $config->valuta) . '</td>
							</tr>';
        }

        if ($ordered > 0) {
            $update .= '<table class="ticketstation-basket-table">
							<tr>
								<td>' . $ordered . ' ' . $tickets . '</td>
								<td>' . $TicketstationFunctions->showprice($config->priceformat, ($ordertotal - $fees), $config->valuta) . '</td>
							</tr>
							' . $feesRow . '
							<tr>
								<td><strong>' . Text::_('COM_TICKETSTATION_ORDERTOTAL_CART') . '</strong></td>
								<td><strong>' . $TicketstationFunctions->showprice($config->priceformat, $ordertotal, $config->valuta) . '</strong></td>
							</tr>
						</table>';
        } elseif ($config->show_waitinglist != 1 || $waiting < 1) {
            // Not "empty" when the customer only has tickets on the waiting list.
            $update .= '<p id="empty_cart" class="ticketstation-basket-empty">' . Text::_('COM_TICKETSTATION_EMPTY_CART') . '</p>';
        }

        if ($config->show_waitinglist == 1 && $waiting > 0)
        {
            $waitingtickets = ($waiting > 1) ? Text::_('COM_TICKETSTATION_TICKETS') : Text::_('COM_TICKETSTATION_TICKET');

            $update .= '<p id="waitinglist_items" class="ticketstation-basket-waiting">' . $waiting . ' ' . $waitingtickets . ' ' . Text::_('COM_TICKETSTATION_IN_WAITNGLIST') . '</p>';
        }

        echo '<div>' . $update . '</div>';
        exit();
    }

    /**
     *Removing tickets from the cart.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function remove()
    {
        // Reached via a plain GET link in cart/default.php (the AJAX handler in that
        // template targets a CSS class the markup doesn't actually have, so it never
        // fires) - check the token as a query param, not just POST.

        $post    = [];
        $jinput  = Factory::getApplication()->getInput();
        $orderid = $jinput->get('orderid', '0', 'int');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $ordercode = Factory::getApplication()->getSession()->get('ordercode');

        $query = $db->getQuery(true)
            ->select(['o.*'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->where($db->quoteName('orderid') . " = " . (int) $orderid)
            ->where($db->quoteName('ordercode') . " = " . (int) $ordercode);

        $db->setQuery($query);
        $tdata = $db->loadObject();

        if (empty($tdata))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));

            return false;
        }

        if ($tdata->seat_sector != 0)
        {
            // Reset the seat state
            (new Ticket)->resetSeatSateForProVersion($tdata->orderid);
        }

        // Removing the order from the database.
        if ( ! (new Order)->removeSingleOrderFromDatabase($orderid))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVE_ORDER_FAILED'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));

            return false;
        }

        PluginHelper::importPlugin('etickets');
        Factory::getApplication()->triggerEvent('onAfterRemovingOrder', [$post]);

        Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVED_ORDER'), 'success');
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));
    }

    /**
     * Removes a whole cart line: every unpaid ticket of one ticket type in this cart. Seats are
     * removed one by one with remove(), so rows with a seat are left alone here.
     *
     * @return void
     */
    public function removeTicket()
    {
        $app       = Factory::getApplication();
        $db        = Factory::getContainer()->get('DatabaseDriver');
        $ticketid  = $app->getInput()->get('ticketid', 0, 'int');
        $ordercode = (int) $app->getSession()->get('ordercode');
        $cartUrl   = Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : ''), false);

        $query = $db->getQuery(true)
            ->select($db->quoteName('orderid'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->where($db->quoteName('ticketid') . ' = ' . $ticketid)
            ->where('COALESCE(' . $db->quoteName('seat_sector') . ', 0) = 0')
            ->where($db->quoteName('paid') . ' != 1');

        $db->setQuery($query);
        $orderids = $db->loadColumn();

        if (!$ordercode || !$orderids)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER'), 'error');
            $app->redirect($cartUrl);
        }

        foreach ($orderids as $orderid)
        {
            if ( ! (new Order)->removeSingleOrderFromDatabase($orderid))
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVE_ORDER_FAILED'), 'error');
                $app->redirect($cartUrl);
            }
        }

        PluginHelper::importPlugin('etickets');
        $app->triggerEvent('onAfterRemovingOrder', [[]]);

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVED_ORDER'), 'success');
        $app->redirect($cartUrl);
    }

    /**
     * Removing tickets from the waitinglist.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function removeWaiting()
    {
        // Reached via a plain GET link in cart/default.php (same AJAX-class-mismatch
        // situation as remove() above) - check the token as a query param, not just POST.

        $jinput = Factory::getApplication()->getInput();

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $conditions = [
            $db->quoteName('id') . ' = ' . (int) $jinput->get('id', '0', 'int'),
            $db->quoteName('ordercode') . ' = ' . (int) Factory::getApplication()->getSession()->get('ordercode'),
        ];

        $query->delete($db->quoteName('#__ticketstation_waitinglist'))
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVE_ORDER_FAILED'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));

            return false;
        }

        Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_REMOVED_ORDER'), 'success');
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));
    }

    public function itemcount ()
    {
        $order = new Order;

        // Tickets on the waiting list count too, so the basket badge reacts when joining it.
        echo (int) $order->getOrdersCountByOrdercode() + (int) $order->getOrdersCountByOrdercode('#__ticketstation_waitinglist');
    }
}