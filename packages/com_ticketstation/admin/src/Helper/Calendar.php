<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * "Add to calendar": an iCalendar (.ics) file with one VEVENT per event date in an order, offered
 * on the payment result page and attached to the ticket mail.
 *
 * Ticket dates are stored in the site's local time (Global Configuration > Website Time Zone),
 * so they are converted to UTC here; calendar apps then show them in the customer's own zone.
 */
class Calendar
{
    /**
     * The events of an order: one entry per event and start date, with the tickets for it.
     *
     * @param   int  $ordercode
     *
     * @return  object[]  eventid, eventname, startdate, enddate, location, tickets (name => quantity)
     */
    public static function events(int $ordercode): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // A child ticket's name includes its parent's, and it takes its parent's venue when it
        // has none of its own.
        $query = $db->getQuery(true)
            ->select([
                'a.eventid', 'e.eventname', 't.startdate', 't.enddate',
                "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname",
                'v.venue', 'v.street', 'v.zipcode', 'v.city',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = IF(' . $db->quoteName('t.venue') . ' > 0, ' . $db->quoteName('t.venue') . ', ' . $db->quoteName('p.venue') . ')')
            ->where($db->quoteName('a.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->order([$db->quoteName('t.startdate'), $db->quoteName('a.orderid')]);

        $db->setQuery($query);

        $events = [];

        foreach ($db->loadObjectList() as $row) {
            if (!$row->startdate || str_starts_with((string) $row->startdate, '0000-00-00')) {
                continue;
            }

            $key = $row->eventid . '|' . $row->startdate;

            if (!isset($events[$key])) {
                $address = trim(trim((string) $row->street) . ', ' . trim(trim((string) $row->zipcode) . ' ' . trim((string) $row->city)), ', ');

                $events[$key] = (object) [
                    'eventid'   => (int) $row->eventid,
                    'eventname' => (string) $row->eventname,
                    'startdate' => $row->startdate,
                    'enddate'   => $row->enddate,
                    'location'  => implode(', ', array_filter([trim((string) $row->venue), $address])),
                    'tickets'   => [],
                ];
            }

            $events[$key]->tickets[$row->ticketname] = ($events[$key]->tickets[$row->ticketname] ?? 0) + 1;
        }

        return array_values($events);
    }

    /**
     * The .ics file for an order, or an empty string when it has no dated events.
     *
     * @param   int  $ordercode
     *
     * @return  string
     */
    public static function ics(int $ordercode): string
    {
        $events = self::events($ordercode);

        if (!$events) {
            return '';
        }

        $host  = Uri::getInstance(Uri::root())->getHost() ?: 'ticketstation';
        $stamp = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ticketstation//Ticketstation//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $event) {
            $start = self::utc($event->startdate);
            $end   = self::utc($event->enddate);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:ticketstation-' . $ordercode . '-' . $event->eventid . '-' . $start . '@' . $host;
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART:' . $start;

            // Only an end that comes after the start
            if ($end && $end > $start) {
                $lines[] = 'DTEND:' . $end;
            }

            $lines[] = 'SUMMARY:' . self::escape($event->eventname);

            if ($event->location !== '') {
                $lines[] = 'LOCATION:' . self::escape($event->location);
            }

            $tickets = [];

            foreach ($event->tickets as $name => $quantity) {
                $tickets[] = $quantity . ' × ' . $name;
            }

            $lines[] = 'DESCRIPTION:' . self::escape(implode("\n", $tickets));
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /**
     * A date as stored (site-local time) in iCalendar UTC notation, or '' for an empty date.
     */
    private static function utc($localDate): string
    {
        if (!$localDate || str_starts_with((string) $localDate, '0000-00-00')) {
            return '';
        }

        $offset = Factory::getApplication()->get('offset') ?: 'UTC';

        try {
            $date = new \DateTime((string) $localDate, new \DateTimeZone($offset));
        } catch (\Exception $e) {
            return '';
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Escapes a TEXT value (RFC 5545 3.3.11).
     */
    private static function escape(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');

        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /**
     * Folds a content line at 75 octets without splitting a UTF-8 character (RFC 5545 3.1).
     */
    private static function fold(string $line): string
    {
        $folded = '';
        $length = 0;

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $bytes = strlen($char);

            if ($length + $bytes > 75) {
                $folded .= "\r\n ";
                $length  = 1;
            }

            $folded .= $char;
            $length += $bytes;
        }

        return $folded;
    }
}
