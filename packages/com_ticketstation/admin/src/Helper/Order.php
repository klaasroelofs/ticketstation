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


## no direct access
use Joomla\CMS\Factory;


defined('_JEXEC') or die('Restricted access');

class Order
{
    /**
     *
     * @return mixed
     *
     * @since 3.5.4
     */
    public static function getOrdersInCartGroupedByEvent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'o.eventid',
                'o.ticketid',
                'e.eventname',
                'COUNT(o.ticketid) as total',
                'o.ticketid',
                'SUM(t.ticketprice) AS eventprice',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('o.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->where($db->quoteName('o.ordercode') . " = " . Factory::getSession()->get('ordercode'))
            ->group('eventid');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        foreach ($data as &$item)
        {
            $item->tickets = self::getTicketDetails($item->eventid);
        }

        return $data;
    }

    /**
     * Getting current orders in the cart.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrdersInCart()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 'c.*', 't.ticketname', 't.ticketprice', 't.startdate', 'e.eventname', 't.enddate', 'b.country'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_country', 'b') . ' ON ' . $db->quoteName('c.country_id') . ' = ' . $db->quoteName('b.country_id'))
            ->where($db->quoteName('a.ordercode') . " = " . Factory::getApplication()->getSession()->get('ordercode'))
            ->where($db->quoteName('a.paid') . " != 1");

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting order details by ordercode.
     * This can be paid and unpaid tickets
     *
     * @param null $ordercode
     * @param null $userid
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrderDetailsByOrdercode($ordercode = null, $userid = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['o.*', 't.ticketname', 't.eventid', 't.parent AS parent_ticket', 't.ticketdate', 't.ticketprice', 'c.seatid'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 'c') . ' ON ' . $db->quoteName('o.seat_sector') . ' = ' . $db->quoteName('c.id'))
            ->where($db->quoteName('o.ordercode') . " = " . (int) $ordercode);

        if ($userid)
        {
            // Getting only specific orders for this user. (no faking allowed for the frontend)
            $query->where($db->quoteName('o.userid') . " = " . $userid);
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the orders from the database by the orderide.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrderByOrderId($orderid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $orderid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting the ordersfrom the database by the current ordercode.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrdersByOrdercode($ticketid = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . Factory::getApplication()->getSession()->get('ordercode'));

        if($ticketid)
        {
            $query->where($db->quoteName('ticketid') .' = ' .(int) $ticketid);
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the total amount of orders in acrt.
     *
     * @param string $table
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getOrdersCountByOrdercode($table = '#__ticketstation_orders')
    {
        $ordercode = Factory::getApplication()->getSession()->get('ordercode');

        if (!$ordercode) {
            return '0';

        } else {

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select(['COUNT(ordercode) AS total'])
                ->from($db->quoteName($table))
                ->where($db->quoteName('ordercode') . " = " . $ordercode);
            //->group('ordercode');

            // Waiting-list rows that were already promoted to a real order no longer count as
            // waiting; this matches what the cart lists (WaitingList::getOrdersOnWaitingList()).
            if ($table === '#__ticketstation_waitinglist')
            {
                $query->where($db->quoteName('processed') . ' = 0');
            }

            $db->setQuery($query);
            $order = $db->loadObject();

            return $order->total;

        }
    }

    /**
     * Updating the order with a new ordercode and userid.
     *
     * @param $userid
     * @param $ordercode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function update($userid, $ordercode)
    {
        if ($userid == '')
        {
            return false;
        }

        // Getting a new ordercode from the database.
        $new_ordercode = (new Ordercode)->getFinalOrdercode($ordercode);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('userid') . ' = ' . (int) $userid,
            $db->quoteName('ordercode') . ' = ' . $db->quote($new_ordercode),
        ];

        $conditions = [$db->quoteName('ordercode') . ' = ' . $db->quote($ordercode)];

        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        // This is the point where the temporary (session) ordercode used while building the
        // cart/reservation becomes the order's real, final ordercode - so this is where "order
        // created" belongs, not at the individual add-to-cart/add-ticket-row calls (those still
        // use the temporary code, which never appears in the UI once it's rewritten here).
        // A plain log() (not logOnce()) so that a reused ordercode still gets its own fresh
        // "created" timestamp - getForOrder() relies on that to hide an older, removed order's
        // history from the new order that reused its ordercode.
        History::log($new_ordercode, 'order_created', 'Order created');

        return true;
    }

    /**
     * Removing an order from the datbase by an orderid
     *
     * @param $orderid
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function removeSingleOrderFromDatabase($orderid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        // Conditions for the removal.
        $conditions = [$db->quoteName('orderid') . ' = ' . (int) $orderid];

        // prepare the query
        $query->delete($db->quoteName('#__ticketstation_orders'))
            ->where($conditions);

        $db->setQuery($query);

        ## When query goes wrong.. Show message with error.
        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }

    /**
     * Removing an order from the datbase by an orderid
     *
     * @param $orderid
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function removeFullOrderFromDatabase($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        // Conditions for the removal.
        $conditions = [$db->quoteName('ordercode') . ' = ' . $ordercode];

        // prepare the query
        $query->delete($db->quoteName('#__ticketstation_orders'))
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }

    /**
     * Checks if an order is pending or unpaid.
     * Return false when the order is unknown, otherwise true.
     *
     * @param $ordercode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function isOrderPending($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['COUNT(orderid) AS total'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where('(' . $db->quoteName('paid') . " = " . $db->quote(0) . ' OR ' . $db->quoteName('paid') . ' = ' . $db->quote(3) . ')')
            ->where($db->quoteName('ordercode') . " = " . (int) $ordercode);

        $db->setQuery($query);

        $order = $db->loadObject();

        return ($order->total) > 0 ? true : false;
    }

    /**
     * Getting the user id for a specific ordercode.
     *
     * @param $ordercode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function getUserByOrderCode($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['userid'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . " = " . (int) $ordercode)
            ->group('ordercode');

        $db->setQuery($query);

        $order = $db->loadObject();

        return ! empty($order) ? $order->userid : 0;
    }

    /**
     * Getting an order by the barcode. Returning the order object.
     *
     * @param $barcode
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrderByBarcode($barcode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('barcode') . ' = ' . $db->quote($barcode));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting the number of tickets scanned.
     *
     * @param $barcode
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getNumberofTicketsScanned($ticket, $event)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(orderid)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('scanned') . ' = ' . $db->quote(1));

        if ($ticket != 0) {
            $query->where($db->quoteName('ticketid') . ' = ' . (int) $ticket);
        } else {
            $query->where($db->quoteName('eventid') . ' = ' . (int) $event);
        }

        $db->setQuery($query);

        return $db->loadResult();
    }

    /**
     * Getting an order by the barcode. Returning the order object.
     *
     * @param $ordercode
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getNumberOfTicketsInOrder($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($query);

        $tickets_left = count($db->loadObjectList());

        return $tickets_left;
    }
}