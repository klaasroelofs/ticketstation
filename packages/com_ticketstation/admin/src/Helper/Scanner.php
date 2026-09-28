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
use Joomla\Database\ParameterType;

defined('_JEXEC') or die('Restricted access');

/**
 * Lookup of scanner assignments (#__ticketstation_scannermap).
 *
 * A scanner row links a Joomla user (browser scanning) and/or an API key
 * (scanning hardware) to the events and tickets it may scan. The events and
 * tickets columns are JSON arrays of ids; they are always returned here as
 * clean integer lists so callers never have to decode or sanitise them.
 */
class Scanner
{
    /**
     * Scanner assignment of a Joomla user, or null when the user isn't a scanner.
     *
     * @param   int  $userid
     *
     * @return  object|null  Row with ->events and ->tickets as int[]
     */
    public static function getByUserId(int $userid): ?object
    {
        if ($userid <= 0) {
            return null;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_scannermap'))
            ->where($db->quoteName('userid') . ' = :userid')
            ->bind(':userid', $userid, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query, 0, 1);

        return self::normalise($db->loadObject());
    }

    /**
     * Scanner assignment belonging to an API key (constant-time compared),
     * or null when the key is empty or unknown.
     *
     * @param   string  $key
     *
     * @return  object|null  Row with ->events and ->tickets as int[]
     */
    public static function getByApiKey(string $key): ?object
    {
        if ($key === '') {
            return null;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_scannermap'))
            ->where($db->quoteName('apikey') . ' != ' . $db->quote(''));

        $db->setQuery($query);

        // Compare every key so the response time doesn't reveal a partial match.
        $found = null;

        foreach ($db->loadObjectList() as $row) {
            if (hash_equals((string) $row->apikey, $key) && $found === null) {
                $found = $row;
            }
        }

        return self::normalise($found);
    }

    /**
     * Whether the scanner may scan the given event (or the given ticket, or any
     * ticket of an assigned event).
     */
    public static function mayScanEvent(object $scanner, int $eventid): bool
    {
        return \in_array($eventid, $scanner->events, true);
    }

    /**
     * An assigned parent ticket includes its child tickets: an order carries the ticket actually
     * bought, which for a parent with child tickets is mostly one of the children.
     */
    public static function mayScanTicket(object $scanner, int $ticketid, int $eventid = 0): bool
    {
        return \in_array($ticketid, $scanner->tickets, true)
            || \in_array(self::parentOf($ticketid), $scanner->tickets, true)
            || ($eventid > 0 && self::mayScanEvent($scanner, $eventid));
    }

    /**
     * The ticket followed by its child tickets: everything that scanning for this ticket accepts
     * and counts.
     *
     * @return  int[]
     */
    public static function ticketGroup(int $ticketid): array
    {
        if ($ticketid <= 0) {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('ticketid'))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('parent') . ' = :ticketid')
            ->bind(':ticketid', $ticketid, ParameterType::INTEGER);

        $db->setQuery($query);

        return array_merge([$ticketid], array_map('intval', $db->loadColumn()));
    }

    /**
     * Parent of a child ticket, 0 for a ticket without parent.
     */
    private static function parentOf(int $ticketid): int
    {
        if ($ticketid <= 0) {
            return 0;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('parent'))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = :ticketid')
            ->bind(':ticketid', $ticketid, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Ticket name, event id and event name of a ticket, or null when the ticket doesn't exist.
     * Used to tell the door staff what a refused ticket is for.
     */
    public static function ticketInfo(int $ticketid): ?object
    {
        if ($ticketid <= 0) {
            return null;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['t.ticketid', 't.eventid', 't.ticketname', 'e.eventname']))
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e'), $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->where($db->quoteName('t.ticketid') . ' = :ticketid')
            ->bind(':ticketid', $ticketid, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Whether the ticket has Scanning Allowed switched on. With it off, the ticket is refused
     * at the door, also by a scanner that has the whole event.
     */
    public static function scanningAllowed(int $ticketid): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('scans_on'))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = :ticketid')
            ->bind(':ticketid', $ticketid, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }

    /**
     * Decodes a JSON id list column into a list of positive integers.
     */
    public static function idList(?string $json): array
    {
        $ids = json_decode((string) $json, true);

        if (!\is_array($ids)) {
            return [];
        }

        $ids = array_map('intval', array_filter($ids, 'is_scalar'));

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    private static function normalise(?object $row): ?object
    {
        if (!$row) {
            return null;
        }

        $row->userid       = (int) $row->userid;
        $row->manual_entry = (int) $row->manual_entry;
        $row->events       = self::idList($row->events);
        $row->tickets      = self::idList($row->tickets);

        return $row;
    }
}
