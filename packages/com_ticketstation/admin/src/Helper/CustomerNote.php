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

defined('_JEXEC') or die('Restricted access');

/**
 * The note a customer can add to an order in the cart (Configuration > show_remark_field),
 * e.g. special wishes. Kept in #__ticketstation_customer_notes, one row per ordercode, and
 * deliberately separate from the Box Office "Order Reference" (#__ticketstation_remarks),
 * which is short and printed on the tickets. The customer note is only shown in the Box Office.
 */
class CustomerNote
{
    const MAX_LENGTH = 255;

    /**
     * The note of an order, or an empty string when there is none.
     */
    public function get($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('note'))
            ->from($db->quoteName('#__ticketstation_customer_notes'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        return (string) $db->loadResult();
    }

    /**
     * Stores the note of an order, replacing an earlier one. An empty note removes it.
     *
     * @return bool
     */
    public function save($ordercode, $note)
    {
        $ordercode = (int) $ordercode;
        $note      = mb_substr(trim(strip_tags((string) $note)), 0, self::MAX_LENGTH);

        if ($ordercode === 0) {
            return false;
        }

        if ($note === '') {
            return $this->remove($ordercode);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## ordercode is unique, so a second save overwrites the first note
        $db->setQuery(
            'INSERT INTO ' . $db->quoteName('#__ticketstation_customer_notes')
            . ' (' . $db->quoteName('ordercode') . ', ' . $db->quoteName('note') . ')'
            . ' VALUES (' . $ordercode . ', ' . $db->quote($note) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('note') . ' = VALUES(' . $db->quoteName('note') . ')'
        );

        return (bool) $db->execute();
    }

    /**
     * Moves a note to the order's final ordercode when the temporary cart ordercode is replaced
     * (Order::update()). Ordercodes can be reused, e.g. the code of an order the ticketcleaner
     * removed (its note stays for the ghost view in the Box Office), so an older note under the
     * new code is removed first rather than showing up on the new order.
     *
     * @return bool
     */
    public function move($from, $to)
    {
        $from = (int) $from;
        $to   = (int) $to;

        if ($from === $to) {
            return true;
        }

        $this->remove($to);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_customer_notes'))
            ->set($db->quoteName('ordercode') . ' = ' . $to)
            ->where($db->quoteName('ordercode') . ' = ' . $from);

        $db->setQuery($query);

        return (bool) $db->execute();
    }

    /**
     * Removes the note of an order, if there is one.
     *
     * @return bool
     */
    public function remove($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_customer_notes'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        return (bool) $db->execute();
    }
}
