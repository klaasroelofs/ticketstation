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
 * Ticketstation Paymentresult Model
 * @since 0.2.10
 */
class PaymentresultModel extends BaseDatabaseModel
{
    private $ordercode;

    function __construct()
    {
        parent::__construct();

        $this->ordercode = Factory::getApplication()->getInput()->get('ordercode', '0', 'int');
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*, c.*'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid'))
            ->where($db->quoteName('a.ordercode') . " = " . (int) $this->ordercode);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getUnpaid()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(array('COUNT(orderid) AS total'))
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->where($db->quoteName('a.paid') . " != 1")
            ->where($db->quoteName('a.ordercode') . " = " . (int) $this->ordercode);

        $db->setQuery($query);

        return $db->loadObject();
    }

    function getMollie() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_mollie'))
            ->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);
        return $db->loadObject();
    }

}