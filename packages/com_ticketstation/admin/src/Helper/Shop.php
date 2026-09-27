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
 * Who may see and order tickets on the website.
 *
 * Mollie's test mode and bypass mode are for trying the shop out, never for customers: while
 * either is on, only logged-in site users (the organisation's own staff) see tickets and can
 * order them. Anonymous visitors get the same answer as for an unpublished ticket, but still
 * see the events whose sale is about to start (the countdown in the upcoming-events list).
 */
class Shop
{
    private static ?object $mollie = null;

    /**
     * Whether Mollie's test mode or bypass mode is on.
     */
    public static function inTestMode(): bool
    {
        $mollie = self::getMollie();

        return $mollie->test_mode == '1' || $mollie->bypass_mode == '1';
    }

    /**
     * Whether the shop is closed to the current visitor: test or bypass mode is on and the
     * visitor isn't logged in.
     */
    public static function isClosed(): bool
    {
        return self::inTestMode() && Factory::getApplication()->getIdentity()->guest;
    }

    /**
     * Whether the current visitor may order the given ticket on the website: the shop is open
     * to them and the ticket and its event are published, as on the ticket page itself.
     */
    public static function sells(int $ticketid): bool
    {
        if (self::isClosed()) {
            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->where($db->quoteName('t.ticketid') . ' = ' . $ticketid)
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1');

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    private static function getMollie(): object
    {
        if (self::$mollie === null) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName(['test_mode', 'bypass_mode']))
                ->from($db->quoteName('#__ticketstation_mollie'))
                ->where($db->quoteName('configid') . ' = 1');

            $db->setQuery($query);

            self::$mollie = $db->loadObject() ?: (object) ['test_mode' => '0', 'bypass_mode' => '0'];
        }

        return self::$mollie;
    }
}
