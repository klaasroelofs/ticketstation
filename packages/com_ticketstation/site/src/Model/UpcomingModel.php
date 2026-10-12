<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TestData;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketstarter;

/**
 * Ticketstation Upcoming Model
 * @since 0.2.10
 */
class UpcomingModel extends BaseDatabaseModel {

    function __construct()
    {
        parent::__construct();

        $app    = Factory::getApplication();
        $jinput = Factory::getApplication()->getInput();

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
                't.enddate', 't.doors_open', 't.use_auto_publish', 't.publish_date_time', 't.use_sale_stop', 't.sale_stop', 'v.street', 'v.zipcode', 'v.website',
            ])
            // Price range of the published variants (child tickets); NULL for a ticket without them.
            ->select('(SELECT MIN(c.ticketprice) FROM #__ticketstation_tickets AS c WHERE c.parent = t.ticketid AND c.published = 1) AS variant_min_price')
            ->select('(SELECT MAX(c.ticketprice) FROM #__ticketstation_tickets AS c WHERE c.parent = t.ticketid AND c.published = 1) AS variant_max_price')
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
                'e.eventname', 'e.eventid', 'e.published',
            ])
            // Whether any ticket is on sale: an event without one gets a note instead of an empty card.
            ->select('(SELECT COUNT(*) FROM #__ticketstation_tickets AS pt WHERE pt.eventid = e.eventid'
                . ' AND pt.parent = 0 AND pt.published = 1) AS published_tickets')
            ->from($db->quoteName('#__ticketstation_events', 'e'))
            ->where($db->quoteName('e.published') . " = 1")
            ->order('e.eventdate ASC');

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
        // Without the automatic tasks nothing publishes the event at its start, so announcing it
        // would only count down to nothing.
        if (!$this->automationOn())
        {
            return [];
        }

        //set horizon to only show upcoming events that will be published within 1 month from now
        $horizon = Date::localNow('Y-m-d H:i:s', '+1 month');
        $now     = Date::localNow();

        $db = Factory::getContainer()->get('DatabaseDriver');

        // The event's own choice (Not announced / Countdown / Date only); a start that has passed
        // is published by the automatic tasks on this very request, so it is no announcement.
        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'startdate', 'eventdate', 'published', 'presale_display',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('published') . " = 0")
            ->where($db->quoteName('automatic_change_state') . " = 1")
            ->where($db->quoteName('presale_display') . ' > 0')
            ->where($db->quoteName('startdate') . ' > '. $db->quote($now))
            ->where($db->quoteName('startdate') . ' < '. $db->quote($horizon))
            ->where($db->quoteName('closingdate') . ' > '. $db->quote($now))
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Tickets that are announced on the list although they are not on sale yet: unpublished parent
     * tickets of a published event with automatic publishing and an announcement switched on, whose
     * publish date falls within a month and whose end has not passed. Variants (child tickets)
     * are not announced; they are choices within a ticket.
     *
     * @return mixed
     */
    function getUpcomingtickets()
    {
        if (!$this->automationOn())
        {
            return [];
        }

        $now     = Date::localNow();
        $horizon = Date::localNow('Y-m-d H:i:s', '+1 month');

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketname', 't.eventid', 't.publish_date_time', 't.presale_display'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.parent') . ' = 0')
            ->where($db->quoteName('t.published') . ' = 0')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('t.use_auto_publish') . ' = 1')
            ->where($db->quoteName('t.presale_display') . ' > 0')
            ->where($db->quoteName('t.publish_date_time') . ' > ' . $db->quote($now))
            ->where($db->quoteName('t.publish_date_time') . ' < ' . $db->quote($horizon))
            ->where('NOT ' . Ticketstarter::endedSql($db, 't.enddate'))
            ->order('t.publish_date_time ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Whether the automatic tasks (publishing, sales stops, cleaning up) are switched on:
     * Configuration > Orders > automatic removal of unfinished orders.
     */
    private function automationOn(): bool
    {
        static $on = null;

        if ($on === null)
        {
            $on = ((new Config)->get(['remove_unfinished'])->remove_unfinished ?? 0) == 1;
        }

        return $on;
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
            ->where(TestData::condition('test'))
            ->where(Refund::heldSql())
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

}