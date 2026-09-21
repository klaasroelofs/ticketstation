<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

## no direct access

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

class Coupon
{
    /**
     * Checking the coupon and updating orders if required.
     *
     * @param $coupon
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function check($coupon)
    {
        // Sanitizing the coupon.
        $coupon = $this->sanitizeCouponCode($coupon);

        // Loading the coupon data.
        $coupon = $this->getCouponFromDatabase($coupon);

        // Check there is one.
        if (empty($coupon))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_COUPON'), 'error');
            return false;
        }

        // If there is a limit on this coupon.
        if ($coupon->coupon_limit != 0)
        {
            if ($coupon->coupon_limit == $coupon->coupon_used)
            {
                Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_WAS_LIMITED'), 'error');
                return false;
            }
        }

        if (!empty($coupon->coupon_valid_to) && $coupon->coupon_valid_to < date('Y-m-d'))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_EXPIRED'), 'error');
            return false;
        }

        if ( ! $this->resetDiscounts())
        {
            return false;
        }

        if ( ! $this->updateOrdersInDatabase($coupon))
        {
            return false;
        }

        // Setting the coupon in the session, for later use.
        if (Factory::getApplication()->getSession()->get('coupon') == '')
        {
            Factory::getApplication()->getSession()->set('coupon', $coupon);
            return $this->updateCouponUsage($coupon->coupon_id);

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_ALREADY_APPLIED'), 'error');
            return false;
        }
        
    }

    /**
     * Resets all discounts in the database.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function resetDiscounts()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('coupon') . ' = ""',
            $db->quoteName('discount_type') . ' = 0',
            $db->quoteName('discount_amount') . ' = 0',
            $db->quoteName('discount') . ' = 0',

        ];

        // Setting the conditions for updating
        $conditions = [$db->quoteName('ordercode') . ' = ' . Factory::getApplication()->getSession()->get('ordercode')];

        // Updating the table
        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        $query = $db->getQuery(true);

        $conditions = [
            $db->quoteName('discount') . ' >= 0',
            $db->quoteName('ticketid') . ' = 0',
            $db->quoteName('ordercode') . ' = ' . Factory::getApplication()->getSession()->get('ordercode'),
        ];

        $query->delete($db->quoteName('#__ticketstation_orders'));
        $query->where($conditions);

        $db->setQuery($query);

        return $db->execute();
    }

    /**
     * Updating the coupon usage in the database.
     *
     * @param $id
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function updateCouponUsage($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields     = [$db->quoteName('coupon_used') . ' = coupon_used+1'];
        $conditions = [$db->quoteName('coupon_id') . ' = ' . $id];
        $query->update($db->quoteName('#__ticketstation_coupons'))->set($fields)->where($conditions);

        $db->setQuery($query);
        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    /**
     * Updating orders in the database with their discounts.
     *
     * @param $orders
     * @param $coupon
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function updateOrdersInDatabase($coupon)
    {
        $amount  = new Amount;
        $amounts = $amount->getAmount();

        // Amount Discount
        if ($coupon->coupon_type == 0)
        {
            if ( ! $this->updateAmountCoupon($coupon, $amounts))
            {
                return false;
            }

            return true;
        }

        // Getting the orders from the database.
        $orders = (new Order)->getOrdersByOrdercode();

        foreach ($orders as $order)
        {
            if ( ! $this->uddatePercentageCoupon($order, $coupon))
            {
                return false;
            }
        }

        return true;
    }

    private function updateAmountCoupon($coupon, $amounts)
    {
        // Discount per ordered ticket.
        $discount = $coupon->coupon_discount;

        //Checks if the ticketprice is bigger than the discount.
        if ($amounts->total < $discount)
        {
            $discount = $amounts->total;
        }

        /* $object                  = new \stdClass;
        $object->ordercode       = \JFactory::getSession()->get('ordercode');
        $object->discount_type   = 0;
        $object->discount_amount = floatval($coupon->coupon_discount);
        $object->discount        = floatval($discount);

        return \JFactory::getDbo()->insertObject('#__ticketmaster_orders', $object); */

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $ordercode = Factory::getApplication()->getSession()->get('ordercode');

        $fields = [
            $db->quoteName('coupon') . ' = ' . $db->quote($coupon->coupon_code),
            $db->quoteName('discount_type') . ' = 0',
            $db->quoteName('discount_amount') . ' = ' . floatval($coupon->coupon_discount),
            $db->quoteName('discount') . ' = ' . floatval($discount),
        ];

        // Setting the conditions for updating
        $conditions = [$db->quoteName('ordercode') . ' = ' . $ordercode];

        // Updating the table
        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        return $db->execute();

    }

    /**
     * Updating the order details when it is a precentage discount.
     *
     * @param $order
     * @param $coupon
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function uddatePercentageCoupon($order, $coupon)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $discount = ($order->price / 100) * $coupon->coupon_discount;
        $price    = $order->price - $discount;
        $amounts  = (new Amount)->calculateVatFromPrice($price, $order->vat_percentage);

        $fields = [
            $db->quoteName('coupon') . ' = ' . $db->quote($coupon->coupon_code),
            $db->quoteName('discount_type') . ' = 1',
            $db->quoteName('discount_amount') . ' = ' . floatval($coupon->coupon_discount),
            $db->quoteName('discount') . ' = ' . (floatval(($order->price / 100) * $coupon->coupon_discount)),
            $db->quoteName('vat') . ' = ' . (floatval($amounts['vat_amount'])),

        ];

        // Setting the conditions for updating
        $conditions = [$db->quoteName('orderid') . ' = ' . $order->orderid];

        // Updating the table
        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        return $db->execute();
    }

    /**
     * Getting the coupon data from the database.
     *
     * @param $coupon
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    private function getCouponFromDatabase($coupon)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_coupons'))
            ->where($db->quoteName('coupon_code') . ' = ' . $db->quote($coupon))
            ->where($db->quoteName('published') . ' = 1');

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Sanitize the coupon
     *
     * @param $coupon
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function sanitizeCouponCode($coupon)
    {
        $coupon = str_replace([":", "/", "\\", "@", "#", "@", "!", "$", "?"], "", $coupon);

        return strtoupper($coupon);
    }
}