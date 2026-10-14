<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

/**
 * The parent tickets of all events, grouped per event (latest event first). Used to limit a coupon
 * to certain tickets: a parent ticket brings its child tickets (see Coupon::expandTickets()), so
 * those are not offered separately. A child ticket that an earlier version of the form let the
 * coupon be limited to stays in the list, so editing the coupon doesn't drop it unseen.
 */
class TicketsField extends GroupedlistField
{
    protected $type = 'Tickets';

    protected function getGroups()
    {
        $groups = parent::getGroups();
        $db     = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.parent', 't.ticketname', 'e.eventid', 'e.eventname', 'e.eventdate'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->order([$db->quoteName('e.eventdate') . ' DESC', $db->quoteName('e.eventid') . ' DESC', $db->quoteName('t.ticketid') . ' ASC']);

        $db->setQuery($query);
        $tickets = $db->loadObjectList();

        // Only the child tickets this coupon is already limited to are listed, under their parent.
        $selected = is_array($this->value) ? $this->value : explode(',', (string) $this->value);
        $selected = array_map('intval', $selected);
        $children = [];

        foreach ($tickets as $ticket)
        {
            if ((int) $ticket->parent > 0 && in_array((int) $ticket->ticketid, $selected, true))
            {
                $children[(int) $ticket->parent][] = $ticket;
            }
        }

        foreach ($tickets as $ticket)
        {
            if ((int) $ticket->parent > 0)
            {
                continue;
            }

            $label = $ticket->eventname . ' (' . Date::display($ticket->eventdate, 'd-m-Y') . ')';

            $groups[$label][] = HTMLHelper::_('select.option', (string) $ticket->ticketid, $ticket->ticketname);

            foreach ($children[(int) $ticket->ticketid] ?? [] as $child)
            {
                $groups[$label][] = HTMLHelper::_('select.option', (string) $child->ticketid, $ticket->ticketname . ' - ' . $child->ticketname);
            }
        }

        return $groups;
    }
}
