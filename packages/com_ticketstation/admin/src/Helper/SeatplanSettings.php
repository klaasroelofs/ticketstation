<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\QueryInterface;

/**
 * Resolves the seat plan settings that apply to a seat (#__ticketstation_seatplancoords row).
 *
 * The seat chart, Multi Seat, seat type and size always belong to the chart's owner: the
 * ticket the seat was added to, or - for a seat of a child ticket (Multi Seat = No, one child
 * ticket per section) - that child's parent, stored in seatplancoords.parent. A child ticket
 * may have its own settings row, but only its colours are used, as an override of the
 * parent's; an empty colour, or no row at all, falls back to the parent.
 */
class SeatplanSettings
{
    /**
     * Joins the owner's settings as "ps" and the child override as "cs" onto a seat
     * aliased "c", for raw SQL queries.
     */
    public const JOINS = ' INNER JOIN #__ticketstation_seatplansettings AS ps ON ps.ticketid = IF(c.parent > 0, c.parent, c.ticketid)'
        . ' LEFT JOIN #__ticketstation_seatplansettings AS cs ON cs.ticketid = c.ticketid AND c.parent > 0';

    /**
     * The owner's chart settings plus the effective colours, to be selected alongside JOINS.
     */
    public const COLUMNS = 'ps.multi_seat, ps.type,'
        . " COALESCE(NULLIF(cs.background_color, ''), ps.background_color) AS background_color,"
        . " COALESCE(NULLIF(cs.border_color, ''), ps.border_color) AS border_color,"
        . " COALESCE(NULLIF(cs.font_color, ''), ps.font_color) AS font_color";

    /**
     * Adds JOINS and COLUMNS to a query builder query on seats aliased "c".
     */
    public static function apply(QueryInterface $query): QueryInterface
    {
        return $query
            ->select(self::COLUMNS)
            ->join('INNER', '#__ticketstation_seatplansettings AS ps ON ps.ticketid = IF(c.parent > 0, c.parent, c.ticketid)')
            ->join('LEFT', '#__ticketstation_seatplansettings AS cs ON cs.ticketid = c.ticketid AND c.parent > 0');
    }

    /**
     * One seat with its chart settings and effective colours, or null when the seat (or its
     * owner's settings row) doesn't exist.
     */
    public static function forSeat(int $coordId): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('c.*')
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->where($db->quoteName('c.id') . ' = ' . $coordId);

        $db->setQuery(self::apply($query));

        return $db->loadObject() ?: null;
    }

    /**
     * The child tickets of a parent with their colour overrides (empty when not set).
     */
    public static function getChildColours(int $parentId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketname', 't.ticketprice', 't.published',
                "IFNULL(s.background_color, '') AS background_color",
                "IFNULL(s.border_color, '') AS border_color",
                "IFNULL(s.font_color, '') AS font_color"])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplansettings', 's') . ' ON ' . $db->quoteName('s.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('t.parent') . ' = ' . $parentId)
            ->order($db->quoteName('t.ticketprice') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Stores the colour overrides posted from the parent's settings screen as
     * [childTicketId => [background_color, border_color, font_color]]. Only real children of
     * $parentId are touched; a child with all three colours empty loses its row.
     */
    public static function saveChildColours(int $parentId, array $input): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach (self::getChildColours($parentId) as $child) {
            $posted = $input[$child->ticketid] ?? null;

            if (!is_array($posted)) {
                continue;
            }

            $colours = [];

            foreach (['background_color', 'border_color', 'font_color'] as $field) {
                $value           = ltrim(trim((string) ($posted[$field] ?? '')), '#');
                $colours[$field] = preg_match('/^[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : '';
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ticketstation_seatplansettings'))
                ->where($db->quoteName('ticketid') . ' = ' . (int) $child->ticketid);
            $db->setQuery($query)->execute();

            if (implode('', $colours) === '') {
                continue;
            }

            $row = (object) array_merge(['ticketid' => (int) $child->ticketid], $colours);
            $db->insertObject('#__ticketstation_seatplansettings', $row);
        }
    }
}
