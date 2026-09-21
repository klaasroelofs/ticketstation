<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */


namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


## no direct access
defined('_JEXEC') or die('Restricted access');

class Ticketcleaner
{

    function cleanup()
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('remove_unfinished', 'removal_hours', 'removal_days', 'show_waitinglist', 'pro_installed'));
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $config = $db->loadObject();

        // Check PRO tables on relations.
        $this->checkProSeats();

        if ($config->remove_unfinished != 1) {
            return false;
        }

        ## THIS IS THE CLEAN UP OF UNFINISHED ORDERS (NOT PAID, NOT PUBLISHED)
        ## Create a new date NOW()-1h (database session is not longer than 2 hours in global config).
        if ($config->removal_hours >= 1) {
            $cleanup = date('Y-m-d H:i:s', mktime(date('H') - $config->removal_hours, date('i'), date('s'), date('m'), date('d'), date('Y')));
        } else {
            $minutes = intval(60 * $config->removal_hours);
            $cleanup = date('Y-m-d H:i:s', mktime(date('H'), date('i') - $minutes, date('s'), date('m'), date('d'), date('Y')));
        }
        
        if ($config->show_waitinglist == 1) {
            ## Promoting a waiting list customer already deletes the specific stale order(s)
            ## it hands over to them (see WaitingList::processWaitingListItem). Whatever is
            ## left over below is genuinely unclaimed and must still be released back to
            ## stock (and logged), regardless of whether the waiting list is on.
            $waiting = new WaitingList();
            $waiting->processList(array(), $cleanup);
        }

        $query = $db->getQuery(true)
            ->select(array('o.*', 't.parent AS parentticket'))
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')')
            ->where($db->quoteName('o.orderdate') . ' < ' . $db->quote($cleanup))
            ->where($db->quoteName('o.paid') . ' = 0')
            ->where($db->quoteName('o.userid') . ' = 0')
            ->where($db->quoteName('o.published') . ' = 0');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        if (count($data) > 0) {

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('orderdate') . ' < ' . $db->quote($cleanup),
                $db->quoteName('paid') . ' = 0',
                $db->quoteName('userid') . ' = 0',
                $db->quoteName('published') . ' = 0'
            );

            $query->delete($db->quoteName('#__ticketstation_orders'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            ## When query goes wrong.. Show message with error.
            if ($result) {

                ## Tickets have been removed successful
                ## Now we need to update the totals from the Object earlier this script.

                for ($i = 0, $n = count($data); $i < $n; $i++) {
                    $row = $data[$i];

                    // Snapshot the seat descriptor before it gets reset below, so the
                    // history entry can still show which seat this order held.
                    $this->attachSeatDescriptor($row);

                    $ticket_helper = new Tickets();

                    // Increasing ticket totals:
                    $ticket_helper->increaseTicketTotals($row->ticketid);

                    // Increasing parentticket totals:
                    if ($row->parentticket != 0) {
                        $ticket_helper->increaseTicketTotals($row->parentticket);
                    }

                    // Check if there was a seat booked:
                    if ($row->seat_sector != 0) {
                        $ticket_helper->resetSeatSate($row->orderid);
                    }
                }

                $this->logAutoRemoval($data, 'unfinished', 'Removed (unfinished order)');
            }
        }

        ## THIS IS THE CLEAN UP OF PENDING ORDERS (STATUS UNPAID (0) OR PENDING (3))
        ## TYPICALLY, THESE ARE ORDERS THAT REACHED THE PAYMENT SCREEN OF FAILED PAYMENT (PAID = 0 / PUBLISHED = 0) OR FOR WHICH A PAYMENT LINK WAS SENT (PAID = 3 / PUBLISHED = 1), BUT THAT WERE NEVER ACTUALLY PAID
        $cleanup_pending = date('Y-m-d H:i:s', mktime(date('H'), date('i'), date('s'), date('m'), date('d') - $config->removal_days, date('Y')));

        if ($config->show_waitinglist == 1) {
            $waiting = new WaitingList();
            $waiting->processList(array(), $cleanup_pending);
        }

        $query = $db->getQuery(true)
            ->select(array('o.*', 't.parent AS parentticket'))
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')')
            ->where($db->quoteName('o.orderdate') . ' < ' . $db->quote($cleanup_pending))
            ->where('(' . $db->quoteName('o.paid') . ' = ' . $db->quote(0) . ' OR ' . $db->quoteName('o.paid') . ' = ' . $db->quote(3) . ')');
            //->where($db->quoteName('o.published') . ' = 1');

        $db->setQuery($query);
        $data_pending = $db->loadObjectList();

        if (count($data_pending) > 0) {

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('orderdate') . ' < ' . $db->quote($cleanup_pending),
                '(' . $db->quoteName('paid') . ' = ' . $db->quote(0) . ' OR ' . $db->quoteName('paid') . ' = ' . $db->quote(3) . ')'
                //$db->quoteName('published') . ' = 1'
            );

            $query->delete($db->quoteName('#__ticketstation_orders'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            ## When query goes wrong.. Show message with error.
            if ($result) {

                ## Tickets have been removed successful
                ## Now we need to update the totals from the Object earlier this script.

                for ($i = 0, $n = count($data_pending); $i < $n; $i++) {
                    $row = $data_pending[$i];

                    // Snapshot the seat descriptor before it gets reset below, so the
                    // history entry can still show which seat this order held.
                    $this->attachSeatDescriptor($row);

                    $ticket_helper = new Tickets();

                    // Increasing ticket totals:
                    $ticket_helper->increaseTicketTotals($row->ticketid);

                    // Increasing parentticket totals:
                    if ($row->parentticket != 0) {
                        $ticket_helper->increaseTicketTotals($row->parentticket);
                    }

                    // Check if there was a seat booked:
                    if ($row->seat_sector != 0) {
                        $ticket_helper->resetSeatSate($row->orderid);
                    }
                }

                $this->logAutoRemoval($data_pending, 'pending', 'Removed (pending payment expired)');
            }
        }

        return true;

    }

    /**
     * Looks up the seat descriptor (seatid, row_name) for an order row still holding a
     * seat, and stores it on the row before resetSeatSate() clears the link. Used so the
     * "removed by ticketcleaner" history snapshot can still show which seat was freed.
     *
     * @param   object  $row  Order row (from #__ticketstation_orders), modified in place.
     *
     * @return  void
     */
    private function attachSeatDescriptor($row)
    {
        $row->seatid   = null;
        $row->row_name = null;

        if ($row->seat_sector == 0) {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(array('seatid', 'row_name'))
            ->from($db->quoteName('#__ticketstation_seatplancoords'))
            ->where($db->quoteName('orderid') . ' = ' . $db->quote($row->orderid));

        $db->setQuery($query);
        $seat = $db->loadObject();

        if ($seat) {
            $row->seatid   = $seat->seatid;
            $row->row_name = $seat->row_name;
        }
    }

    /**
     * Records a "removed by ticketcleaner" history entry per affected order, with a full
     * snapshot of its (now deleted) rows as context. The Box Office uses this snapshot to
     * keep the order visible/auditable after the fact, without the order row itself having
     * to survive - front-end availability and reporting queries keep reading
     * #__ticketstation_orders exactly as before.
     *
     * @param   array   $rows     Order rows that were just deleted (each carries ->ordercode).
     * @param   string  $reason   Short machine-readable reason ('unfinished' or 'pending').
     * @param   string  $message  Human-readable history message.
     *
     * @return  void
     */
    private function logAutoRemoval(array $rows, $reason, $message)
    {
        $byOrdercode = array();

        foreach ($rows as $row) {
            $byOrdercode[$row->ordercode][] = get_object_vars($row);
        }

        foreach ($byOrdercode as $ordercode => $snapshotRows) {
            History::log($ordercode, 'order_removed_auto', $message, array(
                'reason' => $reason,
                'rows'   => $snapshotRows,
            ), 'Ticketcleaner');
        }
    }

    private function checkProSeats()
    {

        $helper = new Tickets;

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('remove_unfinished', 'removal_hours', 'show_waitinglist'));
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $config = $db->loadObject();


        $query = $db->getQuery(true);

        $query->select(array('c.id', 'c.orderid AS orderid_from_coordstable', 'o.orderid AS orderid_from_orderstable'));
        $query->from($db->quoteName('#__ticketstation_seatplancoords', 'c'));
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_orders', 'o') . ' ON (' . $db->quoteName('c.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');
        $query->where($db->quoteName('c.orderid') . ' != 0');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $row = $data[$i];

            if (!$row->orderid_from_orderstable) {
                $helper->resetSeatSate($row->orderid_from_coordstable);
            }
        }

        return true;
    }

    function counter()
    {
        return $this->counter;
    }

    function error()
    {
        return $this->setError;
    }

}