<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use stdClass;

defined('_JEXEC') or die;


## no direct access
defined('_JEXEC') or die('Restricted access');

class WaitingList
{
    /**
     * Hands available tickets to the waiting list, after an order was deleted, the cleanup
     * removed unfinished or expired orders, or a ticket's Capacity was raised. Confirmed
     * waiting orders for the given tickets, their parent and their sibling or child tickets
     * are promoted oldest first, each one only when all its tickets fit in what is available;
     * an order that doesn't fit is skipped for a later, smaller one. Child tickets that share
     * their parent's capacity count against it together, so a place freed on one child ticket
     * can go to a customer waiting for another. Seated tickets have no waiting list.
     *
     * @param   int[]  $ticketids  Tickets that may have tickets available again.
     *
     * @return  int  The number of tickets handed to the waiting list.
     */
    public function promote(array $ticketids): int
    {
        $ticketids = array_values(array_unique(array_filter(array_map('intval', $ticketids))));

        if (!$ticketids) {
            return 0;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        // The whole families of these tickets: their top-level tickets and all child tickets.
        $query = $db->getQuery(true)
            ->select('DISTINCT IF(' . $db->quoteName('parent') . ' > 0, ' . $db->quoteName('parent') . ', ' . $db->quoteName('ticketid') . ')')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->whereIn($db->quoteName('ticketid'), $ticketids);
        $db->setQuery($query);
        $tops = array_map('intval', $db->loadColumn());

        if (!$tops) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('t.ticketid'))
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 'top') . ' ON ' . $db->quoteName('top.ticketid')
                . ' = IF(' . $db->quoteName('t.parent') . ' > 0, ' . $db->quoteName('t.parent') . ', ' . $db->quoteName('t.ticketid') . ')')
            ->whereIn($db->quoteName('top.ticketid'), $tops)
            ->where($db->quoteName('top.show_seatplans') . ' != 1');
        $db->setQuery($query);
        $family = array_map('intval', $db->loadColumn());

        if (!$family) {
            return 0;
        }

        // Waiting orders for these tickets, oldest first.
        $query = $db->getQuery(true)
            ->select($db->quoteName('ordercode'))
            ->from($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('confirmed') . ' = 1')
            ->where($db->quoteName('processed') . ' = 0')
            ->whereIn($db->quoteName('ticketid'), $family)
            ->group($db->quoteName('ordercode'))
            ->order('MIN(' . $db->quoteName('date_added') . ') ASC');
        $db->setQuery($query);

        $promoted = 0;

        foreach ($db->loadColumn() as $ordercode) {
            // A waiting order is promoted as a whole, so every ticket in it has to fit.
            $query = $db->getQuery(true)
                ->select([$db->quoteName('ticketid'), 'COUNT(*) AS ' . $db->quoteName('total')])
                ->from($db->quoteName('#__ticketstation_waitinglist'))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
                ->where($db->quoteName('processed') . ' = 0')
                ->group($db->quoteName('ticketid'));
            $db->setQuery($query);
            $lines = $db->loadObjectList();

            if ($this->fits($lines) && $this->processWaitingListItem([$ordercode])) {
                $promoted += array_sum(array_column($lines, 'total'));
            }
        }

        return $promoted;
    }

    /**
     * Whether all tickets of a waiting order fit in what is available. Tickets that sell from
     * the same capacity count against it together.
     */
    private function fits(array $lines): bool
    {
        $needed    = [];
        $available = [];

        foreach ($lines as $line) {
            $pool = Availability::poolOwner((int) $line->ticketid);

            $needed[$pool]    = ($needed[$pool] ?? 0) + (int) $line->total;
            $available[$pool] = Availability::forTicket((int) $line->ticketid);
        }

        foreach ($needed as $pool => $amount) {
            if ($amount > $available[$pool]) {
                return false;
            }
        }

        return true;
    }

    private function processWaitingListItem($cid = array()) {

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select(array('w.*', 't.parent AS parentticket', 't.ticketprice', 't.vat_percentage AS ticket_vat'));
            $query->from($db->quoteName('#__ticketstation_waitinglist', 'w'));
            $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('w.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
            $query->where($db->quoteName('w.ordercode') . ' IN ('.$cids.')');

            $db->setQuery( $query );

            ## Getting the ticket id's
            $data = $db->loadObjectList();

            ## Loop the ticketnumbers for deletion
            for ($i = 0, $n = count($data); $i < $n; $i++ ){

                $row  = $data[$i];

                ## Price and VAT as a normal purchase records them (see OrderController::buyticket()),
                ## so the invoice of this order shows the right amounts.
                $pricing = (new Amount)->calculateVatFromPrice((float) $row->ticketprice, (float) $row->ticket_vat);

                $process 				= new stdClass();
                $process->price               = $row->ticketprice;
                $process->vat                 = $pricing['vat_amount'];
                $process->vat_percentage      = $pricing['vat_percentage'];
                $process->price_excluding_vat = $pricing['price_excluding_vat'];
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

            ## The payment link is sent now: the transaction costs are those of this moment.
            foreach (array_unique(array_column($data, 'ordercode')) as $ordercode) {
                OrderTotals::capture($ordercode);
            }

            ## Send people a payment request.
            $this->sendPayment($cids);

        }

        return true;

    }

    private function sendPayment($cids=array()){

        ## Check if the class exists:
        if (!class_exists(PaymentAPI::class)) {
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

            $payment_helper = new PaymentAPI( (int)$row->ordercode );
            $config         = $payment_helper->getConfig();

            $query = $db->getQuery(true);

            $query->select(array('COUNT(orderid) AS total', 'userid'));
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('paid') . ' != '. $db->quote(1));
            $query->where($db->quoteName('ordercode') . ' = '. $db->quote((int)$row->ordercode));

            $db->setQuery($query);
            $item = $db->loadObject();

            if( $item->total > 0 ){

                $message = new eTicketsMessage;

                $variables = eTicketsMessage::orderVariables((int) $row->ordercode);
                $variables['paymentlink'] = $payment_helper->generatePaymentLink($row->ordercode);

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
     * Each row created for an order gets its own independently generated token
     * (see processWaitingListItem()/OrderController::waitinglist()), but a
     * customer can have several waiting-list rows sharing one ordercode - e.g.
     * one signup per ticket type. The confirmation email only ever embeds one
     * row's token (see Confirmation::SendWaitingList()), so confirming must
     * resolve that single token to its ordercode and then confirm every row
     * for that ordercode - matching confirm($ordercode) below - rather than
     * only the one row whose token happened to be emailed.
     *
     * @param string $token The validation token
     * @return bool
     * @since 1.0.0
     */
    public function confirmByToken($token)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('ordercode'))
            ->from($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('validation_token') . ' = ' . $db->quote($token));
        $db->setQuery($query);

        $ordercode = $db->loadResult();

        if (!$ordercode)
        {
            // Invalid/unknown token - nothing to confirm.
            return false;
        }

        return $this->confirm($ordercode);
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
            ->select(['a.*', "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname", 't.ticketprice', 't.startdate', 'e.eventname'])
            ->from($db->quoteName('#__ticketstation_waitinglist', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->where($db->quoteName('a.ordercode') . " = " . (int) $ordercode)
            ->where($db->quoteName('a.processed') . " = 0");

        $db->setQuery($query);

        return $db->loadObjectList();
    }

}