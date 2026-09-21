<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;

/**
 * Admin "new reservation" wizard: event/ticket -> quantity or seat selection -> customer ->
 * complete (paid/not yet paid). Mirrors the frontend cart/checkout flow but runs entirely
 * against the admin session, so it never touches the site session.
 *
 * @since 1.8.0
 */
class ReservationController extends BaseController
{
    private const SESSION_KEY = 'ticketstation.reservation';

    /**
     * Starts a brand new reservation: clears any previous wizard state and generates a fresh
     * temporary ordercode, then shows step 1 (event/ticket selection). When called with an
     * eventid (see complete()'s "start a new reservation immediately" redirect), that event is
     * preselected on step 1 instead of the picker starting empty.
     */
    public function start()
    {
        $eventid = Factory::getApplication()->getInput()->getInt('eventid', 0);

        $this->setState([]);

        $ordercode = (new Ordercode)->getTemporaryOrdercode();
        (new Ordercode)->setOrdercode($ordercode);

        $redirect = 'index.php?option=com_ticketstation&view=reservation';

        if ($eventid)
        {
            $redirect .= '&eventid=' . $eventid;
        }

        $this->setRedirect(Uri::base() . $redirect);
    }

    /**
     * Cancels the reservation in progress: removes any order rows already written for this
     * ordercode, frees any booked seats, and clears the wizard state.
     */
    public function cancel()
    {
        // Reached via a plain GET link in every reservation wizard step's
        // template - check the token as a query param, not just POST.
        $this->checkToken('request') or jexit(Text::_('JINVALID_TOKEN'));

        $ordercode = $this->getOrdercode();

        if ($ordercode)
        {
            $this->getModel('Reservation')->removeOrderRowsForOrdercode($ordercode);
        }

        $this->setState([]);
        Factory::getApplication()->getSession()->clear('ordercode');

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    /**
     * Step 1 -> step 2: stores the chosen ticket and routes to the quantity step or the seat
     * selection step, depending on whether the ticket has a seatplan.
     */
    public function selectTicket()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $ticketid = $jinput->getInt('ticketid', 0);

        if (! $ticketid || ! $this->getOrdercode())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_NO_TICKET_SELECTED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation');

            return;
        }

        $ticket = (new Ticket)->getTicketDetailsById($ticketid);

        if (! $ticket)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_NO_TICKET_SELECTED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation');

            return;
        }

        $state             = $this->getState();
        $state['ticketid'] = $ticketid;
        $state['eventid']  = $ticket->eventid;
        $this->setState($state);

        $layout = $ticket->show_seatplans == 1 ? 'seatplan' : 'quantity';

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=' . $layout);
    }

    /**
     * Step 2a: adds the requested number of non-seatplan order rows, mirroring
     * site/src/Controller/OrderController.php::buyticket() but for the admin session.
     */
    public function addQuantity()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $model  = $this->getModel('Reservation');

        $ordercode = $this->getOrdercode();
        $state     = $this->getState();
        $ticketid  = (int) ($state['ticketid'] ?? 0);
        $amount    = $jinput->getInt('amount', 0);

        if (! $ordercode || ! $ticketid || $amount < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_INVALID_QUANTITY'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=quantity');

            return;
        }

        $ticket  = (new Ticket)->getTicketDetailsById($ticketid);
        $config  = (new Config)->get(['variable_transcosts', 'transactioncosts', 'transcosts']);
        $pricing = (new Amount)->calculateVatFromPrice($ticket->ticketprice, $ticket->vat_percentage);
        $fee     = $config->variable_transcosts == 1 ? (($ticket->ticketprice / 100) * $config->transcosts) : 0;

        if ($amount > $ticket->totaltickets)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_SOLD_OUT'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=quantity');

            return;
        }

        for ($i = 0; $i < $amount; $i++)
        {
            $orderid = $model->insertOrderRow([
                'ordercode'           => $ordercode,
                'ticketid'            => $ticketid,
                'eventid'             => $ticket->eventid,
                'price'               => $ticket->ticketprice,
                'vat'                 => $pricing['vat_amount'],
                'price_excluding_vat' => $pricing['price_excluding_vat'],
                'vat_percentage'      => $pricing['vat_percentage'],
                'fees'                => $fee,
                'requires_seat'       => 0,
                'orderdate'           => date('Y-m-d H:i:s'),
                'ipaddress'           => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            if (! $orderid)
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED'), 'error');
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=quantity');

                return;
            }

            $model->adjustTicketTotal($ticketid, -1);
        }

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=customer');
    }

    /**
     * Step 2b (AJAX): reserves a single seat, mirroring the non-multiseat branch of
     * site/src/Controller/OrderseatedController.php::makeReservation() for the admin session.
     */
    public function makeReservation()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $model  = $this->getModel('Reservation');

        $coordId   = $jinput->getInt('id', 0);
        $ordercode = $this->getOrdercode();

        if (! $ordercode)
        {
            echo json_encode(['error' => '1', 'msg' => Text::_('COM_TICKETSTATION_NO_DATABASE_SESSION'), 'id' => 0]);
            $app->close();
        }

        $seat = $model->getSeatWithSettings($coordId);

        if (! $seat || $seat->booked == 1)
        {
            echo json_encode(['error' => '1', 'msg' => Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN'), 'id' => $coordId]);
            $app->close();
        }

        $ticket = (new Ticket)->getTicketDetailsById($seat->ticketid);

        if (! $ticket || $ticket->totaltickets == 0)
        {
            echo json_encode(['error' => '1', 'msg' => Text::_('COM_TICKETSTATION_SOLD_OUT'), 'id' => $coordId]);
            $app->close();
        }

        // Mirrors addQuantity() above - without this, seated orders never get a vat/vat_percentage/
        // price_excluding_vat/fees value at all (they default to NULL/0), so invoices for seated
        // tickets always show 0% VAT regardless of what's configured on the ticket.
        $config  = (new Config)->get(['variable_transcosts', 'transactioncosts', 'transcosts']);
        $pricing = (new Amount)->calculateVatFromPrice($ticket->ticketprice, $ticket->vat_percentage);
        $fee     = $config->variable_transcosts == 1 ? (($ticket->ticketprice / 100) * $config->transcosts) : 0;

        $orderid = $model->insertOrderRow([
            'ordercode'           => $ordercode,
            'ticketid'            => $seat->ticketid,
            'eventid'             => $ticket->eventid,
            'price'               => $ticket->ticketprice,
            'vat'                 => $pricing['vat_amount'],
            'price_excluding_vat' => $pricing['price_excluding_vat'],
            'vat_percentage'      => $pricing['vat_percentage'],
            'fees'                => $fee,
            'requires_seat'       => 1,
            'seat_sector'         => $coordId,
            'orderdate'           => date('Y-m-d H:i:s'),
            'ipaddress'           => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        if (! $orderid)
        {
            echo json_encode(['error' => '1', 'msg' => Text::_('COM_TICKETSTATION_ORDER_FAILED'), 'id' => $coordId]);
            $app->close();
        }

        $model->updateSeatCoords($orderid, $coordId);
        $model->adjustTicketTotal($seat->ticketid, -1);

        $state             = $this->getState();
        $state['eventid']  = $ticket->eventid;
        $state['ticketid'] = $seat->ticketid;
        $this->setState($state);

        echo json_encode([
            'error'  => '0',
            'msg'    => Text::_('COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED'),
            'id'     => $coordId,
            'seatid' => $seat->row_name !== '' ? $seat->row_name . $seat->seatid : $seat->seatid,
        ]);
        $app->close();
    }

    /**
     * Step 2b (AJAX): releases a previously reserved seat, mirroring
     * site/src/Controller/OrderseatedController.php::removeseat().
     */
    public function removeSeat()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $model  = $this->getModel('Reservation');

        $coordId   = $jinput->getInt('id', 0);
        $ordercode = $this->getOrdercode();

        $order = $model->getOrderBySeat((string) $ordercode, $coordId);

        if (! $order || ! $order->orderid)
        {
            echo json_encode(['error' => '1', 'msg' => Text::_('COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER'), 'id' => $coordId]);
            $app->close();
        }

        $model->deleteOrderRow((int) $order->orderid);
        $model->adjustTicketTotal((int) $order->ticketid, 1);
        $model->freeSeatCoords($coordId);

        echo json_encode(['error' => '0', 'msg' => Text::_('COM_TICKETSTATION_THIS_SEAT_IS_REMOVED'), 'id' => $coordId]);
        $app->close();
    }

    /**
     * Step 2b -> step 3: the admin is done picking seats.
     */
    public function finishSeats()
    {
        $app       = Factory::getApplication();
        $ordercode = $this->getOrdercode();

        if (! $ordercode || count($this->getModel('Reservation')->getOrderSummary($ordercode)) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_NO_SEATS_SELECTED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=seatplan');

            return;
        }

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=customer');
    }

    /**
     * Step 3 -> step 4: finds or creates the client and remembers them for the complete() step.
     */
    public function saveCustomer()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $model  = $this->getModel('Reservation');

        $ordercode = $this->getOrdercode();

        $firstname    = $jinput->getString('firstname', '');
        $lastname     = $jinput->getString('lastname', '');
        $emailaddress = $jinput->getString('emailaddress', '');
        $phonenumber  = $jinput->getString('phonenumber', '');

        $data = [
            'firstname'    => $firstname,
            'name'         => $lastname,
            'emailaddress' => $emailaddress,
            'phonenumber'  => $phonenumber,
        ];

        if (! $ordercode || $data['name'] === '' || $data['emailaddress'] === '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_CUSTOMER_REQUIRED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=customer');

            return;
        }

        $clientid = $model->findOrCreateClient($data);

        if (! $clientid)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=customer');

            return;
        }

        $state             = $this->getState();
        $state['clientid'] = $clientid;
        // Kept alongside clientid (rather than re-derived from the clients table) so that
        // navigating Back to this step re-shows exactly what was typed, and step 4 can display
        // it for review, even though findOrCreateClient() may have matched an existing client
        // whose stored name/phone could differ slightly.
        $state['customer'] = [
            'firstname'    => $firstname,
            'lastname'     => $lastname,
            'emailaddress' => $emailaddress,
            'phonenumber'  => $phonenumber,
        ];
        $this->setState($state);

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=confirm');
    }

    /**
     * Step 4: finalizes the ordercode via the same Order::update() the frontend checkout uses,
     * and - when "already paid" was chosen - marks it paid and immediately creates + sends the
     * tickets via the same PaymentAPI methods the Boxoffice screen already uses. When "not yet
     * paid" is chosen, the order is simply left unpaid for later processing via Boxoffice.
     */
    public function complete()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $ordercode = $this->getOrdercode();
        $state     = $this->getState();
        $clientid  = (int) ($state['clientid'] ?? 0);
        $paid      = $jinput->getInt('paid', 1);
        $startNew  = $jinput->getBool('start_new', true);

        if (! $ordercode || ! $clientid)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation');

            return;
        }

        if (! (new Order)->update($clientid, $ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation&layout=confirm');

            return;
        }

        // Order::update() rewrites the ordercode to its final value and stores that in the
        // session - read it back so the rest of this method (and the redirect) use it.
        $finalOrdercode = Factory::getApplication()->getSession()->get('ordercode');

        if ($paid == 1)
        {
            $payment = new PaymentAPI((int) $finalOrdercode);
            $payment->updateOrder();
            $payment->createTickets();
            $payment->sendTickets();
        }

        $lastEventId = (int) ($state['eventid'] ?? 0);

        $this->setState([]);
        Factory::getApplication()->getSession()->clear('ordercode');

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_RESERVATION_COMPLETED'));

        if ($startNew)
        {
            $redirect = 'index.php?option=com_ticketstation&controller=reservation&task=start';

            if ($lastEventId)
            {
                $redirect .= '&eventid=' . $lastEventId;
            }

            $this->setRedirect(Uri::base() . $redirect);

            return;
        }

        $this->setRedirect(
            Uri::base() . 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $finalOrdercode
        );
    }

    private function getOrdercode()
    {
        return Factory::getApplication()->getSession()->get('ordercode');
    }

    private function getState(): array
    {
        return Factory::getApplication()->getSession()->get(self::SESSION_KEY, []);
    }

    private function setState(array $state): void
    {
        Factory::getApplication()->getSession()->set(self::SESSION_KEY, $state);
    }
}
