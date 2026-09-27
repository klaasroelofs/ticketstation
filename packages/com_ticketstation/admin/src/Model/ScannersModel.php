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
        $limitstart    = $app->getInput()->get('limitstart', 0, 'int');

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

    /**
     * Tickets with Scanning Allowed in date order, each child ticket directly below its parent
     * (->child = 1). A child ticket whose parent has scanning switched off keeps its own place
     * and carries the parent's name in ->parentname.
     *
     * @return  array
     */
    function getTickets()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                't.ticketname', 't.ticketid', 't.ticketcode', 't.parent', 'p.ticketname AS parentname',
                'e.eventname', 'e.eventid', 'e.eventcode',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('t.parent') . ' = ' . $db->quoteName('p.ticketid'))
            ->where($db->quoteName('t.scans_on') . ' = 1')
            ->order('t.startdate ASC, t.ticketid ASC');

        $db->setQuery($query);
        $tickets = $db->loadObjectList();

        $listed   = [];
        $children = [];

        foreach ($tickets as $ticket)
        {
            if ((int) $ticket->parent === 0)
            {
                $listed[(int) $ticket->ticketid] = true;
            }
        }

        foreach ($tickets as $ticket)
        {
            if (isset($listed[(int) $ticket->parent]))
            {
                $children[(int) $ticket->parent][] = $ticket;
            }
        }

        $ordered = [];

        foreach ($tickets as $ticket)
        {
            if (isset($listed[(int) $ticket->parent]))
            {
                continue;
            }

            $ticket->child = 0;
            $ordered[]     = $ticket;

            foreach ($children[(int) $ticket->ticketid] ?? [] as $child)
            {
                $child->child = 1;
                $ordered[]    = $child;
            }
        }

        return $ordered;
    }

    /**
     * Users that can be linked to this scanner. Users of other scanners are left out, because the
     * browser scanner only ever uses a user's first scanner; this scanner's own user always stays.
     *
     * @return  array
     */
    function getUsers()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $scannerUsers = function ($operator) use ($db) {
            return $db->getQuery(true)
                ->select($db->quoteName('userid'))
                ->from($db->quoteName('#__ticketstation_scannermap'))
                ->where($db->quoteName('id') . ' ' . $operator . ' ' . (int) $this->id)
                ->where($db->quoteName('userid') . ' IS NOT NULL');
        };

        $query = $db->getQuery(true)
            ->select([
                'id', 'CONCAT(name, " (" , id, ")") AS name',
            ])
            ->from($db->quoteName('#__users'))
            ->where('(' . $db->quoteName('id') . ' NOT IN (' . $scannerUsers('!=') . ')'
                . ' OR ' . $db->quoteName('id') . ' IN (' . $scannerUsers('=') . '))');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Whether the user is already linked to a scanner other than the one with this id.
     *
     * @return  bool
     */
    function isUserTaken(int $userid, int $id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_scannermap'))
            ->where($db->quoteName('userid') . ' = ' . $userid)
            ->where($db->quoteName('id') . ' != ' . $id);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
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

        // Generate API key for new scanners if not already set
        $jinput = Factory::getApplication()->getInput();
        $id = $jinput->get('id', '0', 'INT');

        if ($id == 0 && (empty($row->apikey) || $row->apikey === ''))
        {
            // Generate a random 32-byte hex key for new scanner
            $row->apikey = bin2hex(random_bytes(32));
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

        if ($id != 0)
        {
            $this->scanner = $id;
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