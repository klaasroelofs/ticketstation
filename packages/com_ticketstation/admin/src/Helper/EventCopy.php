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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

/**
 * Copies an event as a starting point for a series or a recurring production: the event, all its
 * tickets (child tickets stay under their copied parent), the seat chart of each ticket and the
 * uploaded ticket designs and background images.
 *
 * The copy is unpublished, event and tickets alike, so nothing goes on sale before the dates are
 * adjusted; the automatic publishing is switched off for the same reason. Orders and the waiting
 * list are not copied, and every seat comes over free (a sold seat too); seats blocked without an order
 * stay blocked. Coupons are not touched: a coupon limited to the original event keeps counting
 * for that event only.
 */
class EventCopy
{
    private const FILES = '/components/com_ticketstation/assets/';

    /**
     * @param   int  $eventid  The event to copy.
     *
     * @return  int  The id of the new event.
     *
     * @throws  \RuntimeException  when the event doesn't exist or the copy fails; nothing is
     *                             left behind in the database then.
     */
    public static function copy(int $eventid): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('eventid') . ' = ' . $eventid));
        $event = $db->loadObject();

        if (!$event)
        {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'));
        }

        try
        {
            $db->transactionStart();

            $event->eventname = trim((string) $event->eventname . ' ' . Text::_('COM_TICKETSTATION_COPIED'));
            $event->published = 0;
            // The old publish date has passed: left on, the automatic publishing (Ticketstarter) would
            // publish the copy again at once. Set new dates and switch it on again.
            $event->automatic_change_state = 0;
            unset($event->eventid, $event->created, $event->modified);
            $db->insertObject('#__ticketstation_events', $event, 'eventid');
            $newEvent = (int) $event->eventid;

            $map = self::copyTickets($eventid, $newEvent);

            foreach ($map as $old => $new)
            {
                self::copySettings($old, $new);
            }

            self::copySeats($map);

            $db->transactionCommit();
        }
        catch (\Throwable $e)
        {
            $db->transactionRollback();

            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        // Files last: a failed copy of a file isn't worth undoing the rest, the designs can be
        // uploaded again.
        self::copyFiles($eventid, $newEvent, $map);

        return $newEvent;
    }

    /**
     * Copies the tickets of an event, parents before their child tickets.
     *
     * @return  array<int,int>  old ticket id => new ticket id
     */
    private static function copyTickets(int $eventid, int $newEvent): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('eventid') . ' = ' . $eventid)
            ->order([$db->quoteName('parent') . ' ASC', $db->quoteName('ticketid') . ' ASC']));

        $map = [];

        foreach ($db->loadObjectList() as $ticket)
        {
            $old = (int) $ticket->ticketid;

            $ticket->eventid   = $newEvent;
            $ticket->parent    = (int) $ticket->parent > 0 ? ($map[(int) $ticket->parent] ?? 0) : 0;
            $ticket->published = 0;
            // Same for the tickets: see the event above.
            $ticket->use_auto_publish = 0;
            unset($ticket->ticketid, $ticket->created, $ticket->modified);

            $db->insertObject('#__ticketstation_tickets', $ticket, 'ticketid');

            $map[$old] = (int) $ticket->ticketid;
        }

        return $map;
    }

    /**
     * Copies the seat chart settings (colours, background, shapes) of one ticket.
     */
    private static function copySettings(int $old, int $new): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_seatplansettings'))
            ->where($db->quoteName('ticketid') . ' = ' . $old));
        $settings = $db->loadObject();

        if ($settings)
        {
            unset($settings->id);
            $settings->ticketid = $new;

            $db->insertObject('#__ticketstation_seatplansettings', $settings);
        }
    }

    /**
     * Copies the seats of the copied tickets. A seat belongs to the ticket it is sold as (a child
     * ticket for a section) and has the owning parent ticket as parent, so both are mapped. Seats
     * come over as a free seat, also when they are sold in the original (the chart has to stay
     * complete); a seat that was blocked without an order stays blocked.
     *
     * @param   array<int,int>  $map  old ticket id => new ticket id
     */
    private static function copySeats(array $map): void
    {
        if (!$map)
        {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_seatplancoords'))
            ->whereIn($db->quoteName('ticketid'), array_keys($map)));

        $columns = null;
        $rows    = [];

        $flush = function () use ($db, &$columns, &$rows)
        {
            if ($rows)
            {
                $db->setQuery($db->getQuery(true)
                    ->insert($db->quoteName('#__ticketstation_seatplancoords'))
                    ->columns($db->quoteName($columns))
                    ->values($rows));
                $db->execute();
                $rows = [];
            }
        };

        // All seats are read first: the next queries would otherwise disturb the open cursor.
        foreach ($db->loadAssocList() as $seat)
        {
            unset($seat['id']);
            $seat['ticketid'] = $map[(int) $seat['ticketid']];
            $seat['parent']   = (int) $seat['parent'] > 0 ? ($map[(int) $seat['parent']] ?? 0) : 0;
            $seat['blocked']  = (int) $seat['orderid'] === 0 ? (int) $seat['blocked'] : 0;
            $seat['orderid']  = 0;
            $seat['booked']   = (int) $seat['blocked'] === 1 ? 1 : 0;

            $columns ??= array_keys($seat);
            $rows[]    = implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $db->quote($value), array_values($seat)));

            if (count($rows) >= 200)
            {
                $flush();
            }
        }

        $flush();
    }

    /**
     * Copies the uploaded files: the event's background image and, per ticket, the design (PDF
     * or JPG) and the background image.
     *
     * @param   array<int,int>  $map  old ticket id => new ticket id
     */
    private static function copyFiles(int $eventid, int $newEvent, array $map): void
    {
        $base  = JPATH_ADMINISTRATOR . self::FILES;
        $pairs = [['images/ticketbackgrounds/event' . $eventid . '.jpg', 'images/ticketbackgrounds/event' . $newEvent . '.jpg']];

        foreach ($map as $old => $new)
        {
            $pairs[] = ['etickets/eTicket-' . $old . '.pdf', 'etickets/eTicket-' . $new . '.pdf'];
            $pairs[] = ['etickets/eTicket-' . $old . '.jpg', 'etickets/eTicket-' . $new . '.jpg'];
            $pairs[] = ['images/ticketbackgrounds/ticket' . $old . '.jpg', 'images/ticketbackgrounds/ticket' . $new . '.jpg'];
        }

        foreach ($pairs as [$from, $to])
        {
            if (is_file($base . $from))
            {
                File::copy($base . $from, $base . $to);
            }
        }
    }
}
