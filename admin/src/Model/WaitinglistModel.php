<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * Ticketstation Waitinglist Model
 * @since 2.0.10
 */
class WaitinglistModel extends BaseDatabaseModel
{
    function __construct()
    {
        parent::__construct();

        $app = Factory::getApplication();

        ## Get the pagination request variables
        $limit      = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest('waitinglist.limitstart', 'limitstart', 0, 'int');

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination;
    }

    /**
     * Base query shared by getList()/getTotal(): every unconfirmed or confirmed-but-not-yet
     * promoted entry, with event/ticket names and client details (client is only known once
     * the customer has bound their details at checkout - see CheckoutModel::itemsupdate()).
     * Already-promoted (processed = 1) entries are no longer "waiting", they're real orders
     * in the Box Office, so they're excluded here.
     */
    private function getBaseQuery($db)
    {
        return $db->getQuery(true)
            ->select(['w.*', 'e.eventname', 't.ticketname', 'c.name AS client_name', 'c.firstname AS client_firstname', 'c.emailaddress AS client_email'])
            ->from($db->quoteName('#__ticketstation_waitinglist', 'w'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('w.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('w.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('w.userid'))
            ->where($db->quoteName('w.processed') . ' = 0');
    }

    function getTotal()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $this->getBaseQuery($db);

        $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $this->getBaseQuery($db)->order($db->quoteName('w.date_added') . ' ASC');

        $db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));

        return $db->loadObjectList();
    }

    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Confirms every waiting-list entry sharing an ordercode with one of the selected rows -
     * a signup is usually several rows (one per ticket unit), and confirming "half" of a
     * customer's own request wouldn't make sense. Delegates to the same WaitingList::confirm()
     * the customer's own email link uses, so behaviour stays identical either way.
     */
    function confirm($cid)
    {
        if ( ! count($cid))
        {
            return false;
        }

        ArrayHelper::toInteger($cid);
        $cids = implode(',', $cid);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('ordercode'))
            ->from($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('id') . ' IN (' . $cids . ')');

        $db->setQuery($query);
        $ordercodes = $db->loadColumn();

        $waitinglist = new WaitingList();

        foreach ($ordercodes as $ordercode)
        {
            if ( ! $waitinglist->confirm($ordercode))
            {
                return false;
            }
        }

        return true;
    }

    function remove($cid)
    {
        if ( ! count($cid))
        {
            return false;
        }

        ArrayHelper::toInteger($cid);
        $cids = implode(',', $cid);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__ticketstation_waitinglist'));
        $query->where($db->quoteName('id') . ' IN (' . $cids . ')');

        $db->setQuery($query);

        return (bool) $db->execute();
    }
}
