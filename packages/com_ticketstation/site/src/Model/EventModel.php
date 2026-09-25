<?php

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Seatedevent Model
 * @since 0.2.10
 */
class EventModel extends BaseDatabaseModel
{

    function __construct()
    {
        parent::__construct();

        $jinput   = Factory::getApplication()->getInput();
        $this->id = $jinput->get('id', '0', 'int');
    }

    /**
     * Getting all the events and their tickets based on the given event.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getItems()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 'b.eventname', 'b.eventdate', 'b.closingdate', 'v.*', 'v.id AS vid', 'a.published AS ticketpublished', 'b.published AS eventpublished'])
            ->from($db->quoteName('#__ticketstation_tickets', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'b') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('b.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('a.venue') . ' = ' . $db->quoteName('v.id'))
            ->where($db->quoteName('a.published') . " = 1")
            ->where($db->quoteName('b.published') . " = 1")
            ->where($db->quoteName('a.ticketid') . " = " . (int) $this->id);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting all child tickets for a specific event.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getChilds()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 'a.published AS ticketpublished', 'b.published AS eventpublished'])
            ->from($db->quoteName('#__ticketstation_tickets', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'b') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('b.eventid'))
            ->where($db->quoteName('a.parent') . " = " . (int) $this->id)
            ->where($db->quoteName('a.published') . " = 1")
            ->where($db->quoteName('b.published') . " = 1")
            ->order('a.ticketcode ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Returning the configuration
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getConfig()
    {
        return (new Config)->get();
    }

    function getSoldtickets()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $config = $this->getConfig();

        $query = $db->getQuery(true);

        $query->select('COUNT(orderid) AS total_tickets_sold');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);

        $db->setQuery($query);
        $data = $db->loadResult();

        return $data;
    }
}