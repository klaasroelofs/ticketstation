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
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Model\Mixin\ListState;

/**
 * Ticketstation Clients Model
 * @since 0.0.1
 */
class ClientsModel extends BaseDatabaseModel
{
    use ListState;

    private $id;

    /**
     * Customers remove() left alone because they have orders or waiting-list entries.
     *
     * @var int[]
     */
    public $keptClients = [];

    function __construct(){

        parent::__construct();

        $app 	= Factory::getApplication();

        $this->populateListState('clients', [
            'search' => ['searchbox', '', 'string'],
        ]);

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

        $search     = strtolower((string) $this->getState('filter.search'));

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('*'));
        $query->from($db->quoteName('#__ticketstation_clients'));

        if ($search)
        {
            $like_filter = ' LIKE ' . $db->quote('%' . str_replace(' ', '%', $search) . '%');

            $where = [
                // First and last name together, so "Jan Jansen" is found.
                'CONCAT_WS(' . $db->quote(' ') . ', ' . $db->quoteName('firstname') . ', ' . $db->quoteName('name') . ')' . $like_filter,
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

    function store($data)
    {

        $table = $this->getTable();

        // Bind, check and store. Table methods return false or throw on a failure.
        try
        {
            return $table->bind($data) && $table->check() && $table->store();
        }
        catch (\Exception $e)
        {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

            return false;
        }
    }

    function remove($cid){

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            // A customer with orders (or still waiting on the waiting list) stays: their orders,
            // tickets and invoices refer to them. A signup that was already turned into an order
            // (processed = 1) no longer counts: it isn't shown on the Waitinglist screen, so it
            // could never be removed, and its order is checked above.
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('userid'))
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('userid') . ' IN (' . $cids . ')')
                ->union(
                    $db->getQuery(true)
                        ->select('DISTINCT ' . $db->quoteName('userid'))
                        ->from($db->quoteName('#__ticketstation_waitinglist'))
                        ->where($db->quoteName('userid') . ' IN (' . $cids . ')')
                        ->where($db->quoteName('processed') . ' = 0')
                );

            $db->setQuery($query);
            $this->keptClients = array_map('intval', $db->loadColumn());

            $cid = array_values(array_diff($cid, $this->keptClients));

            if (!count($cid)) {
                return true;
            }

            $cids = implode(',', $cid);

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('clientid').' IN ('.$cids.')'
            );

            $query->delete($db->quoteName('#__ticketstation_clients'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            // Their processed signups go with them: nothing reads those any more, and they hold
            // the customer's IP address.
            if ($result) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__ticketstation_waitinglist'))
                    ->where($db->quoteName('userid') . ' IN (' . $cids . ')')
                    ->where($db->quoteName('processed') . ' = 1');

                $db->setQuery($query);
                $db->execute();
            }

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

        $query->select(array('a.*', 't.ticketname', 'e.eventcode', 'e.eventname', 'COUNT(a.orderid) AS totaltickets', 'r.remarks', 'tt.amount AS transaction_amount'));
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

        // What was paid, or else what the customer pays, as the Box Office shows it.
        foreach ($this->data as $row)
        {
            $row->orderprice = (float) $row->transaction_amount > 0
                ? (float) $row->transaction_amount
                : OrderTotals::get($row->ordercode)->total;
        }

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