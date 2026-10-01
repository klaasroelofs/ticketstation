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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatOrphans;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\Model\OrderModel;
use Ticketstation\Component\Ticketstation\Site\Model\SeatedeventModel;

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

        ### NOW UPADTE THE COORDS TABLE (a blocked seat goes back to blocked)
        $query = 'UPDATE #__ticketstation_seatplancoords
				  SET orderid = 0, booked = blocked
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
     * Switches a picked free seat to another price category and re-prices its order row
     * accordingly.
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

        ## Only a price category of the seat's chart is valid, and only for a free seat, so a
        ## visitor can't put an arbitrary (cheaper) ticket on the seat.
        $ticket = null;

        if ($seat && SeatplanSettings::ticketForSeat($seat, (int) $ticketid) === (int) $ticketid && (int) $seat->parent === 0) {

            $query = $db->getQuery(true)
                ->select(['ticketid', 'ticketprice', 'vat_percentage'])
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('ticketid') . ' = ' . (int) $ticketid);

            $db->setQuery($query);
            $ticket = $db->loadObject();
        }

        if (!$ticket) {
            echo Text::_('COM_TICKETSTATION_DB_QUERY_FAILED') . ' (Error: #101)';
            exit();
        }

        ## With online payments off only a free price category can be chosen on the website.
        if (!Shop::canPay((int) $ticket->ticketid)) {
            echo Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_TICKET');
            exit();
        }

        $pricing = (new Amount)->calculateVatFromPrice($ticket->ticketprice, $ticket->vat_percentage);

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_orders'))
            ->set($db->quoteName('ticketid') . ' = ' . (int) $ticket->ticketid)
            ->set($db->quoteName('price') . ' = ' . $db->quote($ticket->ticketprice))
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
        $remove = '<button id="'.(int)$id.'" class="ts-btn ts-btn--danger ts-btn--sm ts-btn--block remove" type="button">'.Text::_( 'COM_TICKETSTATION_REMOVE_SEAT' ).' '.$label.' '.Text::_( 'COM_TICKETSTATION_REMOVE_FROM' ).'</button>';

        ## A free seat can be switched between the chart's price categories; a section seat has
        ## its fixed price.
        $items = (int) $item->parent === 0 ? SeatplanSettings::priceCategories((int) $item->ticketid) : [];

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

        echo '<label class="ts-label" for="' . (int) $item->orderid . '">' . Text::_('COM_TICKETSTATION_PRICE_CATEGORY') . ' ' . $label . ':</label>';
        echo HTMLHelper::_('select.genericlist', $options, (string) (int) $item->orderid, 'class="ts-select ticketid"', 'value', 'text', $current);
        echo $remove;
        exit();
    }

    /**
     * Checks the session's seats on one chart against "Prevent single empty seats" before the
     * customer continues (SeatOrphans); the cart checks it again.
     */
    function checkOrphans(){

        $ownerId = Factory::getApplication()->getInput()->getInt('cid', 0);
        $orphans = SeatOrphans::forChart($ownerId, (int) $this->ordercode);

        echo json_encode([
            'ok'    => !$orphans,
            'msg'   => $orphans ? SeatOrphans::message($orphans) : '',
            'seats' => array_map(fn ($seat) => (int) $seat->id, $orphans),
        ]);
        exit();
    }

    function loadCart(){

        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();
        $session_ordercode = $session->get('ordercode');

        ## Total for this order:
        $totals     = OrderTotals::get($session->get('ordercode'), true);
        $ordertotal = $totals->subtotal;

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
            echo Text::plural('COM_TICKETSTATION_N_SEATS', count($orders));
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
     * Which ticket is sold follows from the seat (see SeatplanSettings::ticketForSeat() and
     * the Seated tickets topic on the Documentation page): a section seat sells its child
     * ticket; a free seat the most expensive price category, which the customer can change
     * afterwards (updateSeat()), or the chart's own ticket when it has no categories.
     * No counter is kept: the seats themselves are the capacity, and claiming the seat below
     * is what makes it unavailable.
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
            $arr = array('error' => '1', 'msg' => Text::_( 'COM_TICKETSTATION_ORDER_FAILED' ), 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ## Only charts the seat-picking page shows this visitor: its ticket is published, and in
        ## test or bypass mode only to logged-in users.
        if (!Shop::sells((int) ($item->parent > 0 ? $item->parent : $item->ticketid))) {
            $arr = array('error' => '1', 'msg' => Text::_( 'COM_TICKETSTATION_TICKET_NOT_AVAILABLE' ), 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        if($item->booked == 1){

            $msg = Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN');
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        $ticketid = SeatplanSettings::ticketForSeat($item);

        $sql = 'SELECT eventid, ticketprice, vat_percentage
				FROM #__ticketstation_tickets
				WHERE ticketid = '.$ticketid;

        $db->setQuery($sql);
        $ticket = $db->loadObject();

        if (!$ticket) {

            $msg = Text::_( 'COM_TICKETSTATION_ORDER_FAILED' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ## With online payments off only a free seat can be taken on the website.
        if (!Shop::canPay((int) $ticketid)) {
            $arr = array('error' => '1', 'msg' => Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_TICKET'), 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ## Claim the seat in one statement before anything else, so two visitors clicking the
        ## same seat at the same moment can never both get it.
        $query = 'UPDATE #__ticketstation_seatplancoords SET booked = 1 WHERE id = '.$id.' AND booked = 0';
        $db->setQuery($query);

        if (!$db->execute() || $db->getAffectedRows() === 0) {

            $msg = Text::_('COM_TICKETSTATION_THIS_SEAT_IS_TAKEN');
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
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
        $post['vat']                 = $pricing['vat_amount'];
        $post['price_excluding_vat'] = $pricing['price_excluding_vat'];
        $post['vat_percentage']      = $pricing['vat_percentage'];
        $post['eventid']             = $ticket->eventid;
        $post['seat_sector']         = $id;

        $model = new OrderModel();

        if (!$model->store($post)) {

            ## Release the claimed seat again.
            $db->setQuery('UPDATE #__ticketstation_seatplancoords SET booked = blocked, orderid = 0 WHERE id = '.$id);
            $db->execute();

            $msg = Text::_( 'COM_TICKETSTATION_ORDER_FAILED' );
            $arr = array('error' => '1', 'msg' => $msg, 'id' => $id);
            echo json_encode($arr);
            exit();
        }

        ### WE NEED TO UPDATE THE SEAT NUMBER NOW ### --> ORDERID NEEDS TO BE ENTERED IN SEAT!
        $model->updateCoords($model->getOrderid(), $id);

        ## With price categories the page opens the category choice for this seat straight away.
        $pricechoice = (int) $item->parent === 0 && $ticketid !== (int) $item->ticketid ? '1' : '0';

        $msg = Text::_( $pricechoice === '1' ? 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED_CHOOSE_PRICE' : 'COM_TICKETSTATION_SEAT_HAS_BEEN_ORDERED' );
        $arr = array('error' => '0', 'msg' => $msg, 'id' => $id, 'pricechoice' => $pricechoice, 'seatid' => $item->row_name.$item->seatid);
        echo json_encode($arr);
        exit();
    }
}
