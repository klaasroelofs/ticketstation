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
use Joomla\Database\QueryInterface;

/**
 * Resolves the seat plan settings that apply to a seat (#__ticketstation_seatplancoords row).
 *
 * A seat chart belongs to a parent ticket (the owner). Each seat is one of two kinds:
 * - a free seat (seatplancoords.ticketid = owner, parent = 0): the customer picks a price
 *   category, see priceCategories(); without categories it sells the owner itself;
 * - a section seat (ticketid = a child ticket, parent = owner): it always sells that child
 *   ticket, at its fixed price.
 * A child ticket with seats of its own is a section; a published child without seats is a
 * price category. Nothing else decides this.
 *
 * The seat type and size always come from the owner's settings row. A child ticket may have
 * its own settings row, but only its colours are used, as an override of the owner's for its
 * section seats; an empty colour, or no row at all, falls back to the owner.
 */
class SeatplanSettings
{
    /**
     * The settings row a seat uses: the owner's ("ps") and the section's colour override
     * ("cs"). A ticket should have one settings row, but older data can hold more; joining
     * them all would return every seat once per row (seats drawn twice on the chart, chosen
     * seats listed twice, double totals), so each join takes the ticket's first row only.
     */
    private const OWNER_ROW = 'ps.id = (SELECT MIN(s1.id) FROM #__ticketstation_seatplansettings AS s1 WHERE s1.ticketid = IF(c.parent > 0, c.parent, c.ticketid))';

    private const SECTION_ROW = 'c.parent > 0 AND cs.id = (SELECT MIN(s2.id) FROM #__ticketstation_seatplansettings AS s2 WHERE s2.ticketid = c.ticketid)';

    /**
     * Joins the owner's settings as "ps" and the child override as "cs" onto a seat
     * aliased "c", for raw SQL queries.
     */
    public const JOINS = ' INNER JOIN #__ticketstation_seatplansettings AS ps ON ' . self::OWNER_ROW
        . ' LEFT JOIN #__ticketstation_seatplansettings AS cs ON ' . self::SECTION_ROW;

    /**
     * The owner's chart settings plus the effective colours, to be selected alongside JOINS.
     */
    public const COLUMNS = 'ps.type,'
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
            ->join('INNER', '#__ticketstation_seatplansettings AS ps ON ' . self::OWNER_ROW)
            ->join('LEFT', '#__ticketstation_seatplansettings AS cs ON ' . self::SECTION_ROW);
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

    /**
     * The chart owner of a seat: its parent for a section seat, otherwise its own ticket.
     */
    public static function owner(object $seat): int
    {
        return (int) $seat->parent > 0 ? (int) $seat->parent : (int) $seat->ticketid;
    }

    /**
     * The price categories a free seat of this chart can be sold in: the published child
     * tickets of the owner that have no seats of their own, most expensive first. Empty when
     * there are none, in which case a free seat sells the owner ticket itself.
     */
    public static function priceCategories(int $ownerId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $sections = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->where($db->quoteName('c.ticketid') . ' = ' . $db->quoteName('t.ticketid'));

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketname', 't.ticketprice'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->where($db->quoteName('t.parent') . ' = ' . $ownerId)
            ->where($db->quoteName('t.published') . ' = 1')
            ->where('NOT EXISTS (' . $sections . ')')
            ->order($db->quoteName('t.ticketprice') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * The ticket a seat sells when it is picked: the section's child ticket for a section
     * seat; for a free seat the most expensive price category, or the owner itself when the
     * chart has none. With $categoryId (a price category chosen by the customer or the box
     * office) that category, but only for a free seat and only when it is a valid category.
     *
     * @return  int  0 when $categoryId isn't allowed for this seat.
     */
    public static function ticketForSeat(object $seat, int $categoryId = 0): int
    {
        if ((int) $seat->parent > 0) {
            return $categoryId === 0 || $categoryId === (int) $seat->ticketid ? (int) $seat->ticketid : 0;
        }

        $categories = self::priceCategories((int) $seat->ticketid);

        if (!$categories) {
            return $categoryId === 0 || $categoryId === (int) $seat->ticketid ? (int) $seat->ticketid : 0;
        }

        if ($categoryId === 0) {
            return (int) $categories[0]->ticketid;
        }

        foreach ($categories as $category) {
            if ((int) $category->ticketid === $categoryId) {
                return $categoryId;
            }
        }

        return 0;
    }
}
