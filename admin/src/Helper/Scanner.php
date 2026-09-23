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

    public static function mayScanTicket(object $scanner, int $ticketid, int $eventid = 0): bool
    {
        return \in_array($ticketid, $scanner->tickets, true)
            || ($eventid > 0 && self::mayScanEvent($scanner, $eventid));
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
