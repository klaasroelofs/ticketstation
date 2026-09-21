<?php

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;

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
    function __construct()
    {
        parent::__construct();


    }

    /**
     * Returning the query to the getList function.
     *
     * @return DatabaseQuery
     *
     * @since 1.0.0
     */
    private function getListQuery()
    {
        $user 	= $this->getCurrentUser();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['tickets'])
            ->from($db->quoteName('#__ticketstation_scannermap'))
            ->where($db->quoteName('userid') . ' = '. $db->quote($user->id));

        $db->setQuery($query);

        if (count(json_decode($db->loadResult())) > 0) {
            $approved_for = json_decode($db->loadResult());
        } else {
            $approved_for = array(0);
        }

        $query = $db->getQuery(true)
            ->select([
                't.startdate', 't.ticketprice', 't.ticketid', 't.starting_total_tickets',
                'e.eventname', 'e.eventcode', 't.ticketname', 't.show_seatplans', 'v.venue', 'v.city', 't.eventid',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('t.venue') . ' = ' . $db->quoteName('v.id'))
            ->where($db->quoteName('t.ticketid') . ' IN (' . implode(",",$approved_for) . ')')
            ->where($db->quoteName('t.enddate') . ' > '. $db->quote(date("Y-m-d H:i:s")))
            ->order('t.startdate ASC');

        return $query;
    }



    /**
     * Returning all ticket which are published and upcoming.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getListQuery(), $this->getState('limitstart'), $this->getState('limit'));

        return $db->loadObjectList();
    }

    /**
     * Getting the published events
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getEvents()
    {
        $user 	= $this->getCurrentUser();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['events'])
            ->from($db->quoteName('#__ticketstation_scannermap'))
            ->where($db->quoteName('userid') . ' = '. $db->quote($user->id));

        $db->setQuery($query);

        if (count(json_decode($db->loadResult())) > 0) {
            $approved_for = json_decode($db->loadResult());
        } else {
            $approved_for = array(0);
        }

        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'published', 'eventcode',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('eventid') . ' IN (' . implode(',', $approved_for) . ')')
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

}