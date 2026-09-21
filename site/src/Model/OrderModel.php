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
 * Ticketstation Order Model
 * @since 0.2.10
 */
class OrderModel extends BaseDatabaseModel
{

    private $id;
    private $ordercode;

    function __construct()
    {
        parent::__construct();
        //$array    = Factory::getApplication()->getInput('cid', array(0), '', 'array');
        //$this->id = (int)$array[0];

        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();
        $this->ordercode = $session->get('ordercode');
    }

    /**
     * Saving data to the specified table.
     *
     * @param $data
     * @param $table
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function store($data)
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
            Factory::getApplication()->enqueueMessage('Store failed ' . $table->getError(), 'error');
            //$this->setError($table->getError());
            return false;
        }

        $this->orderid = $this->_db->insertid();

        return true;

    }

    function getOrderid(){
        return $this->orderid;
    }

    function updateCoords($orderid, $id) {

        $query = 'UPDATE #__ticketstation_seatplancoords'
            . ' SET booked = 1, orderid = '.(int)$orderid
            . ' WHERE id = '.(int)$id.'';

        ## Do the query now
        $this->_db->setQuery( $query );

        ## When query goes wrong.. Show message with error.
        if (!$this->_db->execute()) {
            //$this->setError($this->_db->getErrorMsg());
            return false;
        }

        return true;
    }

}