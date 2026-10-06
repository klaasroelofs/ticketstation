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
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * Who may see and order tickets on the website.
 *
 * The payment provider's test mode is for trying the shop out, never for customers: while it is on, only
 * logged-in site users (the organisation's own staff) see tickets and can order them. Anonymous visitors get the same answer as for an unpublished ticket, but still
 * see the events whose sale is about to start (the countdown in the upcoming-events list).
 *
 * With online payments switched off (Mollie settings) the website only sells free tickets: an
 * order of nothing never goes to Mollie. Paid tickets are sold through Reservations and the
 * Box Office, and the website says so. Test mode doesn't apply then.
 */
class Shop
{
    /**
     * Whether customers can pay online: the active payment provider is switched on.
     */
    public static function paymentsOn(): bool
    {
        $provider = ProviderRegistry::active();

        return $provider !== null;
    }

    /**
     * Whether the payment provider's test mode is on.
     */
    public static function inTestMode(): bool
    {
        return self::paymentsOn() && ProviderRegistry::active()->isTestMode();
    }

    /**
     * Whether the shop is closed to the current visitor: test mode is on and the
     * visitor isn't logged in.
     */
    public static function isClosed(): bool
    {
        return self::inTestMode() && Factory::getApplication()->getIdentity()->guest;
    }

    /**
     * Whether the website can take an order for the given ticket as far as paying goes:
     * online payments are on, or the ticket is free.
     */
    public static function canPay(int $ticketid): bool
    {
        return self::paymentsOn() || self::highestPrice([$ticketid]) <= 0;
    }

    /**
     * Whether only the box office sells the given ticket: online payments are off and none of
     * the ticket, or with $family its child tickets (variants, price categories and sections),
     * is free. With $all = false: as soon as one of them costs something, as for a seating
     * chart, whose seats are picked on one page.
     */
    public static function boxOfficeOnly(int $ticketid, bool $all = true): bool
    {
        if (self::paymentsOn()) {
            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($all ? 'MIN(' . $db->quoteName('ticketprice') . ')' : 'MAX(' . $db->quoteName('ticketprice') . ')')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where('(' . $db->quoteName('ticketid') . ' = ' . $ticketid . ' OR (' . $db->quoteName('parent') . ' = ' . $ticketid
                . ' AND ' . $db->quoteName('published') . ' = 1))');

        // A ticket with variants sells the variants, not itself.
        if ($all) {
            $variants = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('parent') . ' = ' . $ticketid)
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($variants);

            if ((int) $db->loadResult() > 0) {
                $query->where($db->quoteName('ticketid') . ' != ' . $ticketid);
            }
        }

        $db->setQuery($query);

        return (float) $db->loadResult() > 0;
    }

    /**
     * Whether the current visitor may order the given ticket on the website: the shop is open
     * to them, the ticket and its event are published, as on the ticket page itself, and it
     * can be paid for (see canPay()).
     */
    public static function sells(int $ticketid): bool
    {
        if (self::isClosed() || !self::canPay($ticketid)) {
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

    /**
     * The highest price among the given tickets.
     *
     * @param   int[]  $ticketids
     */
    public static function highestPrice(array $ticketids): float
    {
        $ticketids = array_values(array_unique(array_map('intval', $ticketids)));

        if (!$ticketids) {
            return 0.0;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('ticketprice') . ')')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->whereIn($db->quoteName('ticketid'), $ticketids);

        $db->setQuery($query);

        return (float) $db->loadResult();
    }
}
