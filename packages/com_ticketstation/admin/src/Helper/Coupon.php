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
 * One coupon per order. Applying it stores its code and terms (discount_type 1 = percentage,
 * 0 = amount; discount_amount; coupon_tickets) with the order in #__ticketstation_ordertotals
 * (see OrderTotals::setCoupon()), so later edits of the coupon don't change an order that
 * already uses it. A coupon counts for the whole order, or only for the tickets it is limited
 * to (coupon_tickets; a parent ticket brings its child tickets, see expandTickets()). The
 * discount itself is taken over the total of the tickets it counts for (see discountFor()): a
 * percentage of it, or the fixed amount, but never more than that total.
 *
 * How often a coupon is used is not counted up anywhere: it is the number of orders that
 * carry its code and still have rows, whatever their status (see usage()). Just like a
 * ticket, a use is given back as soon as the order is gone: cleaned up by the ticketcleaner or
 * deleted in the Box Office.
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
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode);

        $db->setQuery($query);

        if (!$ordercode || (int) $db->loadResult() === 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EMPTY_CART'), 'error');

            return false;
        }

        if (OrderTotals::coupon($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_ALREADY_APPLIED'), 'error');

            return false;
        }

        // A coupon for certain tickets only: the cart must hold at least one of them.
        $tickets = self::expandTickets((string) ($coupon->coupon_tickets ?? ''));

        if ($tickets)
        {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
                ->whereIn($db->quoteName('ticketid'), $tickets);

            $db->setQuery($query);

            if ((int) $db->loadResult() === 0)
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_NOT_FOR_CART'), 'error');

                return false;
            }
        }

        if ($coupon->coupon_limit > 0 && self::usage($coupon->coupon_code) >= $coupon->coupon_limit)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_WAS_LIMITED'), 'error');

            return false;
        }

        if (!OrderTotals::setCoupon($ordercode, $coupon->coupon_code, (int) $coupon->coupon_type, (float) $coupon->coupon_discount, implode(',', $tickets)))
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
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT ' . self::usageSql($db->quote($code)));

        return (int) $db->loadResult();
    }

    /**
     * usage() as an SQL expression, for lists of coupons: pass the qualified column that holds
     * the coupon code (e.g. "c.coupon_code"). An order whose rows are all gone but whose
     * ordertotals row wasn't swept yet doesn't count.
     */
    public static function usageSql(string $codeColumn): string
    {
        return '(SELECT COUNT(*) FROM #__ticketstation_ordertotals AS uot WHERE uot.coupon = ' . $codeColumn
            . ' AND EXISTS (SELECT 1 FROM #__ticketstation_orders AS uo WHERE uo.ordercode = uot.ordercode))';
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
     * discount the customer pays with (see OrderTotals::get()), also when rows were added
     * after the coupon was applied. An order without a coupon is left alone.
     */
    public static function refresh(int $ordercode): bool
    {
        $terms = OrderTotals::coupon($ordercode);

        if (!$terms)
        {
            return true;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['orderid', 'ticketid', 'price', 'vat_percentage'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->order($db->quoteName('orderid') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        // Only the rows of the tickets the coupon counts for share in the discount; the others
        // get none.
        $tickets  = self::ticketIds($terms->coupon_tickets ?? '');
        $counts   = fn ($row) => !$tickets || in_array((int) $row->ticketid, $tickets, true);
        $eligible = array_values(array_filter($rows, $counts));

        $total    = array_sum(array_map(fn ($row) => (float) $row->price, $eligible));
        $discount = self::discountFor($total, $terms->discount_type, $terms->discount_amount);
        $left     = $discount;
        $last     = $eligible ? (int) end($eligible)->orderid : 0;

        foreach ($rows as $row)
        {
            if (!$counts($row))
            {
                $share = 0.0;
            }
            else
            {
                $share = (int) $row->orderid === $last ? $left : ($total > 0 ? round($discount * (float) $row->price / $total, 2) : 0.0);
            }

            $share = min(max($share, 0.0), (float) $row->price);
            $left  = round($left - $share, 2);
            $vat   = (new Amount)->calculateVatFromPrice((float) $row->price - $share, (float) $row->vat_percentage);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ticketstation_orders'))
                ->set([
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
     * The ticket ids in a list as kept with a coupon or an order ("12,14"); empty for a coupon
     * that counts for the whole order.
     *
     * @return  int[]
     */
    public static function ticketIds(?string $list): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $list)))));
    }

    /**
     * The tickets a coupon limited to these tickets counts for: the tickets themselves and the
     * child tickets of any parent among them, as a seated or general-admission parent is sold
     * through its child tickets.
     *
     * @return  int[]  empty for a coupon that counts for the whole order
     */
    public static function expandTickets(string $list): array
    {
        $ids = self::ticketIds($list);

        if (!$ids)
        {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('ticketid'))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->whereIn($db->quoteName('parent'), $ids);

        $db->setQuery($query);

        $all = array_merge($ids, array_map('intval', $db->loadColumn()));
        sort($all);

        return array_values(array_unique($all));
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
