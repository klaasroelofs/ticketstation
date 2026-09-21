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
 * Ticketstation Statistics Model
 * @since 0.3.1
 */
class StatisticsModel extends BaseDatabaseModel
{
    function __construct()
    {
        parent::__construct();

        $app    = Factory::getApplication();
        $jinput = Factory::getApplication()->getInput();

        $this->id = $jinput->get('id', '0', 'int');
        $this->eventid = $this->getEventid();
    }

    function getEventid()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('eventid')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . " = " . (int) $this->id);

        $db->setQuery($query);

        return $db->loadResult();
    }

    /**
     * Getting the ticketinfo.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getTicket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketname', 't.starting_total_tickets', 'e.eventname'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.ticketid') . " = " . (int) $this->id);

        $db->setQuery($query);

        return $db->loadObject();
    }

    function getSoldticketsbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('COUNT(orderid) AS total_tickets_sold');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 1");

        $db->setQuery($query);

        return $db->loadResult();

    }

    function getSoldticketsbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['ticketid', 'COUNT(orderid) AS total_tickets_sold']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 1");
        $query->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();

    }

    function getScannedperhourticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'DATE(' . $db->quoteName('scandate') . ') AS scandate',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ') AS scanhour',
                'COUNT(' . $db->quoteName('scanned') . ') AS total_scanned'
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('scanned') . ' = 1')
            ->where($db->quoteName('ticketid') . ' = ' . (int) $this->id)
            ->group([
                'DATE(' . $db->quoteName('scandate') . ')',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ')'
            ])
            ->order([
                'DATE(' . $db->quoteName('scandate') . ') ASC',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ') ASC'
            ]);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getScannedperhourevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'DATE(' . $db->quoteName('scandate') . ') AS scandate',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ') AS scanhour',
                'COUNT(' . $db->quoteName('scanned') . ') AS total_scanned'
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('scanned') . ' = 1')
            ->where($db->quoteName('eventid') . ' = ' . (int) $this->eventid)
            ->group([
                'DATE(' . $db->quoteName('scandate') . ')',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ')'
            ])
            ->order([
                'DATE(' . $db->quoteName('scandate') . ') ASC',
                'EXTRACT(HOUR FROM ' . $db->quoteName('scandate') . ') ASC'
            ]);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getScannedperscannerticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['u.name', 'COUNT(o.scanned) AS total_scanned']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('o.scanner') . ' = ' . $db->quoteName('u.id'));
        $query->where($db->quoteName('o.ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('o.scanned') . " = 1");
        $query->group($db->quoteName('o.scanner'));
        $query->order('u.name ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getScannedperscannerevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['u.name', 'COUNT(o.scanned) AS total_scanned']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('o.scanner') . ' = ' . $db->quoteName('u.id'));
        $query->where($db->quoteName('o.eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('o.scanned') . " = 1");
        $query->group($db->quoteName('o.scanner'));
        $query->order('u.name ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getLastorderbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['ordercode']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 1");
        $query->order('orderdate DESC');

        $db->setQuery($query);

        $result =  $db->loadObject();
        $ordercode = $result->ordercode;

        $query = $db->getQuery(true);

        $query->select(['MAX(o.orderdate) as orderdate', 'COUNT(o.orderid) as quantity', 'CONCAT(c.firstname, " ", c.name) as name']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('c.clientid'));
        $query->where($db->quoteName('ordercode') . " = " . (int) $ordercode);

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getLastorderbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['ordercode']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 1");
        $query->order('orderdate DESC');

        $db->setQuery($query);

        $result =  $db->loadObject();
        $ordercode = $result->ordercode;

        $query = $db->getQuery(true);

        $query->select(['MAX(o.orderdate) as orderdate', 'COUNT(o.orderid) as quantity', 'CONCAT(c.firstname, " ", c.name) as name']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('c.clientid'));
        $query->where($db->quoteName('ordercode') . " = " . (int) $ordercode);

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getOrdercountbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('COUNT(DISTINCT ordercode)');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 1");

        $db->setQuery($query);

        return $db->loadResult();

    }

    function getOrdercountbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['ticketid', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 1");
        $query->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();

    }

    function getUnfinishedordersbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 0");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getUnfinishedordersbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 0");
        //$query->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getTicketsbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketname', 't.starting_total_tickets'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.eventid') . " = " . (int) $this->eventid);
            //->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();

    }

    function getPdfnotcreatedbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 1");
        $query->where($db->quoteName('pdfcreated') . " = 0");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getPdfnotcreatedbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 1");
        $query->where($db->quoteName('pdfcreated') . " = 0");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getPdfnotsentbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('paid') . " = 1");
        $query->where($db->quoteName('pdfsent') . " = 0");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getPdfnotsentbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . " = " . (int) $this->eventid);
        $query->where($db->quoteName('paid') . " = 1");
        $query->where($db->quoteName('pdfsent') . " = 0");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getDownloadedticketsbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['COUNT(orderid) as tickets', 'COUNT(DISTINCT ordercode) as orders']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int) $this->id);
        $query->where($db->quoteName('downloaded') . " = 1");

        $db->setQuery($query);

        return $db->loadObject();

    }

    function getClientswithoutorder()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('c.clientid'),
                $db->quoteName('c.firstname'),
                $db->quoteName('c.name'),
                'COUNT(' . $db->quoteName('o.orderid') . ') AS orderqty'
            ])
            ->from($db->quoteName('#__ticketstation_clients', 'c'))
            ->leftJoin($db->quoteName('#__ticketstation_orders', 'o') . ' ON ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('c.clientid'))
            ->where($db->quoteName('o.userid') . ' IS NULL')
            ->group($db->quoteName('c.clientid'));

        $db->setQuery($query);

        return $db->loadObjectList();

    }

    function getClientswithmultipleordersbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                "COUNT(DISTINCT o.ordercode) as orders",
                "COUNT(DISTINCT o.orderid) as tickets",
                "c.firstname",
                "c.name",
                "c.clientid",
                "MAX(o.orderdate) as orderdate"
            ])
            ->from($db->quoteName("#__ticketstation_orders", "o"))
            ->leftJoin($db->quoteName("#__ticketstation_clients", "c") . " ON o.userid = c.clientid")
            ->where("o.paid = 1")
            ->where("o.ticketid = " . (int) $this->id)
            ->group("c.clientid")
            ->having("orders > 1")
            ->order("orders DESC, tickets DESC, c.firstname ASC");

        $db->setQuery($query);

        return $db->loadObjectList();

    }

    function getClientswithmultipleordersbyevent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                "COUNT(DISTINCT o.ordercode) AS orders",
                "COUNT(DISTINCT o.orderid) AS tickets",
                "c.firstname",
                "c.name",
                "c.clientid",
                "MAX(o.orderdate) AS orderdate"
            ])
            ->from($db->quoteName("#__ticketstation_orders", "o"))
            ->leftJoin($db->quoteName("#__ticketstation_clients", "c") . " ON o.userid = c.clientid")
            ->where("o.paid = 1")
            ->where("o.eventid = " . (int) $this->eventid)
            ->group("c.clientid")
            ->having("orders > 1")
            ->order("orders DESC, tickets DESC, c.firstname ASC");

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