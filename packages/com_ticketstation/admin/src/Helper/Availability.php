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
 * How many tickets are still available: the capacity minus the order rows that hold it,
 * whatever their status. Nothing is counted up or down anywhere else; an order row exists
 * exactly as long as its ticket is taken (the ticketcleaner, the Box Office and the cart
 * delete the row to release it).
 *
 * Quantity tickets: the capacity is Capacity (starting_total_tickets). A parent with child
 * tickets chooses, in its own counter_choice, whether the children share its capacity
 * (0, one pool for the parent and all children) or each have their own (1).
 *
 * Seated tickets: the capacity is the number of seats on the chart that aren't blocked, and
 * available is that minus the order rows, in the same way (see freeSeats()).
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
     * the capacity they are part of, for the availability bar: {available, capacity}. With
     * a capacity per child ticket, both are the sums over the published children; for a
     * seated ticket, the seats.
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

        if (!$children || $ticket->counter_choice == 0) {
            $summary->available = self::remaining($ticket->starting_total_tickets, self::poolIds($ticket, $children));
            $summary->capacity  = (int) $ticket->starting_total_tickets;

            return $summary;
        }

        foreach ($children as $child) {
            if ($child->published == 1) {
                $summary->available += self::remaining($child->starting_total_tickets, [(int) $child->ticketid]);
                $summary->capacity  += (int) $child->starting_total_tickets;
            }
        }

        return $summary;
    }

    /**
     * Tickets still available when buying this (quantity) ticket.
     */
    public static function forPurchase(int $ticketid): int
    {
        $pool = self::pool($ticketid);

        return $pool ? self::remaining($pool->capacity, $pool->ids) : 0;
    }

    /**
     * Tickets available for any single ticket, for screens that list tickets one by one
     * (the backend reservation tool): a child ticket's own or shared availability, a seated
     * ticket's free seats, or a quantity ticket's Capacity left.
     */
    public static function forTicket(int $ticketid): int
    {
        $ticket = self::getTicket($ticketid);

        if (!$ticket) {
            return 0;
        }

        if ($ticket->parent > 0) {
            return self::forVariant($ticketid)->available;
        }

        return $ticket->show_seatplans == 1 ? self::freeSeats($ticket)->available : self::forPurchase($ticketid);
    }

    /**
     * Adds $amount order rows for a quantity ticket, but only while its capacity allows it.
     * The pool's ticket row is locked for the duration, so two customers buying the last
     * tickets at the same moment are handled one after the other instead of both passing
     * the check. $store is called once per ticket and returns false on failure, which
     * undoes every row added in this call.
     *
     * @return  bool  false when there is not enough capacity left or a row failed to save.
     */
    public static function reserve(int $ticketid, int $amount, callable $store): bool
    {
        $pool = self::pool($ticketid);

        if (!$pool || $amount < 1) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->transactionStart();

        try {
            $db->setQuery('SELECT ' . $db->quoteName('ticketid') . ' FROM ' . $db->quoteName('#__ticketstation_tickets')
                . ' WHERE ' . $db->quoteName('ticketid') . ' = ' . (int) $pool->ticketid . ' FOR UPDATE');
            $db->execute();

            if (self::remaining($pool->capacity, $pool->ids) < $amount) {
                $db->transactionRollback();

                return false;
            }

            for ($i = 0; $i < $amount; $i++) {
                if (!$store()) {
                    $db->transactionRollback();

                    return false;
                }
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return false;
        }

        return true;
    }

    /**
     * Availability of one child ticket (variant) for the backend ticket list:
     * {available, capacity, shared}. "shared" is true when the variant has no stock of its
     * own but draws on its parent: the parent's shared capacity, or the free seats of the
     * chart for a price category of a seated ticket. A section of a seated ticket counts its
     * own seats.
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

        $pool = self::pool($ticketid);

        $summary->available = self::remaining($pool->capacity, $pool->ids);
        $summary->capacity  = $pool->capacity;
        $summary->shared    = $pool->ticketid !== (int) $ticket->ticketid;

        return $summary;
    }

    /**
     * The ticket whose Capacity a quantity ticket sells from: its parent when that shares its
     * capacity with the child tickets, otherwise the ticket itself.
     */
    public static function poolOwner(int $ticketid): int
    {
        $pool = self::pool($ticketid);

        return $pool ? $pool->ticketid : 0;
    }

    /**
     * The capacity a quantity ticket sells from: {ticketid, capacity, ids}, where ticketid is
     * the ticket whose Capacity applies (and whose row reserve() locks) and ids are the
     * tickets whose order rows use it up. A child of a parent with shared capacity sells
     * from the parent's pool; any other ticket from its own Capacity.
     */
    private static function pool(int $ticketid): ?object
    {
        $ticket = self::getTicket($ticketid);

        if (!$ticket) {
            return null;
        }

        $owner = $ticket;

        if ($ticket->parent > 0) {
            $parent = self::getTicket((int) $ticket->parent);

            if ($parent && $parent->counter_choice == 0) {
                $owner = $parent;
            }
        }

        $ids = $owner->ticketid === $ticket->ticketid && $ticket->parent > 0
            ? [(int) $ticket->ticketid]
            : self::poolIds($owner, self::getChildren((int) $owner->ticketid));

        return (object) [
            'ticketid' => (int) $owner->ticketid,
            'capacity' => (int) $owner->starting_total_tickets,
            'ids'      => $ids,
        ];
    }

    /**
     * The tickets whose orders use up a top-level ticket's Capacity: the ticket itself and,
     * with shared capacity, all its child tickets. The parent's own orders always count, so
     * a (manual) order on the parent can't let the pool overflow.
     */
    private static function poolIds(object $ticket, array $children): array
    {
        $ids = [(int) $ticket->ticketid];

        if ($ticket->counter_choice == 0) {
            foreach ($children as $child) {
                $ids[] = (int) $child->ticketid;
            }
        }

        return $ids;
    }

    private static function remaining($capacity, array $ticketids): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->whereIn($db->quoteName('ticketid'), $ticketids);

        $db->setQuery($query);

        return max(0, (int) $capacity - (int) $db->loadResult());
    }

    /**
     * Seats of a seated ticket's chart: {available, capacity, shared}. The capacity is the
     * number of seats that aren't blocked, plus blocked seats the box office sold anyway;
     * available is that minus the order rows that use them. Without $childId for the whole chart (all orders of the owner and its children).
     * With $childId for one child ticket: a section counts its own seats and orders; a price
     * category (a child without seats) shares the free seats with the owner and the other
     * categories, which the result marks as "shared".
     */
    private static function freeSeats(object $owner, int $childId = 0): object
    {
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $ownerId = (int) $owner->ticketid;
        $summary = (object) ['available' => 0, 'capacity' => 0, 'shared' => false];

        ## Seats per ticket they belong to: the owner (free seats) or a child (its section).
        $query = $db->getQuery(true)
            ->select(['c.ticketid', 'SUM(c.blocked = 0 OR c.orderid > 0) AS seats'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->where('(' . $db->quoteName('c.ticketid') . ' = ' . $ownerId . ' OR ' . $db->quoteName('c.parent') . ' = ' . $ownerId . ')')
            ->group($db->quoteName('c.ticketid'));

        $db->setQuery($query);
        $seats = array_map('intval', $db->loadAssocList('ticketid', 'seats'));

        $ids = [$ownerId];

        foreach (self::getChildren($ownerId) as $child) {
            $ids[] = (int) $child->ticketid;
        }

        $query = $db->getQuery(true)
            ->select(['ticketid', 'COUNT(*) AS sold'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->whereIn($db->quoteName('ticketid'), $ids)
            ->group($db->quoteName('ticketid'));

        $db->setQuery($query);
        $sold = array_map('intval', $db->loadAssocList('ticketid', 'sold'));

        if ($childId > 0 && isset($seats[$childId])) {
            $summary->capacity  = $seats[$childId];
            $summary->available = max(0, $seats[$childId] - ($sold[$childId] ?? 0));

            return $summary;
        }

        if ($childId > 0) {
            ## A price category: the free seats, used up by the owner and every category.
            $taken = 0;

            foreach ($ids as $id) {
                if (!isset($seats[$id]) || $id === $ownerId) {
                    $taken += $sold[$id] ?? 0;
                }
            }

            $summary->capacity  = $seats[$ownerId] ?? 0;
            $summary->available = max(0, $summary->capacity - $taken);
            $summary->shared    = true;

            return $summary;
        }

        $summary->capacity  = array_sum($seats);
        $summary->available = max(0, $summary->capacity - array_sum($sold));

        return $summary;
    }

    private static function getTicket(int $ticketid): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'parent', 'starting_total_tickets', 'counter_choice', 'show_seatplans', 'published'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . $ticketid);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    private static function getChildren(int $parentid): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'parent', 'starting_total_tickets', 'counter_choice', 'show_seatplans', 'published'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('parent') . ' = ' . $parentid);

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}
