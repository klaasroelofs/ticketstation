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
 * Ticketstation Clients Model
 * @since 0.0.1
 */
class ClientsModel extends BaseDatabaseModel
{
    private $id;

    function __construct(){

        parent::__construct();

        $app 	= Factory::getApplication();

        ## Get the pagination request variables
        $limit      = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->get('list_limit'), 'int' );
        $limitstart = $app->getUserStateFromRequest( 'products.limitstart', 'limitstart', 0, 'int' );

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
    }

    function getPagination() {

        if (empty($this->_pagination)) {

            $this->_pagination = new Pagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
        }

        return $this->_pagination;
    }

    function getTotal() {

        $this->_total = $this->_getListCount($this->getQuery(), $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    private function getQuery(){

        $app = Factory::getApplication();

        //$filter_order		= $app->getUserStateFromRequest( 'filter_ordering', 'filter_ordering', 'name', 'cmd' ); //TODO: change name > a.name?
        //$filter_order_Dir	= $app->getUserStateFromRequest( 'filter_order_Dir', 'filter_order_Dir', '', 'word' );
        $search     = $app->getUserStateFromRequest('searchbox', 'searchbox', '', 'string');
        $search     = strtolower($search);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('*'));
        $query->from($db->quoteName('#__ticketstation_clients'));

        if ($search)
        {
            $like_filter = ' LIKE ' . $db->quote('%' . str_replace(' ', '%', $search) . '%');

            $where = [
                $db->quoteName('name') . $like_filter,
                $db->quoteName('firstname') . $like_filter,
                $db->quoteName('emailaddress') . $like_filter,
            ];

            $query->where('(' . implode(' OR ', $where) . ')');
        }

        $query->order('clientid ASC');

        return $query;

    }

    function getList() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getQuery(), $this->getState('limitstart'), $this->getState('limit' ));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query 	= $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_clients'));
        $query->where($db->quoteName('clientid') . ' = '. $db->quote((int)$this->id));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;

    }

    function publish($cid = array(), $publish = 1) {

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');
            $query  = $db->getQuery(true);

            $fields = array(
                $db->quoteName('published') . ' = '.(int) $publish
            );

            $conditions = array(
                $db->quoteName('clientid') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_clients'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

            return true;


        }

        return false;
    }

    function store($data) //TODO: Replace deprecated setError / getError
    {

        $table = $this->getTable();

        // Bind the data.
        if (!$table->bind($data)) {
            Factory::getApplication()->enqueueMessage('Bind failed', 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            Factory::getApplication()->enqueueMessage('Check failed', 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            Factory::getApplication()->enqueueMessage('Store failed', 'error');
            //$this->setError($table->getError());
            return false;
        }

        /*// Save the data >> bind/check/store.
        if (!$table->save($data)) {
            Factory::getApplication()->enqueueMessage('Store failed', 'error');
            //$this->setError($table->getError());
            return false;
        }*/

        return true;

    }

    function remove($cid){

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select(array('clientid'));
            $query->from($db->quoteName('#__ticketstation_clients'));
            $query->where( $db->quoteName('clientid') . ' IN ('.$cids.')' );

            $db->setQuery($query);
            $data = $db->loadObjectList();

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('clientid').' IN ('.$cids.')'
            );

            $query->delete($db->quoteName('#__ticketstation_clients'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if($result){
                return true;
            }else{
                return false;
            }

        }

        return false;


    }


    function getOrderList() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_clients'));
        $query->where($db->quoteName('clientid') . ' = '. $db->quote((int)$this->id));

        $db->setQuery($query);
        $uid = $db->loadObject();

        $query = $db->getQuery(true);

        $query->select(array('a.*', 't.ticketname', 'e.eventcode', 'e.eventname', 'SUM(t.ticketprice) AS orderprice', 'COUNT(a.orderid) AS totaltickets', 'r.remarks', 'tt.amount AS transaction_amount'));
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ('.$db->quoteName('t.ticketid').' = '.$db->quoteName('a.ticketid').')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_remarks', 'r') . ' ON ('.$db->quoteName('r.ordercode').' = '.$db->quoteName('a.ordercode').')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_transactions', 'tt') . ' ON (' . $db->quoteName('a.ordercode') . ' = ' . $db->quoteName('tt.orderid') . ')');
        $query->where($db->quoteName('a.userid') . ' = '. $db->quote((int)$uid->clientid));
        $query->group('a.ordercode');
        $query->order('a.orderdate DESC');

        $db->setQuery($query);
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getConfig() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = '. $db->quote(1));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }






}