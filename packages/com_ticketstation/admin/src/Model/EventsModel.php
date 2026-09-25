<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Client\ClientHelper;
use Joomla\Filesystem\File;

/**
 * Ticketstation Events Model
 * @since 0.0.1
 */
class EventsModel extends ListModel
{
    function __construct()
    {
        parent::__construct();

        $app    	= Factory::getApplication();

        ## Get the pagination request variables
        $limit      = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
        $limitstart = $app->getUserStateFromRequest( 'products.limitstart', 'limitstart', 0, 'int' );

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
        }

        return $this->_pagination;
    }

    function getTotal()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_events'));

        $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_events'));

        $db->setQuery($query, $this->getState('limitstart'), $this->getState('limit' ));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getSold()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('eventid', 'COUNT(orderid) AS soldtickets'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('paid') . ' = 1');
        $query->group($db->quoteName('eventid'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getPending()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('eventid', 'COUNT(orderid) AS pending_tickets'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('paid') . ' = 3');
        $query->group($db->quoteName('eventid'));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        return $data;
    }

    function getAdded()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('eventid', 'SUM(starting_total_tickets) AS total'));
        $query->from($db->quoteName('#__ticketstation_tickets'));
        $query->group($db->quoteName('eventid'));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        return $data;
    }

    function getUnfinished()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('eventid', 'COUNT(orderid) AS unfinished_orders'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('paid') . ' = 0');
        $query->where($db->quoteName('userid') . ' = 0');
        $query->group($db->quoteName('eventid'));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        return $data;
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_events'));
        $query->where($db->quoteName('eventid') . ' = '. $db->quote((int)$this->id));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = '. $db->quote(1));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function publish($cid = array(), $publish = 1)
    {
        ## Count the cids
        if (count( $cid ))
        {
            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $fields = array(
                $db->quoteName('published') . ' = ' . $db->quote((int) $publish)
            );

            $conditions = array(
                $db->quoteName('eventid') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_events'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

        }

        return true;
    }

    function remove($cid = array())
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        ClientHelper::setCredentialsFromRequest('ftp');

        $path 	= Uri::root() . '/administrator/components/com_ticketstation/tickets/';

        ArrayHelper::toInteger($cid);
        $cids = implode( ',', $cid );

        $query = $db->getQuery(true);

        $query->select(array('orderid', 'ticketid'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . ' IN ('.implode( ',', $cid ).')');

        $db->setQuery( $query );
        $data = $db->loadObjectList();

        $config = $this->getConfig();

        ## Loop the ticketnumbers for deletion
        for ($i = 0, $n = count($data); $i < $n; $i++ )
        {
            $row  = $data[$i];

            if(file_exists( $path . 'eTicket-' . $row->orderid . '.pdf' ))
            {
                File::delete( $path . 'eTicket-' . $row->orderid . '.pdf' );
            }

        }

        $query = $db->getQuery(true);

        $conditions = array(
            $db->quoteName('eventid') . ' IN ('.$cids.')'
        );

        $query->delete($db->quoteName('#__ticketstation_events'));
        $query->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result)
        {
            return false;
        }

        $query = $db->getQuery(true);

        $conditions = array(
            $db->quoteName('eventid') . ' IN ('.$cids.')'
        );

        $query->delete($db->quoteName('#__ticketstation_tickets'));
        $query->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result)
        {
            return false;
        }

        $query = $db->getQuery(true);

        $conditions = array(
            $db->quoteName('eventid') . ' IN ('.$cids.')'
        );

        $query->delete($db->quoteName('#__ticketstation_orders'));
        $query->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result)
        {
            return false;
        }

        $query = $db->getQuery(true);

        $query->select(array('ticketid AS id'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('eventid') . ' IN ('.implode( ',', $cid ).')');
        $query->group('ticketid');

        $db->setQuery( $query );
        $pro_tables = $db->loadObjectList();

        foreach($pro_tables as $ticket)
        {
            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('ticketid') . ' = ' . $ticket->id
            );

            $query->delete($db->quoteName('#__ticketstation_seatplancoords'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result)
            {
                return false;
            }
        }

        return true;
    }

}