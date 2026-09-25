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
use stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * Records and retrieves the lifecycle timeline of an order (created, paid, tickets
 * generated/sent, scanned, blacklisted, refunded, ...), shown as the "History" tab
 * on the Box Office order screen.
 */
class History
{
    /**
     * Writes one history entry for an order.
     *
     * @param   string|int   $ordercode  The order this event belongs to.
     * @param   string       $event_type Short machine-readable event key (e.g. 'order_paid').
     * @param   string       $message    Human-readable description of what happened.
     * @param   array|null   $context    Optional extra data (amount, email, status, ...), stored as JSON.
     * @param   string|null  $actor      Optional explicit actor (e.g. 'Ticketcleaner' for an automated
     *                                   removal), overriding the usual logged-in-user/Website detection.
     *                                   Useful when the event fires during an unrelated request (e.g. the
     *                                   ticketcleaner runs on whichever page happens to trigger it) and
     *                                   attributing it to "whoever was browsing" would be misleading.
     *
     * @return  bool
     */
    public static function log($ordercode, $event_type, $message, $context = null, $actor = null)
    {
        if (empty($ordercode))
        {
            return false;
        }

        $entry             = new stdClass();
        $entry->ordercode  = (string) $ordercode;
        $entry->event_type = $event_type;
        $entry->message    = $message;
        $entry->context    = $context ? json_encode($context) : null;
        $entry->actor      = $actor ?? self::getActor();
        $entry->created    = Factory::getDate()->toSql();

        return (bool) Factory::getContainer()->get('DatabaseDriver')
            ->insertObject('#__ticketstation_history', $entry);
    }

    /**
     * Returns all history entries for an order, oldest first.
     *
     * @param   string|int  $ordercode
     *
     * @return  array
     */
    public function getForOrder($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Ordercodes get reused once the highest order is removed, so an old, removed
        // order's history could otherwise resurface under a new order with the same code.
        // Only show entries from this ordercode's most recent "created" onwards.
        $since_query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('created') . ')')
            ->from($db->quoteName('#__ticketstation_history'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('event_type') . ' = ' . $db->quote('order_created'));

        $db->setQuery($since_query);
        $since = $db->loadResult();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_history'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode));

        if ($since)
        {
            $query->where($db->quoteName('created') . ' >= ' . $db->quote($since));
        }

        $query->order($db->quoteName('created') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Finds orders whose most recent lifecycle event is an automatic ticketcleaner removal
     * (event_type 'order_removed_auto') and which no longer have a live row in
     * #__ticketstation_orders (the cleaner does a real delete; only the history keeps the
     * snapshot). A manually removed order never has this event type, so it never resurfaces
     * here - only automatic removals stay visible.
     *
     * If an ordercode was later reused for a new order, the new order's own history (a more
     * recent 'order_created') takes over as its lifecycle, so its old removal never surfaces
     * as a ghost as long as that order (or a later manual removal of it) still stands.
     *
     * @param   array|null  $ordercodes  Restrict the lookup to these ordercodes (e.g. a single
     *                                   order on the detail page). Null checks all of them,
     *                                   for the Box Office list.
     *
     * @return  array  Snapshot data keyed by ordercode: ['created' => ..., 'reason' => ...,
     *                  'rows' => [...]]. Skips entries whose context is missing/unreadable.
     */
    public static function getAutoRemovedGhosts(?array $ordercodes = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT h.* FROM ' . $db->quoteName('#__ticketstation_history', 'h')
            . ' WHERE h.' . $db->quoteName('event_type') . ' = ' . $db->quote('order_removed_auto')
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_orders', 'o')
            . ' WHERE o.' . $db->quoteName('ordercode') . ' = h.' . $db->quoteName('ordercode') . ')'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_history', 'h2')
            . ' WHERE h2.' . $db->quoteName('ordercode') . ' = h.' . $db->quoteName('ordercode')
            . ' AND h2.' . $db->quoteName('created') . ' > h.' . $db->quoteName('created') . ')';

        if ($ordercodes !== null)
        {
            if (empty($ordercodes))
            {
                return [];
            }

            $quoted = array_map(static fn ($code) => $db->quote((string) $code), $ordercodes);
            $sql .= ' AND h.' . $db->quoteName('ordercode') . ' IN (' . implode(',', $quoted) . ')';
        }

        $db->setQuery($sql);

        $ghosts = [];

        foreach ($db->loadObjectList() as $row)
        {
            $context = json_decode((string) $row->context, true);

            if ( ! is_array($context) || empty($context['rows']))
            {
                continue;
            }

            $ghosts[$row->ordercode] = [
                'created' => $row->created,
                'reason'  => $context['reason'] ?? null,
                'rows'    => $context['rows'],
            ];
        }

        return $ghosts;
    }

    /**
     * Determines who triggered the event: the logged-in back-end user when this runs
     * in a Joomla admin request, or 'Website' for guest/front-end/webhook requests.
     */
    private static function getActor()
    {
        $app = Factory::getApplication();

        try
        {
            $identity = $app->getIdentity();

            if ($identity && $identity->id > 0)
            {
                return $identity->name;
            }
        }
        catch (\Throwable $e)
        {
            // No identity available (e.g. CLI/webhook context) — fall through to default.
        }

        return $app->isClient('administrator') ? 'Systeem' : 'Website';
    }
}
