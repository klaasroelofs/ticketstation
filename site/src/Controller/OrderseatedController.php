<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\Model\OrderModel;
use Ticketstation\Component\Ticketstation\Site\Model\SeatedeventModel;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Orderseated Controller
 * @since  0.2.11
 */
class OrderseatedController extends BaseController {

    private $ordercode;
    private $id;
    private $eventid;
    private $userid;

    function __construct() {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();

        $this->id  = $jinput->get('id', '0', 'int');
        ## Getting the global DB session
        $session   = Factory::getApplication()->getSession();
        ## Getting the orderid if there is one.
        $this->ordercode = $session->get('ordercode');

        ## Check if the user is logged in.
        //$user = Factory::getApplication()->getIdentity();
        //$this->userid = $user->id;

    }

    function removeseat(){

        $jinput = Factory::getApplication()->getInput();

        ## Getting the id
        $id     = $jinput->get('id', '0', 'int');

        ## Get database information
        $db     = Factory::getContainer()->get('DatabaseDriver');

        ## Check if there are any orders, otherwise stop the script.
        $query = 'SELECT orderid, ticketid FROM #__ticketstation_orders
				  WHERE ordercode = '.(int)$this->ordercode.'
				  AND seat_sector = '.(int)$id;

        $db->setQuery($query);
        $item = $db->loadObject();

        ## Check if order is valid
        if(empty($item) || $item->orderid == 0){

            $msg = Text::_( 'COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        $seat = SeatplanSettings::forSeat((int) $id);

        $query = 'DELETE FROM #__ticketstation_orders WHERE orderid = '.(int)$item->orderid.'';

        ## Do the query now
        $db->setQuery( $query );

        ## When query goes wrong.. Show message with error.
        if (!$db->execute()) {

            $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_ORDER_TABLE' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ## Give the seat back to the counter makeReservation() took it from: the seat's own
        ## ticket, whatever price category the order ended up with.
        $counterid = $seat ? (int) $seat->ticketid : (int) $item->ticketid;

        $query = 'UPDATE #__ticketstation_tickets SET totaltickets = totaltickets+1
				  WHERE ticketid = '.$counterid;

        ## Do the query now
        $db->setQuery( $query );

        ## When query goes wrong.. Show message with error.
        if (!$db->execute()) {

            $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_ORDER_TABLE' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ### NOW UPADTE THE COORDS TABLE
        $query = 'UPDATE #__ticketstation_seatplancoords
				  SET orderid = 0, booked = 0
				  WHERE id = '.(int)$id.' ';

        ## Do the query now
        $db->setQuery( $query );

        ## When query goes wrong.. Show message with error.
        if (!$db->execute()) {

            $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_COORDS_TABLE' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        $msg = Text::_( 'COM_TICKETSTATION_THIS_SEAT_IS_REMOVED' );
        $arr = array('error' => '0', 'msg' => $msg, 'id' => $id,
            'background' => $seat ? $seat->background_color : '', 'color' => $seat ? $seat->font_color : '');
        echo json_encode($arr);
        exit();
    }

    /**
     * Switches a picked seat to another price category (Multi Seat = Yes with child
     * tickets) and re-prices its order row accordingly.
     */
    function updateSeat(){

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $jinput = Factory::getApplication()->getInput();

        ## Getting the values from the POST.
        $ticketid  = $jinput->get('ticketid', '0', 'INT');
        $orderid   = $jinput->get('orderid', '0', 'INT');
        $ordercode = $this->ordercode; // from session, set in the constructor — ignore the client-supplied 'ordercode' param entirely

        $query = $db->getQuery(true)
            ->select(['orderid', 'seat_sector'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $orderid)
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query);
        $order = $db->loadObject();

        $seat = ($order && $order->seat_sector) ? SeatplanSettings::forSeat((int) $order->seat_sector) : null;

        ## Only a published child ticket of the seat's own ticket is a valid price category,
        ## so a visitor can't put an arbitrary (cheaper) ticket on the seat.
        $ticket = null;

        if ($seat && $seat->multi_seat == 1) {

            $query = $db->getQuery(true)
                ->select(['ticketid', 'ticketprice', 'vat_percentage'])
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('ticketid') . ' = ' . (int) $ticketid)
                ->where($db->quoteName('parent') . ' = ' . (int) $seat->ticketid)
                ->where($db->quoteName('published') . ' = 1');

            $db->setQuery($query);
            $ticket = $db->loadObject();
        }

        if (!$ticket) {
            echo Text::_('COM_TICKETSTATION_DB_QUERY_FAILED') . ' (Error: #101)';
            exit();
        }

        $pricing = (new Amount)->calculateVatFromPrice($ticket->ticketprice, $ticket->vat_percentage);

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_orders'))
            ->set($db->quoteName('ticketid') . ' = ' . (int) $ticket->ticketid)
            ->set($db->quoteName('price') . ' = ' . $db->quote($ticket->ticketprice))
            ->set($db->quoteName('fees') . ' = ' . $db->quote($this->getSeatFee($ticket->ticketprice)))
            ->set($db->quoteName('vat') . ' = ' . $db->quote($pricing['vat_amount']))
            ->set($db->quoteName('price_excluding_vat') . ' = ' . $db->quote($pricing['price_excluding_vat']))
            ->set($db->quoteName('vat_percentage') . ' = ' . $db->quote($pricing['vat_percentage']))
            ->where($db->quoteName('orderid') . ' = ' . (int) $order->orderid)
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        if (!$db->execute()) {
            echo Text::_('COM_TICKETSTATION_DB_QUERY_FAILED') . ' (Error: #101)';
            exit();
        }

        echo Text::_('COM_TICKETSTATION_CHANGED_TICKET');
        exit();
    }

    function loadSeat(){

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $jinput = Factory::getApplication()->getInput();

        ## Getting the values from the POST.
        $id = $jinput->get('id', '0', 'INT');

        $item = SeatplanSettings::forSeat((int) $id);

        if (!$item) {
            exit();
        }

        $label  = htmlspecialchars($item->row_name . $item->seatid, ENT_QUOTES, 'UTF-8');
        $remove = '<button id="'.(int)$id.'" class="btn btn-block btn-danger remove">'.Text::_( 'COM_TICKETSTATION_REMOVE_SEAT' ).' '.$label.' '.Text::_( 'COM_TICKETSTATION_REMOVE_FROM' ).'</button>';

        ## With Multi Seat = Yes, the published child tickets are the price categories to choose from.
        $items = [];

        if ($item->multi_seat == 1) {

            $query = $db->getQuery(true)
                ->select(['ticketid', 'ticketname', 'ticketprice'])
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('parent') . ' = ' . (int) $item->ticketid)
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ticketprice') . ' DESC');

            $db->setQuery($query);
            $items = $db->loadObjectList();
        }

        if (count($items) == 0) {
            ## ONLY A REMOVE BUTTON IS NEEDED -- NO EXTRA OPTIONS TO CHOOSE
            echo $remove;
            exit();
        }

        ## A DROPDOWN IS NEEDED TO CHOOSE PRICE/TICKET
        $sql = 'SELECT ticketid
				FROM #__ticketstation_orders
				WHERE orderid = '.(int)$item->orderid.'';

        $db->setQuery($sql);
        $current = (int) $db->loadResult();

        $query = 'SELECT priceformat, valuta
				  FROM #__ticketstation_config
				  WHERE configid = 1';

        $db->setQuery($query);
        $config = $db->loadObject();

        $options = [];

        foreach ($items as $row) {
            $price     = (new TicketstationFunctions)->showprice($config->priceformat, $row->ticketprice, $config->valuta);
            $options[] = HTMLHelper::_('select.option', $row->ticketid, $row->ticketname .' - '.$price);
        }

        echo HTMLHelper::_('select.genericlist', $options, (string) (int) $item->orderid, 'class="input ticketid" style="width:100%;"', 'value', 'text', $current);
        echo $remove;
        exit();
    }

    function loadCart(){

        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();
        $session_ordercode = $session->get('ordercode');

        ## Total for this order:
        $total = (new getAmount)->_getAmount($session->get('ordercode'));
        $fees = (new getAmount)->_getFees($session->get('ordercode'));
        $ordertotal = $total-$fees;

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $query = 'SELECT *
				  FROM #__ticketstation_orders
				  WHERE ordercode = '.$session_ordercode.'
				  GROUP BY eventid';

        $db->setQuery($query);
        $tickets = $db->loadObjectList();

        $query = 'SELECT *
				  FROM #__ticketstation_orders
				  WHERE ordercode = '.$session_ordercode.'';

        $db->setQuery($query);
        $orders = $db->loadObjectList();

        ## Making the query for showing all the clients in list function
        $query = 'SELECT priceformat, valuta FROM #__ticketstation_config WHERE configid = 1';

        $db->setQuery($query);
        $config = $db->loadObject();

        if(count($orders) == 0) {

            echo '<em>'. Text::_( 'COM_TICKETSTATION_EMPTY_CART' ) .'</em>';

        }else{

            echo '<div style="float:left; width:60%;">';
            if (count($orders) == 1) {
                echo count($orders) .' stoel';
            } else {
                echo count($orders) .' stoelen';
            }
            echo '</div>';
            echo '<div style="float:right; width:39%; text-align:right;">';
            echo (new TicketstationFunctions)->showprice($config->priceformat, $ordertotal, $config->valuta);
            echo '</div>';

        }

        echo '<div style="width:100%; clear:both; margin:15px 0px 5px 0px; padding-top:10px; font-size:90%;">';
        echo '<em>';
        if( count($tickets) > 1 ){
            echo Text::_( 'COM_TICKETSTATION_MORE_THAN_ONE_EVENT' );
        }
        echo '</em>';
        echo '</div>';

    }

    /**
     * Reserves the clicked seat: adds an order row for it to the session's order and marks
     * the seat as booked.
     *
     * Which ticket is sold, and which Total Tickets counter it draws on, follows from the
     * seat plan set-up (see the Seated tickets topic on the Documentation page):
     * - Multi Seat = Yes, no child tickets: the seat's own ticket; its counter.
     * - Multi Seat = Yes, with child tickets (price per person): the most expensive published
     *   child, which the customer can change afterwards (updateSeat()); the counter of the
     *   seat's own (parent) ticket, shared by all price categories.
     * - Multi Seat = No (price per section): the child ticket the seat belongs to; its counter.
     * In every case the counter is that of the seat's own ticket (seatplancoords.ticketid).
     */
    function makeReservation(){

        $jinput = Factory::getApplication()->getInput();

        ## Getting the values from the POST.
        $id = (int) $jinput->get('id', '0', 'INT');
        $ordercode = $jinput->get('ordercode', '0', 'CMD');

        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();

        if( $ordercode != $session->get('ordercode') ){

            $msg = Text::_( 'COM_TICKETSTATION_NO_DATABASE_SESSION' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => 0);
            echo json_encode($arr);
            exit();
        }

        ## Get database information
        $db     = Factory::getContainer()->get('DatabaseDriver');

        $item = SeatplanSettings::forSeat($id);

        if (!$item) {
            $arr = array('error' => '1', 'msg' => Text::_( 'COM_TICKETSTATION_ORDER_FAILED' ), 'id' => $id, 'multiseat' => '1');
            echo json_encode($arr);
            exit();
        }

        $multiseat = $item->multi_seat == 1 ? '1' : '0';

        if($item->booked == 1){

            $msg = Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN');
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat);
            echo json_encode($arr);
            exit();
        }

        $counterid = (int) $item->ticketid;
        $ticketid  = $counterid;

        if ($item->multi_seat == 1) {

            ## Price per person: start at the most expensive published child ticket, if any.
            $query = $db->getQuery(true)
                ->select('ticketid')
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('parent') . ' = ' . $counterid)
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ticketprice') . ' DESC');

            $db->setQuery($query, 0, 1);
            $ticketid = (int) $db->loadResult() ?: $counterid;
        }

        $sql = 'SELECT eventid, ticketprice, vat_percentage
				FROM #__ticketstation_tickets
				WHERE ticketid = '.$ticketid;

        $db->setQuery($sql);
        $ticket = $db->loadObject();

        $sql = 'SELECT totaltickets
				FROM #__ticketstation_tickets
				WHERE ticketid = '.$counterid;

        $db->setQuery($sql);
        $totaltickets = (int) $db->loadResult();

        if (!$ticket || $totaltickets <= 0) {

            ## Not enough tickets available.
            $msg = Text::_( $item->multi_seat == 1 ? 'COM_TICKETSTATION_NO_SEATS_AVAILABLE' : 'COM_TICKETSTATION_SOLD_OUT' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat);
            echo json_encode($arr);
            exit();
        }

        ## Claim the seat in one statement before anything else, so two visitors clicking the
        ## same seat at the same moment can never both get it.
        $query = 'UPDATE #__ticketstation_seatplancoords SET booked = 1 WHERE id = '.$id.' AND booked = 0';
        $db->setQuery($query);

        if (!$db->execute() || $db->getAffectedRows() === 0) {

            $msg = Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN');
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat);
            echo json_encode($arr);
            exit();
        }

        ## Ticket prices are entered VAT-inclusive; split the VAT off for the order row.
        $pricing = (new Amount)->calculateVatFromPrice($ticket->ticketprice, $ticket->vat_percentage);

        ## Lets prepare some variables.
        $post['requires_seat']       = '1';
        $post['orderdate']           = date('Y-m-d H:i:s');
        $post['ordercode']           = $session->get('ordercode');
        $post['ipaddress']           = $_SERVER['REMOTE_ADDR'];
        $post['ticketid']            = $ticketid;
        $post['price']               = $ticket->ticketprice;
        $post['fees']                = $this->getSeatFee($ticket->ticketprice);
        $post['vat']                 = $pricing['vat_amount'];
        $post['price_excluding_vat'] = $pricing['price_excluding_vat'];
        $post['vat_percentage']      = $pricing['vat_percentage'];
        $post['eventid']             = $ticket->eventid;
        $post['seat_sector']         = $id;

        $model = new OrderModel();

        if (!$model->store($post)) {

            ## Release the claimed seat again.
            $db->setQuery('UPDATE #__ticketstation_seatplancoords SET booked = 0, orderid = 0 WHERE id = '.$id);
            $db->execute();

            $msg = Text::_( 'COM_TICKETSTATION_ORDER_FAILED' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat);
            echo json_encode($arr);
            exit();
        }

        ### WE NEED TO UPDATE THE SEAT NUMBER NOW ### --> ORDERID NEEDS TO BE ENTERED IN SEAT!
        $model->updateCoords($model->getOrderid(), $id);

        ## Update the tickets-totals that where removed.
        $query = 'UPDATE #__ticketstation_tickets'
            . ' SET totaltickets = totaltickets-1'
            . ' WHERE ticketid = '.$counterid;

        $db->setQuery( $query );

        if (!$db->execute()) {

            $msg = Text::_('COM_TICKETSTATION_DB_QUERY_FAILED').' (Error: #101)';
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat);
            echo json_encode($arr);
            exit();
        }

        $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );
        $arr = array('error' => '0', 'msg' => $msg, 'id' => $id, 'multiseat' => $multiseat, 'seatid' => $item->row_name.$item->seatid);
        echo json_encode($arr);
        exit();
    }

    /**
     * The per-ticket transaction costs for a seat of the given price: a percentage when
     * transaction costs are variable, otherwise nothing (fixed costs are added per order).
     */
    private function getSeatFee($ticketprice)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT variable_transcosts, transcosts FROM #__ticketstation_config WHERE configid = 1');
        $config = $db->loadObject();

        return ($config && $config->variable_transcosts == 1) ? (($ticketprice / 100) * $config->transcosts) : 0;
    }

    function saveseat(){

        $jinput = Factory::getApplication()->getInput();

        ## Getting the id
        $id     = $jinput->get('id', '0', 'int');
        ## Get database information
        $db     = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT c.*, t.multi_seat, t.type, t.background_color, t.border_color
				FROM #__ticketstation_seatplancoords AS c, #__ticketstation_seatplansettings AS t
				WHERE c.ticketid = t.ticketid
				AND c.id = '.(int)$id.'';

        $db->setQuery($sql);
        $item = $db->loadObject();

        ## Check if the ticket is a multiseat ticket.
        if ($item->multi_seat == 0){

            ## Is it a chair or a seactor?
            if($item->type == 2){

                ## It is a sector --> throw message.
                $msg = Text::_( 'COM_TICKETSTATION_YOU_DONT_HAVE_TO_SELECT_A_SEAT' );
                $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id, 'border' => $item->border_color, 'background' => $item->background_color );
                echo json_encode($arr);
                exit();

            }

            ## Get all orders for checking amount.
            $sql = 'SELECT *, count(orderid) AS total FROM #__ticketstation_orders 
					WHERE ordercode = '.(int)$this->ordercode.'
					AND seat_sector = 0
					AND ticketid = '.$item->ticketid.'
					LIMIT 0,1';

            $db->setQuery($sql);
            $order = $db->loadObject();

            if($order->total > 0){

                ### OK -- ORDER THE TICKET -- PERFORM SOME EXTRA CHECKS ###

                if ($item->booked == 1) {

                    $msg = Text::_( 'COM_TICKETSTATION_THIS_SEAT_IS_TAKEN' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
                    echo json_encode($arr);
                    exit();

                }

                ## OK NOW WE KNOW WHAT ORDER NEEDS TO BE UPDATED IN THE COORDS TABLE
                ## AND WE NEED TO UPDATE THE ORDER TABLE

                $query = 'UPDATE #__ticketstation_orders SET seat_sector = '.(int)$id.' WHERE orderid = '.(int)$order->orderid.' ';

                ## Do the query now
                $db->setQuery( $query );

                ## When query goes wrong.. Show message with error.
                if (!$db->execute()) {
                    $msg = Text::_( 'COM_TICKETSTATION_ERROR_UPDATE_QUERY_1' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                    echo json_encode($arr);
                    exit();
                }

                ### NOW UPADTE THE COORDS TABLE
                $query = 'UPDATE #__ticketstation_seatplancoords 
						  SET orderid = '.(int) $order->orderid.', booked = 1 
						  WHERE id = '.$id.' ';

                ## Do the query now
                $db->setQuery( $query );

                ## When query goes wrong.. Show message with error.
                if (!$db->execute()) {
                    $msg = Text::_( 'COM_TICKETSTATION_ERROR_UPDATE_QUERY_2' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                    echo json_encode($arr);
                    exit();
                }


                $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );

                $arr = array('error' => '0',
                    'msg' => $msg,
                    'id' => $item->seatid,
                    'seat' => $item->id,
                    'border' => $item->border_color,
                    'background' => $item->background_color);

                echo json_encode($arr);
                exit();

            }else{

                if ($item->total == 0) {

                    ## Get all orders for checking amount.
                    $sql = 'SELECT *, count(orderid) AS total 
								FROM #__ticketstation_orders 
								WHERE ordercode = '.(int)$this->ordercode.'
						        AND ticketid = '.(int)$item->ticketid.'';

                    $db->setQuery($sql);
                    $ordereditems = $db->loadObject();

                    if( $ordereditems->total == 0) {

                        ## Customer has not ordered a seat in this section. (Stop ordering this seat)
                        $msg = Text::_( 'COM_TICKETSTATION_NO_ORDER_FOR_THIS_SEAT' );
                        $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                        echo json_encode($arr);
                        exit();

                    }else{

                        ## Customer has not ordered a seat in this section. (Stop ordering this seat)
                        $msg = Text::_( 'COM_TICKETSTATION_NOT_ORDERED_FOR_ROW' );
                        $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                        echo json_encode($arr);
                        exit();
                    }
                }
            }

        }else{

            #### MULTI SEAT PART -- LESS CHECKS #####

            ## Is it a chair or a seactor?
            if($item->type == 2){

                ## It is a sector --> throw message.
                $msg = Text::_( 'COM_TICKETSTATION_YOU_DONT_HAVE_TO_SELECT_A_SEAT' );
                $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                echo json_encode($arr);
                exit();

            }

            ## Check if seat is bookoed? Get lost and warn the user directly.
            if ($item->booked == 1) {

                $msg = Text::_( 'COM_TICKETSTATION_THIS_SEAT_IS_TAKEN' );
                $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
                echo json_encode($arr);
                exit();

            }

            ## Double check if the clicked seat is a ordered seat:

            ## Lets check if the clicked item has a prent or not.
            $sql = 'SELECT count(ticketid) AS total
					FROM #__ticketstation_tickets
					WHERE parent = '.$item->ticketid.'';

            $db->setQuery($sql);
            $parent = $db->loadObject();

            if($parent->total == 0){

                ## Yes it is a parent so this query should get the total:
                $sql = 'SELECT count(orderid) AS total 
						FROM #__ticketstation_orders 
						WHERE ordercode = '.(int)$this->ordercode.'
						AND seat_sector != 0
						AND ticketid = '.$item->ticketid.'
						LIMIT 0,1';

                $db->setQuery($sql);
                $ordered = $db->loadObject();

                $sql = 'SELECT COUNT(orderid) AS total 
						FROM #__ticketstation_orders
						WHERE ordercode = '.(int)$this->ordercode.'
						AND ticketid = '.$item->ticketid.'';

                $db->setQuery($sql);
                $to_order = $db->loadObject();

            }else{

                ## No it is not a parent so this query should get the total:
                $query = 'SELECT count(o.orderid) AS total 
						  FROM #__ticketstation_orders AS o, #__ticketstation_tickets AS t 
						  WHERE o.ordercode = '.(int)$this->ordercode.'
						  AND o.seat_sector != 0
						  AND t.parent = '.$item->ticketid.'
						  AND o.ticketid = t.ticketid
						  LIMIT 0,1';

                $db->setQuery($query);
                $ordered = $db->loadObject();

                $sql = 'SELECT COUNT(a.orderid) AS total 
						FROM #__ticketstation_orders AS a, #__ticketstation_tickets AS t
						WHERE a.ordercode = '.(int)$this->ordercode.'
						AND a.ticketid = t.ticketid
						AND t.parent = '.$item->ticketid.'';

                $db->setQuery($sql);
                $to_order = $db->loadObject();

            }

            ## OKAY, we do now know that the $ordered->total has got the amount of tickets for this order.
            ## We also know how many items may be orderd: $n
            if ($ordered->total >= $to_order->total){

                $msg = Text::_( 'COM_TICKETSTATION_YOU_HAVE_CHOSEN_ALL_SEATS_FOR_THIS_ORDER' );
                $arr = array('error' => '1', 'msg' => $msg , 'id' => $id);
                echo json_encode($arr);
                exit();

            }


            ## Get all orders for checking amount.
            $sql = 'SELECT *, count(orderid) AS total 
					FROM #__ticketstation_orders 
					WHERE ordercode = '.(int)$this->ordercode.'
					AND seat_sector = 0
					LIMIT 0,1';

            $db->setQuery($sql);
            $order = $db->loadObject();

            if($order->total > 0){

                if ($item->booked == 1) {

                    $msg = Text::_( 'COM_TICKETSTATION_THIS_SEAT_IS_TAKEN' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
                    echo json_encode($arr);
                    exit();

                }

                ## OK NOW WE KNOW WHAT ORDER NEEDS TO BE UPDATED IN THE COORDS TABLE
                ## AND WE NEED TO UPDATE THE ORDER TABLE

                $query = 'UPDATE #__ticketstation_orders SET seat_sector = '.(int)$id.' WHERE orderid = '.(int)$order->orderid.' ';

                ## Do the query now
                $db->setQuery( $query );

                ## When query goes wrong.. Show message with error.
                if (!$db->execute()) {
                    $msg = Text::_( 'COM_TICKETSTATION_ERROR_UPDATE_QUERY_1' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                    echo json_encode($arr);
                    exit();
                }

                ### NOW UPADTE THE COORDS TABLE
                $query = 'UPDATE #__ticketstation_seatplancoords 
						  SET orderid = '.(int) $order->orderid.', booked = 1 
						  WHERE id = '.$id.' ';

                ## Do the query now
                $db->setQuery( $query );

                ## When query goes wrong.. Show message with error.
                if (!$db->execute()) {
                    $msg = Text::_( 'COM_TICKETSTATION_ERROR_UPDATE_QUERY_2' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $this->id);
                    echo json_encode($arr);
                    exit();
                }


                $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );

                $arr = array('error' => '0',
                    'msg' => $msg,
                    'id' => $item->seatid,
                    'background' => $item->background_color);

                echo json_encode($arr);
                exit();


            }else{

                ## No more seats to choose :) (Every order has a seat.
                $msg = Text::_( 'COM_TICKETSTATION_REACHED_MAXIMUM_TICKETS_FOR_ORDER' );
                $error = '1';
                $arr = array('error' => $error, 'msg' => $msg, 'id' => (int)$id);
                echo json_encode($arr);
                exit();

            }

        }

    }

}