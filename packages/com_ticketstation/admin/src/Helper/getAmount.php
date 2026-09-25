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

        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'a.coupon'));
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

            $couponcode = $result->coupon;

            if ($couponcode != '') {

                $query = $db->getQuery(true);
                $query->select('*');
                $query->from($db->quoteName('#__ticketstation_coupons'));
                $query->where($db->quoteName('coupon_code') . " = " . $db->quote($couponcode));

                $db->setQuery($query);

                $coupon = $db->loadObject();

                if ($coupon->coupon_type == 1) {

                    ## Discount in %
                    $discount = ($orderprice / 100) * $coupon->coupon_discount;

                } else {

                    ## Discount in amounts :)
                    $discount = $coupon->coupon_discount;

                }

                $orderprice = $orderprice - $discount;

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
                $transcost = (($orderprice / 100) * $config->transcosts) + $config->transactioncosts;
            }

            if ($orderprice != 0) {
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

        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'a.coupon'));
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

            ## Gettig the orderid if there is one.
            //$couponcode = $session->get('coupon');
            $couponcode = $result->coupon;

            if ($couponcode != '') {

                $query = $db->getQuery(true);
                $query->select('*');
                $query->from($db->quoteName('#__ticketstation_coupons'));
                $query->where($db->quoteName('coupon_code') . " = " . $db->quote($couponcode));

                $db->setQuery($query);

                $coupon = $db->loadObject();

                if ($coupon->coupon_type == 1) {

                    ## Discount in %
                    $discount = ($orderprice / 100) * $coupon->coupon_discount;

                } else {

                    ## Discount in amounts :)
                    $discount = $coupon->coupon_discount;

                }

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

        $query->select(array('a.userid', 'SUM(t.ticketprice) AS orderprice', 'a.coupon'));
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


            $couponcode = $result->coupon;

            if ($couponcode != '') {

                $query = $db->getQuery(true);
                $query->select('*');
                $query->from($db->quoteName('#__ticketstation_coupons'));
                $query->where($db->quoteName('coupon_code') . " = " . $db->quote($couponcode));

                $db->setQuery($query);
                $coupon = $db->loadObject();

                if ($coupon->coupon_type == 1) {

                    ## Discount in %
                    $discount = ($orderprice / 100) * $coupon->coupon_discount;

                } else {

                    ## Discount in amounts :)
                    $discount = $coupon->coupon_discount;

                }

                $orderprice = $orderprice - $discount;

            }

            ## Check the order price again
            ## If the total orderprice is 0.00 then no transaction costs have to charged.
            if ($orderprice == 0) {
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
                $transcost = (($orderprice / 100) * $config->transcosts) + $config->transactioncosts;
            }


            return $transcost;

        }
    }
}
