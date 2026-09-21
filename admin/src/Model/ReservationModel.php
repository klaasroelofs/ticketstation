<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Model backing the admin "new reservation" wizard.
 *
 * @since 1.8.0
 */
class ReservationModel extends BaseDatabaseModel
{
    /**
     * All events, for the step 1 picker - published or not, since admin-made reservations
     * are allowed against unpublished events (see getTicketsForEvent()).
     */
    public function getUpcomingEvents()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['eventid', 'eventname'])
            ->from($db->quoteName('#__ticketstation_events'))
            ->order($db->quoteName('eventname') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Ticket types for one event, for the step 1 picker - published or not, as long as the
     * ticket's own end date/time hasn't passed yet (the event's own dates are irrelevant here).
     */
    public function getTicketsForEvent(int $eventid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'ticketname', 'ticketprice', 'show_seatplans', 'totaltickets'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('eventid') . ' = ' . (int) $eventid)
            ->where($db->quoteName('enddate') . ' > ' . $db->quote(date('Y-m-d H:i:s')))
            ->order($db->quoteName('ticketname') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * All seat coordinates for a (non multi-seat) ticket, with their display settings.
     * Mirrors site/src/Model/SeatedeventModel.php::getNochilds().
     */
    public function getSeats(int $ticketid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.*', 't.ticketname', 'tt.background_color', 'tt.font_color', 'tt.border_color'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('c.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->join('INNER', $db->quoteName('#__ticketstation_seatplansettings', 'tt') . ' ON ' . $db->quoteName('c.ticketid') . ' = ' . $db->quoteName('tt.ticketid'))
            ->where($db->quoteName('c.ticketid') . ' = ' . (int) $ticketid);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * One seat coordinate with its seatplan settings joined in, for the makeReservation() check.
     */
    public function getSeatWithSettings(int $coordId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.*', 't.multi_seat', 't.type', 't.background_color', 't.border_color'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_seatplansettings', 't') . ' ON ' . $db->quoteName('c.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('c.id') . ' = ' . (int) $coordId);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * The order row (if any) that booked a given seat for this ordercode.
     * Mirrors the lookup at the top of site/src/Controller/OrderseatedController.php::removeseat().
     */
    public function getOrderBySeat(string $ordercode, int $coordId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['orderid', 'ticketid'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
            ->where($db->quoteName('seat_sector') . ' = ' . (int) $coordId);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Inserts one order row via the standard Joomla Table bind/check/store pattern, matching
     * site/src/Model/OrderModel.php::store(). Returns the new orderid, or null on failure.
     */
    public function insertOrderRow(array $fields): ?int
    {
        $table = $this->getTable('Order');

        // validation_token is NOT NULL + UNIQUE on #__ticketstation_orders (see
        // admin/sql/updates/mysql/2.0.14.sql) and authorises the guest "pay later"/
        // confirmation links in ValidateController - every insert path needs one,
        // matching site/src/Model/OrderModel.php::store().
        if (empty($fields['validation_token']))
        {
            $fields['validation_token'] = bin2hex(random_bytes(32));
        }

        if (! $table->bind($fields) || ! $table->check() || ! $table->store())
        {
            return null;
        }

        return (int) $table->orderid;
    }

    public function deleteOrderRow(int $orderid): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $orderid);

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Marks a seat booked and links it to the order row, mirroring
     * site/src/Model/OrderModel.php::updateCoords().
     */
    public function updateSeatCoords(int $orderid, int $coordId): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_seatplancoords'))
            ->set($db->quoteName('booked') . ' = 1')
            ->set($db->quoteName('orderid') . ' = ' . (int) $orderid)
            ->where($db->quoteName('id') . ' = ' . (int) $coordId);

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Frees a seat back up, mirroring the coords update in
     * site/src/Controller/OrderseatedController.php::removeseat().
     */
    public function freeSeatCoords(int $coordId): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_seatplancoords'))
            ->set($db->quoteName('booked') . ' = 0')
            ->set($db->quoteName('orderid') . ' = 0')
            ->where($db->quoteName('id') . ' = ' . (int) $coordId);

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Adjusts the remaining-tickets counter on a ticket type by $delta (negative to consume,
     * positive to release).
     */
    public function adjustTicketTotal(int $ticketid, int $delta): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $operator = $delta >= 0 ? '+' : '-';
        $amount   = abs($delta);

        $query = 'UPDATE ' . $db->quoteName('#__ticketstation_tickets')
            . ' SET ' . $db->quoteName('totaltickets') . ' = ' . $db->quoteName('totaltickets') . ' ' . $operator . ' ' . $amount
            . ' WHERE ' . $db->quoteName('ticketid') . ' = ' . (int) $ticketid;

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Finds a client by e-mail address (updating their name/phone if found), or creates a new
     * one - same lookup-or-create pattern as site/src/Controller/CheckoutController.php::save().
     * Returns the clientid, or null on failure.
     */
    public function findOrCreateClient(array $data): ?int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('clientid'))
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('emailaddress') . ' = ' . $db->quote($data['emailaddress']));

        $db->setQuery($query);

        $clientid = (int) $db->loadResult();

        if ($clientid > 0)
        {
            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__ticketstation_clients'))
                ->set($db->quoteName('name') . ' = ' . $db->quote($data['name']))
                ->set($db->quoteName('firstname') . ' = ' . $db->quote($data['firstname']))
                ->set($db->quoteName('phonenumber') . ' = ' . $db->quote($data['phonenumber']))
                ->where($db->quoteName('clientid') . ' = ' . $clientid);

            $db->setQuery($updateQuery);
            $db->execute();

            return $clientid;
        }

        $table = $this->getTable('Clients');

        $data['published']  = 1;
        $data['ipaddress']  = $_SERVER['REMOTE_ADDR'] ?? '';

        if (! $table->bind($data) || ! $table->check() || ! $table->store())
        {
            return null;
        }

        return (int) $table->clientid;
    }

    /**
     * All order rows for an ordercode, with ticket/event names - for the step 4 summary.
     */
    public function getOrderSummary(string $ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 't.ticketname', 'e.eventname', 'sc.row_name AS seat_row_name', 'sc.seatid AS seat_number'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 'sc') . ' ON ' . $db->quoteName('sc.id') . ' = ' . $db->quoteName('a.seat_sector'))
            ->where($db->quoteName('a.ordercode') . ' = ' . $db->quote($ordercode))
            ->order([$db->quoteName('sc.row_name'), $db->quoteName('sc.seatid')]);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Abandons an in-progress reservation: frees any booked seats, restores ticket totals,
     * and removes the order rows for this ordercode.
     */
    public function removeOrderRowsForOrdercode(string $ordercode): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['orderid', 'seat_sector', 'ticketid'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $row)
        {
            if ($row->seat_sector)
            {
                $this->freeSeatCoords((int) $row->seat_sector);
            }

            $this->adjustTicketTotal((int) $row->ticketid, 1);
        }

        $deleteQuery = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($deleteQuery);
        $db->execute();
    }
}
