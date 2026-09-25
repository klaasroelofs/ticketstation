<?php

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseQuery;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Ticketscanning Model
 * @since 0.3.0
 */
class TicketscanningModel extends BaseDatabaseModel
{
    /**
     * Scanner assignment of the current user (null when the user isn't a scanner).
     *
     * @return object|null
     *
     * @since 2.2.1
     */
    private function getScanner()
    {
        return Scanner::getByUserId((int) $this->getCurrentUser()->id);
    }

    /**
     * Returning the query to the getList function.
     *
     * @param   int[]  $ticketids
     *
     * @return DatabaseQuery
     *
     * @since 1.0.0
     */
    private function getListQuery(array $ticketids)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                't.startdate', 't.ticketprice', 't.ticketid', 't.starting_total_tickets',
                'e.eventname', 'e.eventcode', 't.ticketname', 't.show_seatplans', 'v.venue', 'v.city', 't.eventid',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('t.venue') . ' = ' . $db->quoteName('v.id'))
            ->whereIn($db->quoteName('t.ticketid'), $ticketids)
            ->where($db->quoteName('t.enddate') . ' > '. $db->quote(Date::localNow()))
            ->order('t.startdate ASC');

        return $query;
    }

    /**
     * Returning the assigned tickets which are upcoming.
     *
     * @return array
     *
     * @since 1.0.0
     */
    function getList()
    {
        $scanner = $this->getScanner();

        if (!$scanner || !$scanner->tickets) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getListQuery($scanner->tickets), $this->getState('limitstart'), $this->getState('limit'));

        return $db->loadObjectList();
    }

    /**
     * Getting the assigned events
     *
     * @return array
     *
     * @since 1.0.0
     */
    function getEvents()
    {
        $scanner = $this->getScanner();

        if (!$scanner || !$scanner->events) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'published', 'eventcode',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->whereIn($db->quoteName('eventid'), $scanner->events)
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

}
