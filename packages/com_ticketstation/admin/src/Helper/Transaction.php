<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;


defined('_JEXEC') or die('Restricted access');

class Transaction
{
    /**
     * Getting the transaction details.
     *
     * @param $ordercode
     *
     * @return mixed
     *
     * @since 3.5.0
     */
    public function getTransactionDetails($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_transactions'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Removing the transaction belonging to an order, if one was recorded.
     * Note: despite the column name, #__ticketstation_transactions.orderid
     * actually stores the order's ordercode, not orders.orderid.
     *
     * @param $ordercode
     *
     * @return bool
     *
     */
    public function remove($ordercode)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_transactions'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        return (bool) $db->execute();
    }
}