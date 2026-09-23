<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\Model\OrderModel;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

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
    private $userid;
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
        //$this->eventname = $jinput->get('parentname', '0', 'cmd');

        $this->ordercode = Factory::getApplication()->getSession()->get('ordercode');
        //$this->userid    = JFactory::getUser()->id;
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
        $msg = '<div class="' . $type . '" style="font-size:97%;">' . $message . '</div>';
        $arr = ['status' => '666', 'msg' => $msg];

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
            $this->showMessage('alert alert-danger', $this->error);
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
                $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_MAX_ORDER_PER_TICKET') . $tickets->max_qty);
            }

            if (count($basket) >= $tickets->max_qty)
            {
                $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_MAX_ORDER_PER_TICKET') . $tickets->max_qty);
            }
        }

        if ($tickets->min_qty != 0)
        {
            if ($tickets->min_qty > $newTotal)
            {
                $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_MIN_ORDER_PER_TICKET') . $tickets->min_qty);
            }
        }

        // Setting the total ticket amount
        $totaltickets = $tickets->starting_total_tickets - $ticketssold;

        if ($tickets->parent > 0)
        {
            // Getting parent ticket details
            $parent_ticket = (new Ticket)->getTicketDetailsById($tickets->parent);
            $parent_ticketssold 	= (new Ticket)->getTicketsSoldById($tickets->parent);
            $totaltickets  = ($tickets->counter_choice == 0) ? ($parent_ticket->starting_total_tickets - $parent_ticketssold) : $totaltickets;
        }

        if ($this->amount > $totaltickets && $config->show_waitinglist && $tickets->parent == 0)
        {
            $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_ADD_TO_WAITINGLIST'));
        }

        if ($this->amount > $totaltickets && ! $config->show_waitinglist)
        {
            $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_EVENT_SOLD_OUT'));
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
        //$post['require_information'] = $tickets->named_tickets_required;
        //$post['userid']              = ($this->userid) ? $this->userid : 0;
        $post['ordercode']           = $this->ordercode;
        $post['ticketid']            = $this->id;
        $post['eventid']             = $this->eventid;

        // Instantiate ticket class.♥
        $ticket = new Ticket;

        for ($i = 0, $n = $this->amount; $i < $n; $i++)
        {
            // Saving the order data.
            if (!$models->store($post))
            {
                $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_FAILED_SAVING_CART'));
            }

            if ($tickets->counter_choice == 1)
            {
                // Decreasing the ticket total.
                if ( ! $ticket->decreaseTicketTotals($this->id))
                {
                    $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_DB_QUERY_FAILED') . ' - 102');
                }
            }

            if ($tickets->counter_choice == 0)
            {
                // Check if this ticket has a parent or not.
                $ticketid = ($tickets->parent == 0) ? $this->id : $tickets->parent;

                // Decreasing the ticket total of the parent ticket.
                if ( ! $ticket->decreaseTicketTotals($ticketid))
                {
                    $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_DB_QUERY_FAILED') . ' - 101');
                }
            }
        }

        if ($this->amount == 1)
        {
            $this->showMessage('alert alert-success', $this->amount . Text::_('COM_TICKETSTATION_EVENT_ADDED_TO_CART1'));
        } else {
            $this->showMessage('alert alert-success', $this->amount . Text::_('COM_TICKETSTATION_EVENT_ADDED_TO_CART'));
        }
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
            $this->showMessage('alert alert-danger', $this->error);
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
                $this->showMessage('alert alert-danger', Text::_('COM_TICKETSTATION_FAILED_SAVING_CART') . ' - 112');
            }
        }

        $message = str_replace('%%AMOUNT%%', $this->amount, Text::_('COM_TICKETSTATION_ADDED_TO_WAITINGLIST'));
        $this->showMessage('alert alert-success', $message);
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

        return true;
    }

    /**
     * Updating the percentage available.
     *
     * @since 1.0.0
     */
    public function updateavailable()
    {
        $tickets  		= (new Ticket)->getTicketDetailsById($this->id);
        $ticketssold 	= (new Ticket)->getTicketsSoldById($this->id);

        ## Determine available tickets
        $available_tickets = $tickets->starting_total_tickets - $ticketssold;

        ## Calculate percentage available tickets
        $percentage_available = round((($available_tickets / $tickets->starting_total_tickets) * 100), 0);

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
        $getAmount = new getAmount();
        $TicketstationFunctions = new TicketstationFunctions();

        // Getting the config
        $config  = (new Config)->getPartialConfig(['priceformat', 'valuta', 'show_waitinglist', 'variable_transcosts']);
        $ordered = $order->getOrdersCountByOrdercode('#__ticketstation_orders');
        $waiting = $order->getOrdersCountByOrdercode('#__ticketstation_waitinglist');

        // Show more or one ticket(s)
        $tickets = ($ordered > 1) ? Text::_('COM_TICKETSTATION_TICKETS') : Text::_('COM_TICKETSTATION_TICKET');

        $ordertotal = $getAmount->_getAmount($this->ordercode);
        $fees       = $getAmount->_getFees($this->ordercode);
        //ordertotal = $total; //- $fees;

        $update = '';

        // Transaction costs switched off in the configuration: no fees row at all.
        $feesRow = '';

        if ($config->variable_transcosts != 2) {
            $feesRow = '<tr style="height: 40px;">
								<td>' . Text::_('COM_TICKETSTATION_FEES') . '</td>
								<td style="text-align: right;">' . $TicketstationFunctions->showprice($config->priceformat, $fees, $config->valuta) . '</td>
							</tr>';
        }

        if ($ordered > 0) {
            $update .= '<table style="width: 250px;">
							<tr>
								<td>' . $ordered . ' ' . $tickets . '</td>
								<td style="text-align: right;">' . $TicketstationFunctions->showprice($config->priceformat, ($ordertotal - $fees), $config->valuta) . '</td>
							</tr>
							' . $feesRow . '
							<tr style="border-top: 1px solid #aaa;">
								<td><strong>' . Text::_('COM_TICKETSTATION_ORDERTOTAL_CART') . '</strong></td>
								<td style="text-align: right;"><strong>' . $TicketstationFunctions->showprice($config->priceformat, $ordertotal, $config->valuta) . '</strong></td>
							</tr>
						</table>';
        } elseif ($config->show_waitinglist != 1 || $waiting < 1) {
            // Not "empty" when the customer only has tickets on the waiting list.
            $update .= '<p id="empty_cart"><strong>' . Text::_('COM_TICKETSTATION_EMPTY_CART') . '</strong></p>';
        }

        if ($config->show_waitinglist == 1 && $waiting > 0)
        {
            $waitingtickets = ($waiting > 1) ? Text::_('COM_TICKETSTATION_TICKETS') : Text::_('COM_TICKETSTATION_TICKET');

            $update .= '<br/><br/><span id="waitinglist_items">' . $waiting . ' ' . $waitingtickets . ' <br/>' . Text::_('COM_TICKETSTATION_IN_WAITNGLIST') . '</span>';
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
            ->select(['o.*', 't.parent AS parentticket', 't.counter_choice'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('orderid') . " = " . (int) $orderid)
            ->where($db->quoteName('ordercode') . " = " . $ordercode);

        $db->setQuery($query);
        $tdata = $db->loadObject();

        if (empty($tdata))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . (($itemid = TicketstationFunctions::getSiteItemid()) ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $ticket = new Ticket;

        if ($tdata->counter_choice == 1)
        {
            // Increasing the ticket total
            $ticket->increaseTicketTotals($tdata->ticketid);
        }

        if ($tdata->counter_choice == 0)
        {
            // Increasing the ticket total
            $ticket->increaseTicketTotals($tdata->parentticket);
        }

        if ($tdata->seat_sector != 0)
        {
            // Reset the seat state
            $ticket->resetSeatSateForProVersion($tdata->orderid);
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