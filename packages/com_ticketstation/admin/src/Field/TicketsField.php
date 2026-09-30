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

/**
 * The tickets of all events, grouped per event (latest event first), a child ticket under its
 * parent. Used to limit a coupon to certain tickets.
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

        // Parents first, each followed by its own child tickets.
        $children = [];

        foreach ($tickets as $ticket)
        {
            if ((int) $ticket->parent > 0)
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

            $label = $ticket->eventname . ' (' . date('d-m-Y', strtotime((string) $ticket->eventdate)) . ')';

            $groups[$label][] = HTMLHelper::_('select.option', (string) $ticket->ticketid, $ticket->ticketname);

            foreach ($children[(int) $ticket->ticketid] ?? [] as $child)
            {
                $groups[$label][] = HTMLHelper::_('select.option', (string) $child->ticketid, $ticket->ticketname . ' - ' . $child->ticketname);
            }
        }

        return $groups;
    }
}
