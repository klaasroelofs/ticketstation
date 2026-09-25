<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

/**
 * How many tickets are still available, following the ticket's settings.
 *
 * Quantity tickets count sold order rows against Total Tickets (Start), as the rest of the
 * storefront does. A child ticket either shares the parent's pool (Choice for Ticketcounter
 * "Use Parent Totals") or has its own cap ("Use Child Totals"); the pool is used up by the
 * orders of the parent and of every child that shares it.
 *
 * Seated tickets count free seats on the chart, limited by the Total Tickets counter the
 * seat-picker checks (see OrderseatedController::makeReservation()): the ticket's own for
 * Multi Seat = Yes, each section's (child ticket's) for Multi Seat = No.
 */
class Availability
{
    /**
     * Tickets available for a top-level ticket as a whole, as shown in the upcoming-events
     * list: for a parent with child tickets, the total over all its published variants.
     */
    public static function forListing(int $ticketid): int
    {
        return self::summary($ticketid)->available;
    }

    /**
     * Tickets available for a top-level ticket as a whole (see forListing()) together with
     * the capacity they are part of, for the availability bar:
     * {available, capacity}. Capacity is Total Tickets (Start) of the ticket, or for a parent
     * with child tickets that of the shared pool plus that of every variant with its own cap;
     * for a seated ticket the number of seats.
     */
    public static function summary(int $ticketid): object
    {
        $summary = (object) ['available' => 0, 'capacity' => 0];
        $ticket  = self::getTicket($ticketid);

        if (!$ticket) {
            return $summary;
        }

        if ($ticket->show_seatplans == 1) {
            return self::freeSeats($ticket);
        }

        $children = self::getChildren($ticketid);

        if (!$children) {
            $summary->available = self::remaining($ticket->starting_total_tickets, [$ticketid]);
            $summary->capacity  = (int) $ticket->starting_total_tickets;

            return $summary;
        }

        $usesPool = false;

        foreach ($children as $child) {
            if ($child->published != 1) {
                continue;
            }

            if ($child->counter_choice == 0) {
                $usesPool = true;
            } else {
                $summary->available += self::remaining($child->starting_total_tickets, [(int) $child->ticketid]);
                $summary->capacity  += (int) $child->starting_total_tickets;
            }
        }

        if ($usesPool) {
            $summary->available += self::poolRemaining($ticket, $children);
            $summary->capacity  += (int) $ticket->starting_total_tickets;
        }

        return $summary;
    }

    /**
     * Tickets still available when buying this (quantity) ticket.
     */
    public static function forPurchase(int $ticketid): int
    {
        $ticket = self::getTicket($ticketid);

        if (!$ticket) {
            return 0;
        }

        if ($ticket->parent > 0 && $ticket->counter_choice == 0) {
            $parent = self::getTicket((int) $ticket->parent);

            return $parent ? self::poolRemaining($parent, self::getChildren((int) $parent->ticketid)) : 0;
        }

        return self::remaining($ticket->starting_total_tickets, [$ticketid]);
    }

    /**
     * Availability of one child ticket (variant) for the backend ticket list:
     * {available, capacity, shared}. "shared" is true when the variant has no stock of its
     * own but draws on its parent: the parent's pool ("Use Parent Totals"), or the parent's
     * seats for a price category of a seated ticket with Multi Seat = Yes. A section of a
     * seated ticket with Multi Seat = No counts its own free seats.
     */
    public static function forVariant(int $ticketid): object
    {
        $summary = (object) ['available' => 0, 'capacity' => 0, 'shared' => false];
        $ticket  = self::getTicket($ticketid);
        $parent  = $ticket && $ticket->parent > 0 ? self::getTicket((int) $ticket->parent) : null;

        if (!$ticket || !$parent) {
            return $summary;
        }

        if ($parent->show_seatplans == 1) {
            $seats = self::freeSeats($parent, $ticketid);

            $summary->available = $seats->available;
            $summary->capacity  = $seats->capacity;
            $summary->shared    = $seats->shared;

            return $summary;
        }

        if ($ticket->counter_choice == 0) {
            $summary->available = self::poolRemaining($parent, self::getChildren((int) $parent->ticketid));
            $summary->capacity  = (int) $parent->starting_total_tickets;
            $summary->shared    = true;

            return $summary;
        }

        $summary->available = self::remaining($ticket->starting_total_tickets, [$ticketid]);
        $summary->capacity  = (int) $ticket->starting_total_tickets;

        return $summary;
    }

    /**
     * What is left of a parent's pool after the orders of the parent itself and of every
     * child that shares the pool.
     */
    private static function poolRemaining(object $parent, array $children): int
    {
        $ids = [(int) $parent->ticketid];

        foreach ($children as $child) {
            if ($child->counter_choice == 0) {
                $ids[] = (int) $child->ticketid;
            }
        }

        return self::remaining($parent->starting_total_tickets, $ids);
    }

    private static function remaining($total, array $ticketids): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->whereIn($db->quoteName('ticketid'), $ticketids);

        $db->setQuery($query);

        return max(0, (int) $total - (int) $db->loadResult());
    }

    /**
     * Free seats of a seated ticket, or with $sectionId only those of that section (child
     * ticket) when Multi Seat = No. With Multi Seat = Yes a child is a price category that
     * shares all of the parent's seats, which the result marks as "shared".
     */
    private static function freeSeats(object $ticket, int $sectionId = 0): object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('multi_seat')
            ->from($db->quoteName('#__ticketstation_seatplansettings'))
            ->where($db->quoteName('ticketid') . ' = ' . (int) $ticket->ticketid);

        $db->setQuery($query);
        $multiSeat = $db->loadResult();

        $summary = (object) ['available' => 0, 'capacity' => 0, 'shared' => $sectionId > 0 && $multiSeat == 1];

        if ($multiSeat === null) {
            return $summary;
        }

        ## Multi Seat = No: per section, the free seats of that child ticket up to its counter.
        ## Otherwise the seats belong to the ticket itself and draw on its own counter.
        $query = $db->getQuery(true)
            ->select(['t.totaltickets', 'SUM(c.booked = 0) AS free', 'COUNT(*) AS seats'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('c.ticketid'))
            ->where($db->quoteName($multiSeat == 1 ? 'c.ticketid' : 'c.parent') . ' = ' . (int) $ticket->ticketid)
            ->group([$db->quoteName('c.ticketid'), $db->quoteName('t.totaltickets')]);

        if ($sectionId > 0 && $multiSeat != 1) {
            $query->where($db->quoteName('c.ticketid') . ' = ' . $sectionId);
        }

        $db->setQuery($query);

        foreach ($db->loadObjectList() as $row) {
            $summary->available += max(0, min((int) $row->free, (int) $row->totaltickets));
            $summary->capacity  += (int) $row->seats;
        }

        return $summary;
    }

    private static function getTicket(int $ticketid): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'parent', 'starting_total_tickets', 'totaltickets', 'counter_choice', 'show_seatplans', 'published'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . $ticketid);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    private static function getChildren(int $parentid): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'parent', 'starting_total_tickets', 'totaltickets', 'counter_choice', 'show_seatplans', 'published'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('parent') . ' = ' . $parentid);

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}
