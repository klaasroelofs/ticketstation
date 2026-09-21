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
 * Ticketstation Upcoming Model
 * @since 0.2.10
 */
class UpcomingModel extends BaseDatabaseModel {

    function __construct()
    {
        parent::__construct();

        $app    = Factory::getApplication();
        $jinput = Factory::getApplication()->input;

        $limit      = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $jinput->get('limitstart', '0', 'int');
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    /**
     * Setting the pagination which will be used later on.
     *
     * @return Pagination
     *
     * @since 1.0.0
     */
    function getPagination()
    {
        if (empty($this->_pagination))
        {
            jimport('joomla.html.pagination');
            $this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination;
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
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                't.startdate', 't.ticketprice', 't.ticketid', 't.starting_total_tickets',
                'e.eventname', 't.ticketname', 'e.eventdescription', 't.show_seatplans', 'v.venue', 'v.city', 't.eventid',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('t.venue') . ' = ' . $db->quoteName('v.id'))
            ->where($db->quoteName('parent') . " = 0")
            ->where($db->quoteName('t.published') . " = 1")
            ->where($db->quoteName('e.published') . " = 1")
            ->order('t.startdate ASC');

        return $query;
    }

    /**
     * Returning the pagination to the view.
     *
     * @return int
     *
     * @since 1.0.0
     */
    function getTotal()
    {
        if (empty($this->_total))
        {
            $this->_total = $this->_getListCount($this->getListQuery(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_total;
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
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'published',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('published') . " = 1")
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the upcoming events
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getUpcomingevents()
    {
        //set horizon to only show upcoming events that will be published within 1 month from now
        $horizon = date('Y-m-d H:i:s', strtotime("+1 month"));

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'startdate', 'eventdate', 'published',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('published') . " = 0")
            ->where($db->quoteName('automatic_change_state') . " = 1")
            ->where($db->quoteName('startdate') . ' < '. $db->quote($horizon))
            ->where($db->quoteName('closingdate') . ' > '. $db->quote(date('Y-m-d H:i:s')))
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the sold tickets grouped by ticketid
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getSold()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['eventid', 'ticketid', 'COUNT(orderid) AS soldtickets'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the added tickets to the view to show amounts.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getAdded()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['eventid', 'ticketid', 'SUM(starting_total_tickets) AS totals'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getMollie() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Making the query for showing all the clients in list function
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        return $db->loadObject();
    }

}