<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

## no direct access
defined('_JEXEC') or die('Restricted access');

class getAmount
{

    function _getAmount($eid, $paymentprocessor = 0, $backend = 0)
    {

        ## Connect database now.
        $db = Factory::getContainer()->get('DatabaseDriver');

        if ($backend == 0) {

            $total = $this->getItems($eid);

            if ($total == 0) {
                $transcost = 0;
                return $transcost;
            }

        }

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);

        $config = $db->loadObject();


        $query = $db->getQuery(true);

        ## The coupon terms were stored on the rows when it was applied (see Coupon); rows added
        ## afterwards don't have them yet, hence MAX().
        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'MAX(a.coupon) AS coupon', 'MAX(a.discount_type) AS discount_type', 'MAX(a.discount_amount) AS discount_amount'));
        $query->from($db->quoteName('#__ticketstation_tickets', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_orders', 'a') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'));
        $query->where($db->quoteName('a.ordercode') . " = " . $db->quote($eid));

        if ($backend == 0) {
            $query->where('(' . $db->quoteName('a.paid') . ' = ' . $db->quote(0) . ' OR ' . $db->quoteName('a.paid') . ' = ' . $db->quote(3) . ')');
        } else {
            $query->where($db->quoteName('a.paid') . " = " . $db->quote(1));
        }

        $query->group('a.ordercode');

        $db->setQuery($query);
        $result = $db->loadObject();


        ##########################################################
        ##### THIS WILL NLY BE CALLED WHEN A PAYMENT RETURNS #####
        ##### IT IS BEING USED TO CHECK IF THERE IS A COUPON #####

        if ($paymentprocessor == 1) {

            $query = $db->getQuery(true);
            $query->select(array('userid', 'coupon'));
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('ordercode') . " = " . $db->quote($eid));

            $db->setQuery($query);

            $list = $db->loadObjectList();

            for ($i = 0, $n = count($list); $i < $n; $i++) {

                $row = $list[$i];

                if ($row->coupon != '') {

                    ## We need to fill this temporary :)
                    $app = Factory::getApplication();
                    $session = $app->getSession();
                    ## Gettig the orderid if there is one.
                    $couponcode = $session->set('coupon', $row->coupon);
                }

            }

        }

        ##### END: THIS WILL ONLY BE CALLED WHEN A PAYMENT RETURNS #####
        ##### PLEASE DO NOT CHANGE THIS CODE AS IT MAY DAMAGE!!   #####
        ###############################################################

        if ($result->orderprice == 0) {

            $amount = 0;

        } else {

            ## Get the order price!
            $orderprice = $result->orderprice;

            if ((string) $result->coupon !== '') {
                $orderprice = $orderprice - Coupon::discountFor((float) $orderprice, $result->discount_type, $result->discount_amount);
            }

            ## Now let's do the counting of the price again.
            if ($config->variable_transcosts == 2) {
                ## Transaction costs are switched off completely.
                $transcost = 0;
            } elseif ($config->variable_transcosts != 1) {
                ## When no variable transaction costs are here.
                $transcost = $config->transactioncosts;
            } else {
                ## Total order amount for ordercode (eid) --> variable cost is on.
                $transcost = (($orderprice / 100) * $config->transcosts);
            }

            if (round($orderprice, 2) != 0) {
                ## Amount is not 0.00 so transactin costs are needed.
                $amount = $orderprice + $transcost;
            } else {
                ## Amount  is 0.00 no transaction costs needed.
                $amount = $orderprice;
            }

        }

        return $amount;

    }

    function _getDiscount($eid, $backend = 0)
    {

        ## Connect database now.
        $db = Factory::getContainer()->get('DatabaseDriver');
        ## Set discount to null to avoid errors.
        $discount = 0;

        if ($backend == 0) {

            $total = $this->getItems($eid);

            if ($total == 0) {
                $transcost = 0;
                return $transcost;
            }

        }

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);

        $config = $db->loadObject();


        $query = $db->getQuery(true);

        ## The coupon terms were stored on the rows when it was applied (see Coupon); rows added
        ## afterwards don't have them yet, hence MAX().
        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'MAX(a.coupon) AS coupon', 'MAX(a.discount_type) AS discount_type', 'MAX(a.discount_amount) AS discount_amount'));
        $query->from($db->quoteName('#__ticketstation_tickets', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_orders', 'a') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'));
        $query->where($db->quoteName('a.ordercode') . " = " . $db->quote($eid));

        if ($backend == 0) {
            $query->where('(' . $db->quoteName('a.paid') . ' = ' . $db->quote(0) . ' OR ' . $db->quoteName('a.paid') . ' = ' . $db->quote(3) . ')');
        } else {
            $query->where($db->quoteName('a.paid') . " = " . $db->quote(1));
        }

        $query->group('a.ordercode');

        $db->setQuery($query);
        $result = $db->loadObject();


        if ($result->orderprice == 0) {

            $discount = 0;

        } else {

            ## Get the order price!
            $orderprice = $result->orderprice;

            if ((string) $result->coupon !== '') {
                $discount = Coupon::discountFor((float) $orderprice, $result->discount_type, $result->discount_amount);
            }

        }

        return $discount;

    }

    function getItems($ordercode)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('COUNT(orderid) AS total'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . " = " . $db->quote($ordercode));
        $query->where($db->quoteName('paid') . " != " . $db->quote(1));

        $db->setQuery($query);

        $result = $db->loadObject();


        return $result->total;

    }

    function _getFees($ordercode)
    {

        ## Connect database now.
        $db = Factory::getContainer()->get('DatabaseDriver');

        $total = $this->getItems($ordercode);

        if ($total == 0) {
            $transcost = 0;
            return $transcost;
        }

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);

        $config = $db->loadObject();

        $query = $db->getQuery(true);

        ## The coupon terms were stored on the rows when it was applied (see Coupon); rows added
        ## afterwards don't have them yet, hence MAX().
        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'MAX(a.coupon) AS coupon', 'MAX(a.discount_type) AS discount_type', 'MAX(a.discount_amount) AS discount_amount'));
        $query->from($db->quoteName('#__ticketstation_tickets', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_orders', 'a') . ' ON ' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid'));
        $query->where($db->quoteName('a.ordercode') . " = " . $db->quote($ordercode));
		$query->where('(' . $db->quoteName('a.paid') . ' = ' . $db->quote(0) . ' OR ' . $db->quoteName('a.paid') . ' = ' . $db->quote(3) . ')');
        $query->group('a.ordercode');

        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result->orderprice == 0) {

            $transcost = 0;

        } else {

            ## Get the order price!
            $orderprice = $result->orderprice;


            if ((string) $result->coupon !== '') {
                $orderprice = $orderprice - Coupon::discountFor((float) $orderprice, $result->discount_type, $result->discount_amount);
            }

            ## Check the order price again
            ## If the total orderprice is 0.00 then no transaction costs have to charged.
            if (round($orderprice, 2) == 0) {
                $transcost = 0;
                return $transcost;
            }

            ## Now let's do the counting of the price again.
            if ($config->variable_transcosts == 2) {
                ## Transaction costs are switched off completely.
                $transcost = 0;
            } elseif ($config->variable_transcosts != 1) {
                ## When no variable transaction costs are here.
                $transcost = $config->transactioncosts;
            } else {
                ## Total order amount for ordercode (eid) --> variable cost is on.
                $transcost = (($orderprice / 100) * $config->transcosts);
            }


            return $transcost;

        }
    }
}
