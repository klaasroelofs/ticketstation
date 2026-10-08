<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;

/**
 * Model backing the admin "new reservation" wizard.
 *
 * @since 1.8.0
 */
class ReservationModel extends BaseDatabaseModel
{
    /**
     * Events for the step 1 picker - published or not, since admin-made reservations are
     * allowed against unpublished events - but only those with at least one ticket that
     * getTicketsForEvent() would list, so the picker never offers an event with nothing to book.
     */
    public function getUpcomingEvents()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Same ticket conditions as getTicketsForEvent().
        $tickets = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->where($db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.enddate') . ' > ' . $db->quote(Date::localNow()))
            ->where('(' . $db->quoteName('p.ticketid') . ' IS NULL OR ' . $db->quoteName('p.show_seatplans') . ' = 0)');

        $query = $db->getQuery(true)
            ->select([$db->quoteName('e.eventid'), $db->quoteName('e.eventname')])
            ->from($db->quoteName('#__ticketstation_events', 'e'))
            ->where('EXISTS (' . $tickets . ')')
            ->order($db->quoteName('e.eventname') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Ticket types for one event, for the step 1 picker - published or not, as long as the
     * ticket's own end date/time hasn't passed yet (the event's own dates are irrelevant here).
     * A child ticket is listed under its parent as "Parent - Child", except the children of a
     * seated ticket: those are sold through the parent's seat chart (as sections or price
     * categories), not on their own.
     */
    public function getTicketsForEvent(int $eventid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketprice', 't.show_seatplans',
                "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname"])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->where($db->quoteName('t.eventid') . ' = ' . (int) $eventid)
            ->where($db->quoteName('t.enddate') . ' > ' . $db->quote(Date::localNow()))
            ->where('(' . $db->quoteName('p.ticketid') . ' IS NULL OR ' . $db->quoteName('p.show_seatplans') . ' = 0)')
            ->order('COALESCE(' . $db->quoteName('p.ticketname') . ', ' . $db->quoteName('t.ticketname') . '), ' . $db->quoteName('t.parent') . ', ' . $db->quoteName('t.ticketname'));

        $db->setQuery($query);
        $tickets = $db->loadObjectList();

        foreach ($tickets as $ticket)
        {
            $ticket->available = Availability::forTicket((int) $ticket->ticketid);
        }

        return $tickets;
    }

    /**
     * All seat coordinates on a ticket's seat chart - its free seats and the section seats of
     * its child tickets - with their display settings.
     */
    public function getSeats(int $ticketid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.*', 't.ticketname'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('c.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where('(' . $db->quoteName('c.ticketid') . ' = ' . (int) $ticketid . ' OR ' . $db->quoteName('c.parent') . ' = ' . (int) $ticketid . ')');

        $db->setQuery(SeatplanSettings::apply($query));

        return $db->loadObjectList();
    }

    /**
     * One seat coordinate with its seatplan settings joined in, for the makeReservation() check.
     */
    public function getSeatWithSettings(int $coordId)
    {
        return SeatplanSettings::forSeat($coordId);
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

    /**
     * A seat's order row in this reservation by its orderid, or null when it belongs to
     * another ordercode.
     */
    public function getOrderRow(string $ordercode, int $orderid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['orderid', 'ticketid', 'seat_sector'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $orderid)
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Sells an order row as another ticket (the price category of a free seat), with the new
     * ticket's price and VAT.
     */
    public function changeOrderTicket(int $orderid, array $fields): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)->update($db->quoteName('#__ticketstation_orders'));

        foreach ($fields as $column => $value)
        {
            $query->set($db->quoteName($column) . ' = ' . $db->quote($value));
        }

        $query->where($db->quoteName('orderid') . ' = ' . (int) $orderid);

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Removes the rows without a seat of one ticket type from this reservation.
     */
    public function deleteTicketRows(string $ordercode, int $ticketid): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
            ->where($db->quoteName('ticketid') . ' = ' . (int) $ticketid)
            ->where($db->quoteName('seat_sector') . ' = 0');

        $db->setQuery($query);

        return (bool) $db->execute();
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

        // Only a seat without an order that is free or blocked (the box office may book
        // blocked seats), in one statement, so two people can't both get the same seat.
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_seatplancoords'))
            ->set($db->quoteName('booked') . ' = 1')
            ->set($db->quoteName('orderid') . ' = ' . (int) $orderid)
            ->where($db->quoteName('id') . ' = ' . (int) $coordId)
            ->where($db->quoteName('orderid') . ' = 0')
            ->where('(' . $db->quoteName('booked') . ' = 0 OR ' . $db->quoteName('blocked') . ' = 1)');

        $db->setQuery($query);

        return $db->execute() && $db->getAffectedRows() > 0;
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
            // A blocked seat goes back to blocked, any other seat to free.
            ->set($db->quoteName('booked') . ' = ' . $db->quoteName('blocked'))
            ->set($db->quoteName('orderid') . ' = 0')
            ->where($db->quoteName('id') . ' = ' . (int) $coordId);

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
            ->order([$db->quoteName('sc.row_name'), 'CAST(' . $db->quoteName('sc.seatid') . ' AS UNSIGNED)', $db->quoteName('sc.seatid')]);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * The reservation as display lines, like the cart of the checkout: one line per ticket type,
     * with a quantity for tickets without a seat and the seats listed under a seated ticket.
     * Sorted per event, per parent ticket (earliest date first), tickets without a seat before
     * seats, seats most expensive first and by seat number within a ticket type.
     *
     * @return  array  objects with eventname, ticketname, date, owner, ticketid, seated, unit,
     *                 quantity, total, seats (orderid, sector, label, free, ticketid) and rows
     */
    public function getReservationLines(string $ordercode): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'a.orderid', 'a.ticketid', 'a.eventid', 'a.price', 'a.seat_sector',
                "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname",
                't.ticketprice', 'e.eventname',
                'IFNULL(p.ticketid, t.ticketid) AS owner_ticketid',
                'IFNULL(p.startdate, t.startdate) AS owner_startdate',
                'sc.row_name AS seat_row_name', 'sc.seatid AS seat_number', 'sc.parent AS seat_parent',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 'sc') . ' ON ' . $db->quoteName('sc.id') . ' = ' . $db->quoteName('a.seat_sector'))
            ->where($db->quoteName('a.ordercode') . ' = ' . $db->quote($ordercode))
            ->order($db->quoteName('a.orderid'));

        $db->setQuery($query);

        $lines      = [];
        $eventOrder = [];

        foreach ($db->loadObjectList() as $row)
        {
            $seated = (int) $row->seat_sector !== 0;
            $key    = ($seated ? 'seat' : 'ticket') . $row->ticketid;

            $eventOrder[$row->eventid] ??= count($eventOrder);

            $lines[$key] ??= (object) [
                'event'     => $eventOrder[$row->eventid],
                'eventname' => $row->eventname,
                'ticketname' => $row->ticketname,
                'date'      => (string) $row->owner_startdate,
                'owner'     => (int) $row->owner_ticketid,
                'ticketid'  => (int) $row->ticketid,
                'seated'    => $seated,
                'unit'      => (float) $row->price,
                'seats'     => [],
                'quantity'  => 0,
                'total'     => 0.0,
                'position'  => count($lines),
            ];

            $line = $lines[$key];
            $line->quantity++;
            $line->total += (float) $row->price;

            if ($seated)
            {
                $line->seats[] = (object) [
                    'orderid'  => (int) $row->orderid,
                    'sector'   => (int) $row->seat_sector,
                    'label'    => $row->seat_row_name . $row->seat_number,
                    // A free seat (not a section's) can be switched between price categories.
                    'free'     => (int) $row->seat_parent === 0,
                    'ticketid' => (int) $row->ticketid,
                ];
            }
        }

        $key = fn ($line) => [$line->event, $line->date, $line->owner, $line->seated,
            $line->seated ? -$line->unit : $line->position, $line->ticketid];

        usort($lines, fn ($a, $b) => $key($a) <=> $key($b));

        foreach ($lines as $line)
        {
            usort($line->seats, fn ($a, $b) => strnatcasecmp($a->label, $b->label));
        }

        return $lines;
    }

    /**
     * Resets the orderdate of the reservation's rows to now. Until step 4 they carry no client
     * (userid 0), so Ticketcleaner::cleanup() treats them as an abandoned cart and removes them
     * once older than removal_hours; refreshing them while the wizard is open makes that period
     * count from the last activity instead of from the first ticket added.
     *
     * @return  integer  the number of rows the reservation still holds
     */
    public function touchOrderRows(string $ordercode): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_orders'))
            // Written with date(), like the inserts, as cleanup() compares against date() too.
            ->set($db->quoteName('orderdate') . ' = ' . $db->quote(date('Y-m-d H:i:s')))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
            ->where($db->quoteName('paid') . ' = 0')
            ->where($db->quoteName('userid') . ' = 0')
            ->where($db->quoteName('published') . ' = 0');

        $db->setQuery($query);
        $db->execute();

        return count($this->getOrderSummary($ordercode));
    }

    /**
     * Abandons an in-progress reservation: frees any booked seats and removes the order rows
     * for this ordercode, which makes their tickets available again.
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
        }

        $deleteQuery = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($deleteQuery);
        $db->execute();
    }
}
