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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;

/**
 * Ticketstation Venues Model
 * @since 0.0.1
 */
class VenuesModel extends BaseDatabaseModel
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
        $query->from($db->quoteName('#__ticketstation_venues'));

        $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_venues'));

        $db->setQuery($query, $this->getState('limitstart'), $this->getState('limit' ));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_venues'));
        $query->where($db->quoteName('id') . ' = '. $db->quote((int)$this->id));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
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
                $db->quoteName('id') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_venues'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

        }

        return true;
    }

    public function store($data)
    {
        $table = $this->getTable();

        // Bind the data.
        if (!$table->bind($data)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_BIND_FAILED'), 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_CHECK_FAILED'), 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_STORE_FAILED'), 'error');
            //$this->setError($table->getError());
            return false;
        }

        $jinput = Factory::getApplication()->getInput();

        if ($jinput->get('id', '0', 'INT') != 0)
        {
            $this->venue = $jinput->get('id', '0', 'INT');
        }
        else
        {
            $this->venue = $this->_db->insertid();
        }

        return true;

    }

    function getVenueID()
    {
        return $this->venue;
    }

    function getLastEntry()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('MAX(id)');
        $query->from($db->quoteName('#__ticketstation_venues'));

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result;

    }

    function remove($cid = array())
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Make cids safe, against SQL injections
        ArrayHelper::toInteger($cid);

        ## Implode cids for more actions (when more selected)
        $cids = implode( ',', $cid );

        $query = $db->getQuery(true);

        $query->select(array('COUNT(ticketid) AS total_tickets'));
        $query->from($db->quoteName('#__ticketstation_tickets'));
        $query->where($db->quoteName('venue') . ' IN ( '.$cids.' )');

        $db->setQuery($query);
        $data = $db->loadObject();

        if($data->total_tickets > 0)
        {
            Factory::getApplication()->enqueueMessage($data->total_tickets.' '.Text::_('COM_TICKETSTATION_TICKETS_USING_THIS_VENUE'), 'error');
            return false;
        }

        if (count( $cid ))
        {
            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('id') . ' IN ('.$cids.')'
            );

            $query->delete($db->quoteName('#__ticketstation_venues'));
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