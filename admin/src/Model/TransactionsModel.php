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
 * Ticketstation Transactions Model
 * @since 0.0.1
 */
class TransactionsModel extends BaseDatabaseModel
{
    ## Empty data variabele
    var $_data  = null;
    var $_id = null;
    var $order = null;
    var $filter_order = null;

    function __construct()
    {
        parent::__construct();

        $app 	= Factory::getApplication();

        ## Get the pagination request variables
        $limit      = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->get('list_limit'), 'int' );
        $limitstart = $app->getUserStateFromRequest( 'products.limitstart', 'limitstart', 0, 'int' );

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array = $app->input->get('cid', array(0), 'array');
        $this->id = (int)$array[0];


    }


    function getPagination() {

        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
        }

        return $this->_pagination;
    }

    function getTotal(){

        $this->_total = $this->_getListCount($this->getQuery(), $this->getState('limitstart'), $this->getState('limit'));


        return $this->_total;
    }

    private function getQuery(){

        $app = Factory::getApplication();

        $search     = $app->getUserStateFromRequest('searchbox', 'searchbox', '', 'string');
        $search     = strtolower($search);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select( array('t.*', 'c.name', 'c.firstname'));
        $query->from($db->quoteName('#__ticketstation_transactions', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('t.userid') . ')');

        if ($search)
        {
            $like_filter = ' LIKE ' . $db->quote('%' . str_replace(' ', '%', $search) . '%');

            $where = [
                $db->quoteName('c.name') . $like_filter,
                $db->quoteName('c.firstname') . $like_filter,
                $db->quoteName('t.orderid') . $like_filter,
            ];

            $query->where('(' . implode(' OR ', $where) . ')');
        }

        $query->order('pid DESC');

        return $query;
    }

    function getList(){

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getQuery(), $this->getState('limitstart'), $this->getState('limit' ));
        $data = $db->loadObjectList();

        return $data;
    }

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_transactions', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('t.userid') . ')');
        $query->where($db->quoteName('t.pid') . ' = '. $db->quote((int) $this->id));

        $db->setQuery($query);
        $this->data = $db->loadObject();

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

    function remove($cid){

        ## Count the cids
        if (count( $cid )) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $query = $db->getQuery(true);

            // delete all custom keys for user 1001.
            $conditions = array(
                $db->quoteName('pid') . ' IN ( '.$cids.' )',
            );

            $query->delete($db->quoteName('#__ticketstation_transactions'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if(!$result){
                return false;
            }

            return true;

        }
    }


}