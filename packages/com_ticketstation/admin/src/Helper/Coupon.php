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
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die('Restricted access');

/**
 * Discount coupons.
 *
 * A coupon applies to a whole order, one coupon per order. Applying it stores its code and
 * terms (discount_type 1 = percentage, 0 = amount; discount_amount) on every row of the
 * order, so later edits of the coupon don't change an order that already uses it. The
 * discount itself is taken over the order total (see discountFor()): a percentage of it, or
 * the fixed amount, but never more than the total.
 *
 * How often a coupon is used is not counted up anywhere: it is the number of orders that
 * carry its code, whatever their status (see usage()). Just like a ticket, a use is given
 * back as soon as the order is gone: cleaned up by the ticketcleaner or deleted in the Box
 * Office.
 */
class Coupon
{
    /**
     * Applies a coupon code to the order in the visitor's session.
     *
     * @param   string  $code  The code as the customer typed it.
     *
     * @return  bool
     */
    public function check($code)
    {
        $app       = Factory::getApplication();
        $ordercode = (int) $app->getSession()->get('ordercode');
        $coupon    = $this->getCouponFromDatabase($this->sanitizeCouponCode((string) $code));

        if (empty($coupon))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_COUPON'), 'error');

            return false;
        }

        if (!empty($coupon->coupon_valid_to) && $coupon->coupon_valid_to < Date::localNow('Y-m-d'))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_EXPIRED'), 'error');

            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['COUNT(*) AS items', 'MAX(COALESCE(' . $db->quoteName('coupon') . ', ' . $db->quote('') . ')) AS coupon'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$ordercode || (int) $order->items === 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EMPTY_CART'), 'error');

            return false;
        }

        if ($order->coupon !== '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_ALREADY_APPLIED'), 'error');

            return false;
        }

        if ($coupon->coupon_limit > 0 && self::usage($coupon->coupon_code) >= $coupon->coupon_limit)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_WAS_LIMITED'), 'error');

            return false;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_orders'))
            ->set([
                $db->quoteName('coupon') . ' = ' . $db->quote($coupon->coupon_code),
                $db->quoteName('discount_type') . ' = ' . (int) $coupon->coupon_type,
                $db->quoteName('discount_amount') . ' = ' . (float) $coupon->coupon_discount,
            ])
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode);

        $db->setQuery($query);

        if (!$db->execute())
        {
            return false;
        }

        return self::refresh($ordercode);
    }

    /**
     * The number of orders that use a coupon code.
     */
    public static function usage(string $code): int
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('ordercode') . ')')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('coupon') . ' = ' . $db->quote($code));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * usage() as an SQL expression, for lists of coupons: pass the qualified column that holds
     * the coupon code (e.g. "c.coupon_code").
     */
    public static function usageSql(string $codeColumn): string
    {
        return '(SELECT COUNT(DISTINCT uo.ordercode) FROM #__ticketstation_orders AS uo WHERE uo.coupon = ' . $codeColumn . ')';
    }

    /**
     * The discount on an order total for the given coupon terms, in cents precision: a
     * percentage of the total, or the fixed amount but never more than the total.
     */
    public static function discountFor(float $total, $type, $amount): float
    {
        if ($total <= 0 || (float) $amount <= 0)
        {
            return 0.0;
        }

        $discount = (int) $type === 1 ? $total / 100 * (float) $amount : min((float) $amount, $total);

        return round($discount, 2);
    }

    /**
     * Brings the discount (and the VAT that goes with it) of every row of an order in line
     * with the coupon it carries, for everything that adds up the rows: the invoice, the
     * Box Office. The order's discount is spread over its rows in proportion to their price,
     * the last row taking the rounding difference, so the rows always add up to exactly the
     * discount the customer pays with (see OrderTotals::get()). Rows added after the
     * coupon was applied get its terms too. An order without a coupon is left alone.
     */
    public static function refresh(int $ordercode): bool
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['orderid', 'price', 'vat_percentage', 'coupon', 'discount_type', 'discount_amount'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->order($db->quoteName('orderid') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $terms = null;

        foreach ($rows as $row)
        {
            if ((string) $row->coupon !== '')
            {
                $terms = $row;
                break;
            }
        }

        if (!$terms)
        {
            return true;
        }

        $total    = array_sum(array_map(fn ($row) => (float) $row->price, $rows));
        $discount = self::discountFor($total, $terms->discount_type, $terms->discount_amount);
        $left     = $discount;
        $last     = count($rows) - 1;

        foreach ($rows as $i => $row)
        {
            $share = $i === $last ? $left : ($total > 0 ? round($discount * (float) $row->price / $total, 2) : 0.0);
            $share = min(max($share, 0.0), (float) $row->price);
            $left  = round($left - $share, 2);
            $vat   = (new Amount)->calculateVatFromPrice((float) $row->price - $share, (float) $row->vat_percentage);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ticketstation_orders'))
                ->set([
                    $db->quoteName('coupon') . ' = ' . $db->quote($terms->coupon),
                    $db->quoteName('discount_type') . ' = ' . (int) $terms->discount_type,
                    $db->quoteName('discount_amount') . ' = ' . (float) $terms->discount_amount,
                    $db->quoteName('discount') . ' = ' . $share,
                    $db->quoteName('vat') . ' = ' . (float) $vat['vat_amount'],
                ])
                ->where($db->quoteName('orderid') . ' = ' . (int) $row->orderid);

            $db->setQuery($query);

            if (!$db->execute())
            {
                return false;
            }
        }

        return true;
    }

    /**
     * The published coupon with this code, or null.
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
     * Codes are stored in capitals; what the customer types is compared in capitals too.
     */
    private function sanitizeCouponCode($coupon)
    {
        $coupon = str_replace([":", "/", "\\", "@", "#", "@", "!", "$", "?"], "", trim($coupon));

        return strtoupper($coupon);
    }
}
