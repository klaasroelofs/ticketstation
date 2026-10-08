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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * "Prevent single empty seats" (prevent_orphans in a chart's settings row): a customer on the
 * site may not leave a single free seat (an orphan) between the seats in their order and a
 * taken seat or the end of a row, unless there is no other way. See docs/weesstoelen.md.
 *
 * Seats are claimed one click at a time, so the rule is checked on the whole choice: when the
 * customer continues from the seat-picking page, and again on the cart and checkout (guard()).
 * The backend (box office, reservations) is never restricted.
 *
 * Rows: seats with the same row name on one chart, in the order they stand. Seats with the same
 * row name that are far apart (another block, a balcony) or separated by an aisle form separate
 * segments; the end of a segment counts as the end of the row. A seat without a row name doesn't
 * take part.
 *
 * Within a segment a block is a run of seats that are free or in this order, between taken
 * seats or segment ends. A free seat is an orphan when neither neighbour in its block is free
 * and at least one of them is in this order. The choice is still allowed when the block leaves
 * exactly one seat free: then every placement of that many seats leaves one over.
 *
 * Long tables: the seats on both sides of a table share a row name and form two parallel
 * segments. An orphan with a free seat of the same row right across from it is no orphan after
 * all, as the two can be sold together (freeOpposite()).
 */
class SeatOrphans
{
    private const FREE  = 0;
    private const TAKEN = 1;
    private const MINE  = 2;

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Whether a chart has the rule switched on.
     */
    public static function enabled(int $ownerId): bool
    {
        $settings = SeatChart::settings($ownerId);

        return $settings && (int) ($settings->prevent_orphans ?? 0) === 1;
    }

    /**
     * The orphans an order leaves on one chart, as seat rows (id, row_name, seatid). Empty when
     * the rule is off for the chart or the order has no seats on it.
     *
     * @return  object[]
     */
    public static function forChart(int $ownerId, int $ordercode): array
    {
        if ($ownerId <= 0 || $ordercode <= 0 || !self::enabled($ownerId)) {
            return [];
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['c.id', 'c.x_pos', 'c.y_pos', 'c.width', 'c.height', 'c.row_name', 'c.seatid', 'c.booked', 'o.orderid AS mine'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('LEFT', $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = c.orderid AND c.orderid > 0 AND o.ordercode = ' . $ordercode)
            ->where('(c.ticketid = ' . $ownerId . ' OR c.parent = ' . $ownerId . ')')
            ->where("IFNULL(c.row_name, '') <> ''");

        $seats = $db->setQuery($query)->loadObjectList();
        $any   = false;

        foreach ($seats as $seat) {
            ## Taken covers sold, in someone's basket and blocked (booked = 1 for all three).
            $seat->state = $seat->mine ? self::MINE : ((int) $seat->booked === 1 ? self::TAKEN : self::FREE);
            $any         = $any || $seat->state === self::MINE;
        }

        if (!$any) {
            return [];
        }

        $orphans  = [];
        $segments = self::segments($seats);

        foreach ($segments as $index => $segment) {
            foreach ($segment as $seat) {
                $seat->segment = $index;
            }
        }

        $avoidable = self::avoidable($segments, $seats);

        foreach ($segments as $segment) {
            foreach (self::orphansIn($segment, $avoidable) as $orphan) {
                if (!self::freeOpposite($orphan, $seats)) {
                    $orphans[] = $orphan;
                }
            }
        }

        return $orphans;
    }

    /**
     * Whether a free seat of the same row stands right across from this one, as at a long table
     * with seats on both sides: the two can still be sold together, so neither is an orphan.
     *
     * Across means: another segment of the same row, at about the same place along the row (the
     * centres less than half a seat apart) and at most two seats away sideways. Rows on either
     * side of an aisle lie in line, not across, so theatre rows aren't affected.
     */
    private static function freeOpposite(object $seat, array $seats, array $ignore = []): bool
    {
        $cx = $seat->x_pos + $seat->width / 2;
        $cy = $seat->y_pos + $seat->height / 2;

        ## A seat alone in its segment has no direction of its own: try both.
        $directions = $seat->direction === null ? [false, true] : [$seat->direction];

        foreach ($seats as $other) {
            if ($other === $seat || $other->state !== self::FREE || isset($ignore[$other->id]) || $other->segment === $seat->segment
                || (string) $other->row_name !== (string) $seat->row_name) {
                continue;
            }

            $ox = $other->x_pos + $other->width / 2;
            $oy = $other->y_pos + $other->height / 2;

            foreach ($directions as $vertical) {
                $along    = $vertical ? abs($oy - $cy) : abs($ox - $cx);
                $sideways = $vertical ? abs($ox - $cx) - ($seat->width + $other->width) / 2 : abs($oy - $cy) - ($seat->height + $other->height) / 2;
                $size     = $vertical ? $seat->height : $seat->width;
                $side     = $vertical ? $seat->width : $seat->height;

                if ($along < $size / 2 && $sideways <= 2 * $side) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The first chart on which an order leaves orphans, as [owner id, orphan seats], or null.
     */
    public static function violation(int $ordercode): ?array
    {
        if ($ordercode <= 0) {
            return null;
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select('DISTINCT IF(c.parent > 0, c.parent, c.ticketid)')
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = c.orderid')
            ->where('c.orderid > 0')
            ->where($db->quoteName('o.ordercode') . ' = ' . $ordercode)
            ->where($db->quoteName('o.paid') . ' != 1');

        foreach (array_map('intval', $db->setQuery($query)->loadColumn()) as $ownerId) {
            $orphans = self::forChart($ownerId, $ordercode);

            if ($orphans) {
                return [$ownerId, $orphans];
            }
        }

        return null;
    }

    /**
     * The message for the customer, naming the orphans (e.g. "A5, A9").
     *
     * @param   object[]  $orphans
     */
    public static function message(array $orphans): string
    {
        $labels = array_map(fn ($seat) => $seat->row_name . $seat->seatid, $orphans);

        return Text::sprintf('COM_TICKETSTATION_ORPHAN_SEATS', htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Sends the customer of the session's order back to the seat-picking page when the order
     * leaves orphans; for the site's cart and checkout.
     *
     * The page itself shows why (orphans=1 in the URL), not Joomla's message queue: the cart
     * removes a seat with a background fetch that follows this redirect, and that fetch would
     * use up a queued message before the browser opens the page.
     */
    public static function guard(): void
    {
        $app       = Factory::getApplication();
        $violation = self::violation((int) $app->getSession()->get('ordercode'));

        if (!$violation) {
            return;
        }

        $itemid = TicketstationFunctions::getSiteItemid();
        $app->redirect(Route::_('index.php?option=com_ticketstation&view=seatedevent&cid=' . $violation[0] . '&orphans=1' . ($itemid ? '&Itemid=' . $itemid : ''), false));
    }

    /**
     * Splits a chart's seats into row segments, each ordered along the row.
     *
     * @return  array[]
     */
    private static function segments(array $seats): array
    {
        $rows = [];

        foreach ($seats as $seat) {
            $rows[(string) $seat->row_name][] = $seat;
        }

        $segments = [];

        foreach ($rows as $row) {
            foreach (self::split($row) as $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * The segments of one row: groups of seats that stand next to each other, each sorted along
     * its own direction (left to right, or top to bottom for a vertical row).
     *
     * Two seats are neighbours when the space between their edges is at most the limit: never
     * less than half a seat, never more than a whole seat, and in between one and a half times
     * the usual space between the seats of this row, so wide spacing isn't taken for aisles.
     */
    private static function split(array $row): array
    {
        $n = count($row);

        if ($n < 2) {
            $row[0]->direction = null;

            return [$row];
        }

        $gap = function (object $a, object $b): float {
            $dx = abs(($a->x_pos + $a->width / 2) - ($b->x_pos + $b->width / 2)) - ($a->width + $b->width) / 2;
            $dy = abs(($a->y_pos + $a->height / 2) - ($b->y_pos + $b->height / 2)) - ($a->height + $b->height) / 2;

            return max(0, $dx, $dy);
        };

        ## The usual space: the median of each seat's space to its nearest seat in the row.
        $nearest = [];
        $size    = [];

        for ($i = 0; $i < $n; $i++) {
            $best = INF;

            for ($j = 0; $j < $n; $j++) {
                if ($i !== $j) {
                    $best = min($best, $gap($row[$i], $row[$j]));
                }
            }

            $nearest[] = $best;
            $size[]    = min(max(1, (int) $row[$i]->width), max(1, (int) $row[$i]->height));
        }

        sort($nearest);
        sort($size);
        $usual = $nearest[intdiv($n, 2)];
        $seat  = $size[intdiv($n, 2)];
        $limit = max($seat / 2, min($usual * 1.5 + 1, $seat));

        ## Groups of seats linked through neighbours.
        $group = range(0, $n - 1);
        $find  = function (int $i) use (&$group): int {
            while ($group[$i] !== $i) {
                $i = $group[$i] = $group[$group[$i]];
            }

            return $i;
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($gap($row[$i], $row[$j]) <= $limit) {
                    $group[$find($j)] = $find($i);
                }
            }
        }

        $segments = [];

        for ($i = 0; $i < $n; $i++) {
            $segments[$find($i)][] = $row[$i];
        }

        foreach ($segments as &$segment) {
            $xs       = array_map(fn ($s) => $s->x_pos + $s->width / 2, $segment);
            $ys       = array_map(fn ($s) => $s->y_pos + $s->height / 2, $segment);
            $vertical = max($ys) - min($ys) > max($xs) - min($xs);

            usort($segment, fn ($a, $b) => $vertical
                ? [$a->y_pos, $a->x_pos] <=> [$b->y_pos, $b->x_pos]
                : [$a->x_pos, $a->y_pos] <=> [$b->x_pos, $b->y_pos]);

            foreach ($segment as $seat) {
                $seat->direction = count($segment) > 1 ? $vertical : null;
            }
        }

        return array_values($segments);
    }

    /**
     * Whether the order's seats could be spread over the blocks it already has seats in without
     * leaving an orphan: a block takes none, all, or any number but one fewer than it has, unless the seat
     * that is left has a free seat across from it in a block the order leaves alone (freeOpposite()). Only when that is impossible
     * is an orphan forgiven, so a customer cannot leave one by choosing a seat in a pair of free
     * seats while the other seats chosen could just as well fill a larger block.
     *
     * @param   array[]   $segments
     * @param   object[]  $seats
     */
    private static function avoidable(array $segments, array $seats): bool
    {
        $total   = 0;
        $blocks  = [];
        $touched = [];

        foreach ($segments as $segment) {
            $block = [];

            foreach (array_merge($segment, [null]) as $seat) {
                if ($seat !== null && $seat->state !== self::TAKEN) {
                    $block[] = $seat;
                    $total  += $seat->state === self::MINE ? 1 : 0;
                    continue;
                }

                ## Only blocks the customer chose seats in: the seats are not moved to other parts of the chart.
                if ($block && array_filter($block, fn ($s) => $s->state === self::MINE)) {
                    $blocks[] = $block;

                    foreach ($block as $taken) {
                        $touched[$taken->id] = true;
                    }
                }

                $block = [];
            }
        }

        ## Subset sum over what each block can take.
        $reach = [0 => true];

        foreach ($blocks as $block) {
            $size = count($block);

            ## Taking all but one leaves the seat at one end: fine when that seat has a free seat across.
            $leftOk = $size > 1 && (self::freeOpposite($block[0], $seats, $touched) || self::freeOpposite($block[$size - 1], $seats, $touched));
            $next   = [];

            foreach (array_keys($reach) as $sum) {
                for ($take = 0; $take <= $size; $take++) {
                    if ($take === 0 || $take !== $size - 1 || $leftOk) {
                        $next[$sum + $take] = true;
                    }
                }
            }

            $reach = $next;
        }

        return isset($reach[$total]);
    }

    /**
     * The orphans of one ordered segment that are caused by this order.
     *
     * @return  object[]
     */
    private static function orphansIn(array $segment, bool $avoidable): array
    {
        $orphans = [];
        $n       = count($segment);
        $i       = 0;

        while ($i < $n) {
            if ($segment[$i]->state === self::TAKEN) {
                $i++;
                continue;
            }

            $block = [];

            while ($i < $n && $segment[$i]->state !== self::TAKEN) {
                $block[] = $segment[$i++];
            }

            $mine = count(array_filter($block, fn ($s) => $s->state === self::MINE));

            ## None of this order's seats, or (when the order cannot be placed without an orphan
            ## anywhere on the chart) no placement without one seat left over.
            if ($mine === 0 || (!$avoidable && count($block) - $mine === 1)) {
                continue;
            }

            foreach ($block as $k => $seat) {
                $left  = $block[$k - 1]->state ?? null;
                $right = $block[$k + 1]->state ?? null;

                if ($seat->state === self::FREE && $left !== self::FREE && $right !== self::FREE
                    && ($left === self::MINE || $right === self::MINE)) {
                    $orphans[] = $seat;
                }
            }
        }

        return $orphans;
    }
}
