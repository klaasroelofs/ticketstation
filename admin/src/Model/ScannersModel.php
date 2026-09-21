<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;

/**
 * Ticketstation Scanners Model
 * @since 0.0.1
 */
class ScannersModel extends BaseDatabaseModel
{
    private $id;
    private $_pagination;
    private $scanner;

    function __construct()
    {
        parent::__construct();

        $app    = Factory::getApplication();

        ## Get the pagination request variables
        $limit         = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
        $limitstart    = $app->input->get('limitstart', 0, 'int');

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
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
        $query->from($db->quoteName('#__ticketstation_scannermap'));

        return $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['s.*', 'u.name']);
        $query->from($db->quoteName('#__ticketstation_scannermap', 's'));
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('s.userid') . ' = ' . $db->quoteName('u.id') . ')');

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['s.*', 'u.name']);
        $query->from($db->quoteName('#__ticketstation_scannermap', 's'));
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('s.userid') . ' = ' . $db->quoteName('u.id') . ')');
        $query->where($db->quoteName('s.id') . ' = '. $db->quote((int) $this->id));

        $db->setQuery($query);
        return $db->loadObject();
    }

    function getEvents()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'eventname', 'eventid', 'eventcode',
            ])
            ->from($db->quoteName('#__ticketstation_events'))
            ->order('eventdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getTickets()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                't.ticketname', 't.ticketid', 't.ticketcode', 'e.eventname', 'e.eventid', 'e.eventcode',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.scans_on') . ' = 1')
            ->order('t.startdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getUsers()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'id', 'CONCAT(name, " (" , id, ")") AS name',
            ])
            ->from($db->quoteName('#__users'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function publish($cid = array(), $publish = 1)
    {
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
                $db->quoteName('id') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_scannermap'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result)
            {
                return false;
            }

        }

        return true;
    }

    function store($data)
    {
        $row = $this->getTable();

        ## Bind the form Field to the web link table
        if (!$row->bind($data))
        {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        ## Make sure the web link table is valid
        if (!$row->check())
        {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        ## Store the web link table to the database
        if (!$row->store())
        {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        $jinput = Factory::getApplication()->getInput();

        if ($jinput->get('id', '0', 'INT') != 0)
        {
            $this->scanner = $jinput->get('id', '0', 'INT');
        }
        else
        {
            $this->scanner = $this->_db->insertid();
        }

        return true;

    }

    function getScannerID()
    {
        return $this->scanner;
    }

    function remove($cid = array())
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Make cids safe, against SQL injections
        ArrayHelper::toInteger($cid);

        ## Implode cids for more actions (when more selected)
        $cids = implode( ',', $cid );


        if (count( $cid ))
        {

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('id') . ' IN ('.$cids.')'
            );

            $query->delete($db->quoteName('#__ticketstation_scannermap'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result)
            {
                return false;
            }

            return true;

        }

    }

}