<?php
/**
 * @package     ${NAMESPACE}
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */


namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use stdClass;

defined('_JEXEC') or die;


/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


## no direct access
defined('_JEXEC') or die('Restricted access');

class WaitingList
{
    private $remove;
    private $message;
    private $removesTickets;

    public function processList($cid=array(), $date=null){

        $this->remove = 0;
        $this->message = null;

        ## Database driver
        $db = Factory::getContainer()->get('DatabaseDriver');

        if( count($cid)== 0 ){

            $this->removesTickets = false;

            ## Fetch the actual candidate stale/pending order rows (not just a count),
            ## oldest first, so we know exactly which ones to hand over to a waiting
            ## list customer versus leave for Ticketcleaner to release back to stock.
            $query = $db->getQuery(true);

            $query->select(array('orderid', 'ticketid'));
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('orderdate') . ' < '.$db->quote($date));
            $query->where($db->quoteName('paid') . ' = '.$db->quote(0));
            $query->where($db->quoteName('published') . ' = '.$db->quote(0));
            $query->order($db->quoteName('ticketid') . ' ASC, ' . $db->quoteName('orderdate') . ' ASC');

            $db->setQuery($query);
            $rows = $db->loadObjectList();

            $staleOrderIdsByTicket = array();

            foreach ($rows as $row) {
                $staleOrderIdsByTicket[$row->ticketid][] = $row->orderid;
            }

            if (count($staleOrderIdsByTicket) == 0) {
                return false;
            }

            foreach ($staleOrderIdsByTicket as $ticketid => $orderIds) {

                $total = count($orderIds);

                ## The ordering is based on date added (First in goes first out if enough tickets).

                $sql = 'SELECT COUNT( id ) AS total, ordercode
						FROM #__ticketstation_waitinglist
						WHERE confirmed = 1
						AND processed = 0
						AND ticketid = '.(int)$ticketid.'
						GROUP BY ordercode, ticketid
						HAVING COUNT( id ) <= '.(int)$total.'
						ORDER BY date_added, total DESC';

                $db->setQuery($sql);
                $waiting_items = $db->loadObjectList();

                ## Loop through the waiting list items:
                for ($i2 = 0, $n2 = count($waiting_items); $i2 < $n2; $i2++ ){

                    $waitinglist = $waiting_items[$i2];

                    ## If the removable total is smaller or even to waiting list totals:
                    if($waitinglist->total <= $total){

                        ## Claim the oldest stale reservations for this ticket to hand over.
                        $claimedOrderIds = array_splice($orderIds, 0, $waitinglist->total);

                        ## Remaining total to remove:
                        $total = $total-$waitinglist->total;
                        ## Total processed waiting items:
                        $this->remove = $this->remove+$waitinglist->total;

                        $this->processWaitingListItem(array($waitinglist->ordercode), $claimedOrderIds);

                    }

                } // end for loop 2.

            } // end foreach ticket.

            return true;
        }

        ## Explicit ordercodes were removed (e.g. Box Office refund/blacklist): free up
        ## exactly those ticket slots for the waiting list.
        $this->removesTickets = true;

        $cids = implode( ',', $cid );

        $query = $db->getQuery(true);

        $query->select(array('COUNT(ticketid) as totals', 'ticketid'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' IN ('.$cids.')');
        $query->group('ticketid');

        $db->setQuery($query);
        $items= $db->loadObjectList();

        if(count($items) == 0){
            return false;
        }

        for ($i = 0, $n = count($items); $i < $n; $i++ ){

            $row 		= $items[$i];
            $ticketid 	= $row->ticketid;
            $total 		= $row->totals;

            $sql = 'SELECT COUNT( id ) AS total, ordercode
					FROM #__ticketstation_waitinglist
					WHERE confirmed = 1
					AND processed = 0
					AND ticketid = '.$ticketid.'
					GROUP BY ordercode, ticketid
					HAVING COUNT( id ) <= '.$total.'
					ORDER BY date_added, total DESC';

            $db->setQuery($sql);
            $waiting_items = $db->loadObjectList();

            ## Loop through the waiting list items:
            for ($i2 = 0, $n2 = count($waiting_items); $i2 < $n2; $i2++ ){

                $waitinglist = $waiting_items[$i2];

                ## If the removable total is smaller or even to waiting list totals:
                if($waitinglist->total <= $total){

                    ## Remaining total to remove:
                    $total = $total-$waitinglist->total;
                    ## Total processed waiting items:
                    $this->remove = $this->remove+$waitinglist->total;

                    $this->processWaitingListItem(array($waitinglist->ordercode));

                }

            } // end for loop 2.

        } //end for loop 1.

        return true;

    }

    public function getRemovedTickets(){
        return $this->remove;
    }

    private function processWaitingListItem($cid = array(), $staleOrderIdsToDelete = array()) {

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select(array('w.*', 't.parent AS parentticket'));
            $query->from($db->quoteName('#__ticketstation_waitinglist', 'w'));
            $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('w.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
            $query->where($db->quoteName('w.ordercode') . ' IN ('.$cids.')');

            $db->setQuery( $query );

            ## Getting the ticket id's
            $data = $db->loadObjectList();

            ## Loop the ticketnumbers for deletion
            for ($i = 0, $n = count($data); $i < $n; $i++ ){

                $row  = $data[$i];

                $process 				= new stdClass();
                $process->userid 		= $row->userid;
                $process->ordercode 	= $row->ordercode;
                $process->eventid		= $row->eventid;
                $process->ticketid		= $row->ticketid;
                $process->paid			= 3;
                ## Use "now", not the original waiting-list signup date: Ticketcleaner expires
                ## pending (paid=3) orders after `removal_days`, so backdating this would let it
                ## get swept up before the customer even sees the payment link.
                $process->orderdate		= date('Y-m-d H:i:s');
                $process->published		= 1;
                $process->ipaddress		= $row->ip_address;
                $process->requires_seat = $row->requires_seat;
                ## Generate a cryptographically random validation token for secure guest links
                $process->validation_token = bin2hex(random_bytes(32));

                ## Insert the object into the order table
                $result = $db->insertObject('#__ticketstation_orders', $process);

                ## If this is ticket removal
                if($this->removesTickets === true){

                    ## update the ticket totals:
                    $query = $db->getQuery(true);

                    $fields = array(
                        $db->quoteName('totaltickets') . ' = totaltickets-1'
                    );

                    $conditions = array(
                        $db->quoteName('ticketid') . ' = '.$row->ticketid
                    );

                    $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

                    $db->setQuery($query);

                    $result = $db->execute();

                    if (!$result) {
                        return false;
                    }

                    ## if it is a parent update that one too:
                    if ($row->parentticket != 0){

                        $query = $db->getQuery(true);

                        $fields = array(
                            $db->quoteName('totaltickets') . ' = totaltickets-1'
                        );

                        $conditions = array(
                            $db->quoteName('ticketid') . ' = '.$row->parentticket
                        );

                        $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

                        $db->setQuery($query);

                        $result = $db->execute();

                        if (!$result) {
                            return false;
                        }

                    }

                }

            }

            $query = $db->getQuery(true);

            $fields = array(
                $db->quoteName('processed') . ' = 1'
            );

            $conditions = array(
                $db->quoteName('ordercode') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_waitinglist'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

            ## For a date-based (Ticketcleaner) promotion, delete the specific stale order(s)
            ## we're handing over to this waiting-list customer WITHOUT restoring their ticket
            ## totals: ownership just transfers, net stock is unchanged. Ticketcleaner's own
            ## cleanup pass restores totals for whatever stale orders remain unclaimed.
            if (!empty($staleOrderIdsToDelete)) {

                ArrayHelper::toInteger($staleOrderIdsToDelete);
                $staleIds = implode(',', $staleOrderIdsToDelete);

                ## A stale order may have been a seated reservation (seat plans share the same
                ## orders table and ticket totals as counter tickets). Release its seat before
                ## the row disappears, otherwise the seat stays locked forever - the promoted
                ## waiting-list customer does not inherit it, so nobody would ever be able to
                ## select it again.
                $ticket_helper = new Tickets();
                foreach ($staleOrderIdsToDelete as $staleOrderId) {
                    $ticket_helper->resetSeatSate($staleOrderId);
                }

                $query = $db->getQuery(true);
                $query->delete($db->quoteName('#__ticketstation_orders'));
                $query->where($db->quoteName('orderid') . ' IN ('.$staleIds.')');

                $db->setQuery($query);

                if (!$db->execute()) {
                    return false;
                }
            }

            ## Send people a payment request.
            $this->sendPayment($cids);

        }

        return true;

    }

    private function sendPayment($cids=array()){

        ## Check if the class exists:
        if (!class_exists('paymentAPI')) {
            exit("Class is not available to process transactions.");
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' IN ('.$cids.')');
        $query->group('ordercode');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++ ){

            $row = $data[$i];

            $payment_helper = new paymentAPI( (int)$row->ordercode );
            $config         = $payment_helper->getConfig();

            $query = $db->getQuery(true);

            $query->select(array('COUNT(orderid) AS total', 'userid'));
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('paid') . ' != '. $db->quote(1));
            $query->where($db->quoteName('ordercode') . ' = '. $db->quote((int)$row->ordercode));

            $db->setQuery($query);
            $item = $db->loadObject();

            if( $item->total > 0 ){

                ## Getting the order amount.
                $total = (new getAmount)->_getAmount($row->ordercode, 1);
                $price = TicketstationFunctions::showprice($config->priceformat, $total, $config->valuta);

                $message = new eTicketsMessage;

                $variables = array(
                    'orderlist'   => $payment_helper->getOrderList(),
                    'ordercode'   => $row->ordercode,
                    'price'       => $price,
                    'paymentlink' => $payment_helper->generatePaymentLink($row->ordercode),
                );

                $message->id(3)
                    ->user($item->userid)
                    ->variables($variables)
                    ->send();

                History::log($row->ordercode, 'waitinglist_promoted', 'Waiting list customer promoted, payment link sent');
            }

        }

        return true;
    }

    public function confirm($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $fields     = [$db->quoteName('confirmed') . ' = 1'];
        $conditions = [$db->quoteName('ordercode') . ' = ' . (int) $ordercode];
        $query->update($db->quoteName('#__ticketstation_waitinglist'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    /**
     * Confirm a waiting list entry by validation token (new secure method).
     * Prevents guessing of waiting list entries.
     *
     * @param string $token The validation token
     * @return bool
     * @since 1.0.0
     */
    public function confirmByToken($token)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $fields     = [$db->quoteName('confirmed') . ' = 1'];
        $conditions = [$db->quoteName('validation_token') . ' = ' . $db->quote($token)];
        $query->update($db->quoteName('#__ticketstation_waitinglist'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    /**
     * Getting the orders on a waiting list by ordercode.
     *
     * @param $ordercode
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrdersOnWaitingList($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 't.ticketname', 't.ticketprice', 't.startdate', 'e.eventname'])
            ->from($db->quoteName('#__ticketstation_waitinglist', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('a.ordercode') . " = " . $ordercode)
            ->where($db->quoteName('a.processed') . " = 0");

        $db->setQuery($query);

        return $db->loadObjectList();
    }

}