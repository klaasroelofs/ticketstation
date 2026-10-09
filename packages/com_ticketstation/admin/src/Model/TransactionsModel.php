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
use Ticketstation\Component\Ticketstation\Administrator\Model\Mixin\ListState;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TestData;

/**
 * Ticketstation Transactions Model
 * @since 0.0.1
 */
class TransactionsModel extends BaseDatabaseModel
{
    use ListState;

    ## Empty data variabele
    var $_data  = null;
    var $_id = null;
    var $order = null;
    var $filter_order = null;

    function __construct()
    {
        parent::__construct();

        $this->populateListState('transactions', [
            'search' => ['searchbox', '', 'string'],
        ]);



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

        $search     = strtolower((string) $this->getState('filter.search'));

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select( array('t.*', 'c.name'));
        $query->from($db->quoteName('#__ticketstation_transactions', 't'));
        $query->where(TestData::condition('t.test'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('t.userid') . ')');

        if ($search)
        {
            $like_filter = ' LIKE ' . $db->quote('%' . str_replace(' ', '%', $search) . '%');

            $where = [
                $db->quoteName('c.name') . $like_filter,
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