<?php


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
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Payment Model
 * @since 0.2.10
 */
class PaymentModel extends BaseDatabaseModel
{
    private $ordercode;

    function __construct()
    {
        parent::__construct();

        $this->ordercode = Factory::getApplication()->getSession()->get('ordercode');
    }

    /**
     * Getting all orders in cart based on the current ordercode.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getData()
    {
        // Updating the order if required, just a double check :)
        //if(!(new \RDMedia\Order)->update(JFactory::getUser()->id, $this->ordercode))
        //{
        //return false;
        //}

        return (new Order)->getOrdersInCart();
    }

    /**
     * Getting the configuraion
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getConfig()
    {
        // Loading the configuration
        $config = (new Config)->get();

        if ($config->show_waitinglist == 1)
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select(['COUNT(id) AS total'])
                ->from($db->quoteName('#__ticketstation_waitinglist'))
                ->where($db->quoteName('sent') . " = 0")
                ->where($db->quoteName('ordercode') . " = " . (int) $this->ordercode);

            $db->setQuery($query);
            $waiters = $db->loadObject();

            if ($waiters->total != 0)
            {
                $sendconfirmation = new Confirmation((int) $this->ordercode);
                $sendconfirmation->SendWaitingList();
            }
        }

        return $config;
    }

    /**
     * Getting extra data from the pro version.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getExtData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.id', 'c.orderid', 'c.seatid', 'c.row_name'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 'c') . ' ON ' . $db->quoteName('a.orderid') . ' = ' . $db->quoteName('c.orderid'))
            ->where($db->quoteName('a.ordercode') . " = " . (int) $this->ordercode);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Perform a datacheck when RD e-Tickets Pro has been installed.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getDataCheck()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['COUNT(orderid) AS total'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('requires_seat') . " = 1")
            ->where($db->quoteName('seat_sector') . " = 0")
            ->where($db->quoteName('ordercode') . " = " . (int) $this->ordercode);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getMollie() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Making the query for showing all the clients in list function
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        return $db->loadObject();
    }

}