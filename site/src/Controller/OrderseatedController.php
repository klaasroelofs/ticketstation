<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Finder\Administrator\Indexer\Parser\Html;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
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
        ## Loading the application Joomla
        $app 	= Factory::getApplication();
        ## Setting error to nothing!
        $error 	= '';

        ## Check if there are any orders, otherwise stop the script.
        $query = 'SELECT orderid, ticketid FROM #__ticketstation_orders 
				  WHERE ordercode = '.(int)$this->ordercode.'
				  AND seat_sector = "'.(int)$id.'"';

        $db->setQuery($query);
        $item = $db->loadObject();

        $sql = 'SELECT * 
				FROM #__ticketstation_seatplansettings 
				WHERE ticketid = '.(int)$item->ticketid.'';

        $db->setQuery($sql);
        $coords = $db->loadObject();


        ## Check if order is valid
        if($item->orderid == 0){

            $error = '1';
            $msg = Text::_( 'COM_TICKETSTATION_THIS_IS_NOT_YOUR_ORDER' );

            $arr = array('error' => $error, 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();

        }else{

            $query = 'DELETE FROM #__ticketstation_orders WHERE orderid = '.(int)$item->orderid.'';

            ## Do the query now
            $db->setQuery( $query );

            ## When query goes wrong.. Show message with error.
            if (!$db->execute()) {
                //$this->setError($db->getErrorMsg());

                $error = '1';
                $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_ORDER_TABLE' );

                $arr = array('error' => $error, 'msg' => $msg, 'id' => $id);
                echo json_encode($arr);
                exit();
            }

            ## Update the tickets-totals that where removed.
            $query = 'UPDATE #__ticketstation_tickets SET totaltickets = totaltickets+1
					  WHERE ticketid = '.$item->ticketid.'';

            ## Do the query now
            $db->setQuery( $query );

            ## When query goes wrong.. Show message with error.
            if (!$db->execute()) {
                //$this->setError($db->getErrorMsg());

                $error = '1';
                $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_ORDER_TABLE' );

                $arr = array('error' => $error, 'msg' => $msg, 'id' => $id);
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
                //$this->setError($db->getErrorMsg());

                $error = '1';
                $msg = Text::_( 'COM_TICKETSTATION_COULD_NOT_UPDATE_COORDS_TABLE' );

                $arr = array('error' => $error, 'msg' => $msg, 'id' => $id);
                echo json_encode($arr);
                exit();

            }

            $error = '0';
            $msg = Text::_( 'COM_TICKETSTATION_THIS_SEAT_IS_REMOVED' );

            $arr = array('error' => $error, 'msg' => $msg, 'id' => $id, 'background' => $coords->background_color, 'color' => $coords->border_color );
            echo json_encode($arr);
            exit();

        }

    }

    function updateSeat(){

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $jinput = Factory::getApplication()->getInput();
        ## Getting the values from the POST.
        $ticketid  = $jinput->get('ticketid', '0', 'INT');
        $ordercode = $jinput->get('ordercode', '0', 'CMD');
        $orderid   = $jinput->get('orderid', '0', 'INT');

        ## Update the tickets-totals that where removed.
        $query = 'UPDATE #__ticketstation_orders'
            . ' SET ticketid = '.(int)$ticketid.''
            . ' WHERE orderid = '.(int) $orderid.' ';

        ## Do the query now
        $db->setQuery( $query );

        ## When query goes wrong.. Show message with error.
        if (!$db->execute()) {

            $msg = Text::_('COM_TICKETSTATION_DB_QUERY_FAILED').' (Error: #101)';
            echo $msg;
            exit();

        }

        $msg = Text::_('COM_TICKETSTATION_CHANGED_TICKET');
        echo $msg;
        exit();

    }

    function loadSeat(){

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $jinput = Factory::getApplication()->getInput();
        ## Getting the values from the POST.
        $id = $jinput->get('id', '0', 'INT');
        $ordercode = $jinput->get('ordercode', '0', 'CMD');

        $sql = 'SELECT c.*, t.multi_seat, t.type, t.background_color, t.border_color
				FROM #__ticketstation_seatplancoords AS c, #__ticketstation_seatplansettings AS t
				WHERE c.ticketid = t.ticketid
				AND c.id = '.(int)$id.'';

        $db->setQuery($sql);
        $item = $db->loadObject();

        if ($item->multi_seat == 1){

            $sql = 'SELECT COUNT( ticketid ) AS total, totaltickets
		    		FROM #__ticketstation_tickets
		   		    WHERE parent = '.(int)$item->ticketid.'';

            $db->setQuery($sql);
            $obj = $db->loadObject();

            if($obj->total == 0) {

                ## ONLY A REMOVE BUTTON IS NEEDED -- NO EXTRA OPTIONS TO CHOOSE
                echo '<button id="'.$id.'" class="btn btn-block btn-danger remove">'.Text::_( 'COM_TICKETSTATION_REMOVE_SEAT' ).' '.$item->row_name.$item->seatid.' '.Text::_( 'COM_TICKETSTATION_REMOVE_FROM' ).'</button>';


            }else{

                ## A DROPDOWN IS NEEDED TO CHOOSE PRICE/TICKET
                $sql = 'SELECT ticketid
			    		FROM #__ticketstation_orders
			   		    WHERE orderid = '.(int)$item->orderid.'';

                $db->setQuery($sql);
                $t = $db->loadObject();


                $query = 'SELECT ticketid, ticketname, ticketprice
						  FROM #__ticketstation_tickets
						  WHERE published = 1 
						  AND parent = '.(int)$item->ticketid.'';

                $db->setQuery($query);
                $items = $db->loadObjectList() ;

                $query = 'SELECT priceformat, valuta 
						  FROM #__ticketstation_config 
						  WHERE configid = 1';

                $db->setQuery($query);
                $config = $db->loadObject();

                for ($i2 = 0, $n2 = count($items); $i2 < $n2; $i2++ ){

                    $row        = $items[$i2];
                    $price      = (new TicketstationFunctions)->showprice($config->priceformat, $row->ticketprice, $config->valuta);
                    $options[]  = HTML::_('select.option', $row->ticketid, $row->ticketname .' - '.$price);

                }
                $lists['ticketid'] = HTML::_('select.genericlist', $options, ''.$item->orderid.'', 'class="input ticketid" style="width:100%;"', 'value', 'text', $t->ticketid);

                echo $lists['ticketid'];

                echo '<button id="'.$id.'" class="btn btn-block btn-danger remove">'.Text::_( 'COM_TICKETSTATION_REMOVE_SEAT' ).''.$item->row_name.$item->seatid.' '.Text::_( 'COM_TICKETSTATION_REMOVE_FROM' ).'</button>';


            }

        }else{
            ## THIS IS A NORMAL TICKET WITH ROW PRICING
            echo '<button id="'.$id.'" class="btn btn-block btn-danger remove">'.Text::_( 'COM_TICKETSTATION_REMOVE_SEAT' ).' '.$item->seatid.' '.Text::_( 'COM_TICKETSTATION_REMOVE_FROM' ).'</button>';

        }

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

    function makeReservation(){

        $jinput = Factory::getApplication()->getInput();
        ## Getting the values from the POST.
        $id = $jinput->get('id', '0', 'INT');
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

        ## Get the availabillity:
        $sql = 'SELECT c.*, t.multi_seat, t.type, t.background_color, t.border_color
				FROM #__ticketstation_seatplancoords AS c, #__ticketstation_seatplansettings AS t
				WHERE c.ticketid = t.ticketid
				AND c.id = '.(int)$id.'';

        $db->setQuery($sql);
        $item = $db->loadObject();

        if($item->booked == 1){

            $msg = Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN');

            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => '1');

            echo json_encode($arr);
            exit();

        }


        if($item->multi_seat == 1){

            $sql = 'SELECT COUNT( ticketid ) AS total, totaltickets
				    FROM #__ticketstation_tickets
				    WHERE parent = '.(int)$item->ticketid.'';

            $db->setQuery($sql);
            $obj = $db->loadObject();

            if($obj->total == 0) {

                $sql = 'SELECT totaltickets, eventid, ticketprice, vat_percentage
				        FROM #__ticketstation_tickets
				        WHERE ticketid = '.(int)$item->ticketid.'';

                $db->setQuery($sql);
                $obj = $db->loadObject();

                $ticketcounter = $item->ticketid;
                $obj->total   = 0;
                $totaltickets = $obj->totaltickets;
                $eventid      = $obj->eventid;
                $ticketprice  = $obj->ticketprice;
                $vat_percentage = $obj->vat_percentage;

            }else{

                $totaltickets = $obj->totaltickets;

            }

            if($totaltickets > 0){

                if($obj->total > 1){

                    ## OK, by default we select the most expensive ticket.
                    $sql = 'SELECT ticketid, eventid, ticketprice, vat_percentage
							FROM #__ticketstation_tickets
				    		WHERE parent = '.(int)$item->ticketid.'
				    		ORDER BY ticketprice DESC';

                    $db->setQuery($sql);
                    $ticket = $db->loadObject();

                    $ticketid     = $ticket->ticketid;
                    $eventid      = $ticket->eventid;
                    // $ticketprice/vat_percentage were previously never (re)assigned here, so a
                    // ticket with more than one price tier fell through using a leftover/undefined
                    // $ticketprice and no VAT at all.
                    $ticketprice    = $ticket->ticketprice;
                    $vat_percentage = $ticket->vat_percentage;

                    $sql = 'SELECT parent
							FROM #__ticketstation_tickets
				    		WHERE ticketid = '.(int)$ticketid.'';

                    $db->setQuery($sql);
                    $temp = $db->loadObject();
                    $ticketcounter = $temp->parent;

                } else {

                    $ticketid = $item->ticketid;

                    // This "exactly one child ticket" branch never set $ticketprice/
                    // $vat_percentage at all (they'd be left undefined) - fetch them for the
                    // ticket actually being ordered, same as the other branches above.
                    $sql = 'SELECT ticketprice, vat_percentage
								FROM #__ticketstation_tickets
					    		WHERE ticketid = '.(int)$ticketid.'';

                    $db->setQuery($sql);
                    $priceRow = $db->loadObject();

                    $ticketprice    = $priceRow->ticketprice;
                    $vat_percentage = $priceRow->vat_percentage;

                }


                ## Ticket available -> proceed with order.

                $query = 'SELECT variable_transcosts, transactioncosts, transcosts FROM #__ticketstation_config WHERE configid = 1';

                $db->setQuery($query);
                $config = $db->loadObject();

                if ($config->variable_transcosts == 1)
                {
                    $ticket_fee = (($ticketprice / 100) * $config->transcosts);
                } else {
                    $ticket_fee = '0';
                }

                // Never computed in this branch before, so every seated order created here
                // stored vat/vat_percentage/price_excluding_vat as NULL - invoices for these
                // orders always showed 0% VAT regardless of what's configured on the ticket.
                $pricing = (new Amount)->calculateVatFromPrice($ticketprice, $vat_percentage);

                ## Lets prepare some variables.
                $post['requires_seat'] = '1';
                ## Creating a proper time stamp
                $now = time();
                ## Output: $now = "1074176782";
                $orderdate            = date('Y-m-d H:i:s', $now);
                $post['orderdate']    = $orderdate;
                $post['ordercode']    = $session->get('ordercode');
                $post['ipaddress']    = $_SERVER['REMOTE_ADDR'];
                $post['ticketid']     = $ticketid;
                $post['price']     	  = $ticketprice;
                $post['fees']     	  = $ticket_fee;
                $post['vat']                 = $pricing['vat_amount'];
                $post['price_excluding_vat'] = $pricing['price_excluding_vat'];
                $post['vat_percentage']      = $pricing['vat_percentage'];
                $post['eventid']      = $eventid;
                $post['seat_sector']  = $id;

                //if ($this->userid) {
                //    $post['userid']   = $this->userid;
                //}

                $model	     = $this->getModel('order');

                $models = new OrderModel();
                ##$orderTicket = $model->store($post);

                if ($models->store($post)) {

                    $orderid     = $models->getOrderid();

                    ### WE NEED TO UPDATE THE SEAT NUMBER NOW ### --> ORDERID NEEDS TO BE ENTERED IN SEAT!
                    $models->updateCoords($orderid, $id);


                    ## Update the tickets-totals that where removed.
                    $query = 'UPDATE #__ticketstation_tickets'
                        . ' SET totaltickets = totaltickets-1'
                        . ' WHERE ticketid = '.(int) $ticketcounter.' ';

                    ## Do the query now
                    $db->setQuery( $query );

                    ## When query goes wrong.. Show message with error.
                    if (!$db->execute()) {

                        ## End of checks --> order is added:
                        $msg = Text::_('COM_TICKETSTATION_DB_QUERY_FAILED').' (Error: #101)';

                        $arr = array('error' => '0', 'msg' => $msg, 'id' => $id, 'multiseat' => '1');

                        echo json_encode($arr);
                        exit();

                    }

                    $sql = 'SELECT * FROM #__ticketstation_seatplancoords WHERE orderid = '.(int)$orderid.'';

                    $db->setQuery($sql);
                    $coords_table = $db->loadObject();

                    if($coords_table->row_name == ''){
                        $seatid = $coords_table->seatid;
                    }else{
                        $seatid = $coords_table->row_name.$coords_table->seatid;
                    }


                    //if($obj->total > 1) {
                    //    $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED_CHOOSE_PRICE' );
                    //}else{
                        $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );
                    //}

                    $arr = array('error' => '0', 'msg' => $msg, 'id' => $id, 'multiseat' => '1', 'seatid' => $seatid);
                    echo json_encode($arr);
                    exit();

                }else{

                    #### OOOOPS --> MOVE ALONG, IT WENT WRONG!

                    $msg = Text::_( 'COM_TICKETSTATION_ORDER_FAILED' );
                    $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => '1');
                    echo json_encode($arr);
                    exit();

                }

            }else{

                ## Not enough tickets available.
                $msg = Text::_( 'COM_TICKETSTATION_NO_SEATS_AVAILABLE' );
                $arr = array('error' => '1', 'msg' => $msg, 'id' => '', 'multiseat' => '1');
                echo json_encode($arr);
                exit();

            }

        }else{

            $sql = 'SELECT totaltickets, eventid, ticketprice, vat_percentage
				    FROM #__ticketstation_tickets
				    WHERE ticketid = '.(int)$item->ticketid.'';

            $db->setQuery($sql);
            $obj = $db->loadObject();

            if( $obj->totaltickets == 0 ) {

                ## These are normal tickets! (Prices are per row)
                $msg = Text::_( 'COM_TICKETSTATION_SOLD_OUT' );
                $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => '0');
                echo json_encode($arr);
                exit();

            }else{

                ## Ticket available -> proceed with order.

                ## This branch never set price/vat/fees at all - an order created here would
                ## get price=NULL, meaning the sale amount itself was never recorded, not just
                ## missing VAT. Compute it the same way every other purchase path does.
                $vatQuery = 'SELECT variable_transcosts, transactioncosts, transcosts FROM #__ticketstation_config WHERE configid = 1';
                $db->setQuery($vatQuery);
                $feeConfig = $db->loadObject();

                $ticket_fee = $feeConfig->variable_transcosts == 1 ? (($obj->ticketprice / 100) * $feeConfig->transcosts) : 0;
                $pricing    = (new Amount)->calculateVatFromPrice($obj->ticketprice, $obj->vat_percentage);

                ## Lets prepare some variables.
                $post['requires_seat'] = '1';
                ## Creating a proper time stamp
                $now = time();
                ## Output: $now = "1074176782";
                $orderdate            = date('Y-m-d H:i:s', $now);
                $post['orderdate']    = $orderdate;
                $post['ordercode']    = $session->get('ordercode');
                $post['ipaddress']    = $_SERVER['REMOTE_ADDR'];
                $post['ticketid']     = $item->ticketid;
                ##$post['named_ticket'] = $obj->named_tickets_required;
                $post['price']               = $obj->ticketprice;
                $post['fees']                = $ticket_fee;
                $post['vat']                 = $pricing['vat_amount'];
                $post['price_excluding_vat'] = $pricing['price_excluding_vat'];
                $post['vat_percentage']      = $pricing['vat_percentage'];
                $post['eventid']      = $obj->eventid;
                $post['seat_sector']  = $id;

                if ($this->userid) {
                    $post['userid']   = $this->userid;
                }

                $model	     = $this->getModel('order');
                ##$orderTicket = $model->store($post);

                if ($model->store($post)) {

                    $orderid     = $model->getOrderid();

                    ### WE NEED TO UPDATE THE SEAT NUMBER NOW ### --> ORDERID NEEDS TO BE ENTERED IN SEAT!
                    $model->updateCoords($orderid, $id);


                    ## Update the tickets-totals that where removed.
                    $query = 'UPDATE #__ticketstation_tickets'
                        . ' SET totaltickets = totaltickets-1'
                        . ' WHERE ticketid = '.(int)$item->ticketid.' ';

                    ## Do the query now
                    $db->setQuery( $query );

                    ## When query goes wrong.. Show message with error.
                    if (!$db->execute()) {

                        ## End of checks --> order is added:
                        $msg = Text::_('COM_TICKETSTATION_DB_QUERY_FAILED').' (Error: #102)';
                        $arr = array('error' => '1', 'msg' => $msg, 'id' => $id, 'multiseat' => '0');
                        echo json_encode($arr);
                        exit();

                    }

                }

                $sql = 'SELECT * FROM #__ticketstation_seatplancoords WHERE orderid = '.(int)$orderid.'';

                $db->setQuery($sql);
                $coords_table = $db->loadObject();

                if($coords_table->row_name == ''){
                    $seatid = $coords_table->seatid;
                }else{
                    $seatid = $coords_table->row_name.$coords_table->seatid;
                }

                ## These are normal tickets! (Prices are per row)
                $msg = Text::_( 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );
                $arr = array('error' => '0', 'msg' => $msg, 'id' => $id, 'multiseat' => '0', 'seatid' => $seatid);
                echo json_encode($arr);
                exit();

            }


        }


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