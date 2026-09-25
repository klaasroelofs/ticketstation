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
use Ticketstation\Component\Ticketstation\Administrator\Helper\CustomerNote;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * Ticketstation Cart Model
 * @since 0.2.10
 */
class CartModel extends BaseDatabaseModel
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
        return (new Order)->getOrdersInCart();
    }

    /**
     * Getting all orders on the waiting list
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getWaiters()
    {
        return (new WaitingList)->getOrdersOnWaitingList($this->ordercode);
    }

    /**
     * Getting order which requires additional information.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getRequiredInformation()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.orderid', 't.ticketname'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->where($db->quoteName('a.require_information') . " = 1")
            ->where($db->quoteName('a.required_information') . " = " . $db->quote(""))
            ->where($db->quoteName('a.ordercode') . " = " . (int) $this->ordercode);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Getting the configuration
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    function getConfig()
    {
        return (new Config)->get();
    }

    /**
     * The note the customer added to this order earlier, so the cart can show it again.
     *
     * @return string
     */
    function getCustomerNote()
    {
        return (new CustomerNote)->get($this->ordercode);
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

}