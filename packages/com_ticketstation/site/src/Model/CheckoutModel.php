<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Confirmation;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Coupon;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\User;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * Ticketstation Checkout Model
 * @since 0.2.10
 */
class CheckoutModel extends BaseDatabaseModel
{
    private $ordercode;

    public function __construct()
    {
        parent::__construct();

        $this->ordercode = Factory::getApplication()->getSession()->get('ordercode');
        //$this->userid    = JFactory::getUser()->id;
    }

    /**
     * Requesting data from the configuration table.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getConfig()
    {
        return (new Config)->get();
    }

    /**
     * Updating the items in the database with some userdata and a new ordercode.
     *
     * @param $userid
     * @param $ordercode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function itemsupdate($userid, $ordercode)
    {
        if ($userid == '')
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($query);

        // A customer with only waiting-list tickets has no order yet: skip the ordercode
        // rewrite, as the waiting-list rows (and the payment screen) still use this ordercode.
        if ((int) $db->loadResult() > 0)
        {
            if ( ! (new Order)->update($userid, $ordercode))
            {
                return false;
            }
        }

        // Getting the configuration option.
        $config = (new Config)->get(['show_waitinglist']);

        if ($config->show_waitinglist == 1)
        {
            $query = $db->getQuery(true);

            // Fields to update
            $fields = [$db->quoteName('userid') . ' = ' . $db->quote((int) $userid)];

            // Conditions for the uddate
            $conditions = [$db->quoteName('ordercode') . ' = ' . $db->quote($ordercode)];

            // Execute the query.
            $query->update($db->quoteName('#__ticketstation_waitinglist'))->set($fields)->where($conditions);

            $db->setQuery($query);

            if ( ! $db->execute())
            {
                return false;
            }

            $sendconfirmation = new Confirmation((int) $ordercode);
            $sendconfirmation->SendWaitingList();
        }

        return true;
    }

    function checkCoupon($data)
    {
        return (new Coupon)->check($data['couponcode']);
    }

    /**
     * Store information in the database.
     *
     * @param $data
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function store($data)
    {
        $table = $this->getTable('checkout');

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

        $this->clientid = $this->_db->insertid();

        return true;
    }

    /**
     * Getting the user information by ID
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getData()
    {
        return (new User)->getClientById();
    }

    /**
     * Getting the user information by ID
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getUserInformation()
    {
        return (new User)->getClientById();
    }

    function getClientid(){
        return $this->clientid;
    }
}