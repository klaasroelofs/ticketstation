<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;

defined('_JEXEC') or die;

/**
 * The amounts of an order as a whole: its tickets, the coupon discount, the transaction costs
 * and the total the customer pays.
 *
 * The order rows (#__ticketstation_orders) hold one ticket each, with the price at the moment
 * of ordering. Per order, #__ticketstation_ordertotals keeps:
 *
 * - the coupon and its terms as they were when it was applied (see Coupon), so later edits of
 *   the coupon don't change the order;
 * - the terms of the transaction costs: the kind (fixed, variable or none, as in the
 *   Configuration) and the amount or percentage as it was when the customer went to the payment
 *   page (Order::update()). A backend reservation and a bypassed order get no transaction
 *   costs. Changing the Configuration afterwards leaves such an order alone. Without kept terms
 *   (fee_type NULL, or no row at all), as for a cart that hasn't been through checkout yet, the
 *   current Configuration applies.
 *
 * The amounts themselves are always worked out from the rows and these terms, so removing a
 * ticket from an order keeps a fixed fee, and recalculates a percentage and the discount over
 * what is left.
 */
class OrderTotals
{
    public const FEE_FIXED    = 0;
    public const FEE_VARIABLE = 1;
    public const FEE_NONE     = 2;

    /**
     * The amounts of an order.
     *
     * @param   int|string  $ordercode
     * @param   bool        $openOnly   Only the rows still to be paid (not paid, or pending), as
     *                                  the cart, the payment page and Mollie count them.
     *
     * @return  object  items, tickets, coupon, discount_type, discount_amount, discount,
     *                  coupon_tickets (the tickets a limited coupon counts for, else empty),
     *                  subtotal (tickets - discount), fee_type, fee_rate, fees and total.
     */
    public static function get($ordercode, bool $openOnly = false): object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // A coupon for certain tickets only takes its discount over those tickets.
        $coupon   = self::coupon($ordercode);
        $limited  = $coupon ? Coupon::ticketIds($coupon->coupon_tickets ?? '') : [];
        $eligible = $limited
            ? 'SUM(CASE WHEN ' . $db->quoteName('ticketid') . ' IN (' . implode(',', $limited) . ') THEN ' . $db->quoteName('price') . ' ELSE 0 END)'
            : 'SUM(' . $db->quoteName('price') . ')';

        $query = $db->getQuery(true)
            ->select([
                'COALESCE(' . $db->quoteName('vat_percentage') . ', 0) AS rate',
                'COUNT(*) AS items',
                'SUM(' . $db->quoteName('price') . ') AS tickets',
                $eligible . ' AS eligible',
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->group('rate');

        if ($openOnly)
        {
            $query->whereIn($db->quoteName('paid'), [0, 3]);
        }

        $db->setQuery($query);
        $groups = $db->loadObjectList();

        $items         = 0;
        $tickets       = 0.0;
        $rates         = [];
        $eligible      = 0.0;
        $eligibleRates = [];

        foreach ($groups as $group)
        {
            $items    += (int) $group->items;
            $tickets  += (float) $group->tickets;
            $eligible += (float) $group->eligible;
            $rates[(string) (float) $group->rate]         = (float) $group->tickets;
            $eligibleRates[(string) (float) $group->rate] = (float) $group->eligible;
        }

        $terms    = self::terms($ordercode);
        $type     = $coupon->discount_type ?? null;
        $amount   = $coupon->discount_amount ?? null;
        $coupon   = $coupon->coupon ?? '';
        $tickets  = round($tickets, 2);
        $discount = $coupon !== '' ? Coupon::discountFor(round($eligible, 2), $type, $amount) : 0.0;
        $subtotal = round($tickets - $discount, 2);
        $fees     = self::feesFor($subtotal, $terms);
        $vat      = self::vatByRate($rates, $tickets, $discount, $fees, $limited ? $eligibleRates : null);

        return (object) [
            'items'           => $items,
            'tickets'         => $tickets,
            'coupon'          => $coupon,
            'discount_type'   => $type,
            'discount_amount' => $amount,
            'discount'        => $discount,
            'coupon_tickets'  => $limited,
            'subtotal'        => $subtotal,
            'fee_type'        => $terms->fee_type,
            'fee_rate'        => $terms->fee_rate,
            'fees'            => $fees,
            'total'           => round($subtotal + $fees, 2),
            'vat_rates'       => $vat,
            'vat'             => round(array_sum(array_column($vat, 'vat')), 2),
        ];
    }

    /**
     * Splits an order over its VAT rates. All prices include VAT. The discount and the service
     * fee are spread over the rates in proportion to their ticket amounts, the last rate taking
     * the rounding difference, so the service fee carries the VAT of the tickets it belongs to.
     * A coupon limited to certain tickets spreads its discount over those tickets only.
     *
     * @param   array       $rates          VAT percentage => ticket amount at that rate
     * @param   array|null  $discountRates  VAT percentage => amount of the tickets the coupon
     *                                      counts for; null when it counts for all of them
     *
     * @return  array  VAT percentage => (object) tickets, discount, fees (all including VAT),
     *                 total and vat
     */
    public static function vatByRate(array $rates, float $tickets, float $discount, float $fees, ?array $discountRates = null): array
    {
        $result       = [];
        $discountLeft = $discount;
        $feesLeft     = $fees;
        $keys         = array_keys($rates);
        $last         = end($keys);
        $discountBase = $discountRates !== null ? array_sum($discountRates) : $tickets;

        // The last rate with tickets the coupon counts for takes the rounding difference.
        $discountKeys = $discountRates !== null ? array_keys(array_filter($discountRates, fn ($amount) => $amount > 0)) : $keys;
        $discountLast = $discountKeys ? end($discountKeys) : null;

        foreach ($rates as $rate => $amount)
        {
            $share         = $tickets > 0 ? $amount / $tickets : 0.0;
            $discountShare = $discountBase > 0 ? ($discountRates !== null ? ($discountRates[$rate] ?? 0.0) : $amount) / $discountBase : 0.0;

            $rateDiscount = $rate === $discountLast ? $discountLeft : round($discount * $discountShare, 2);
            $discountLeft = round($discountLeft - $rateDiscount, 2);

            $incl = round($amount - $rateDiscount, 2);

            $rateFees = $rate === $last ? $feesLeft : round($fees * $share, 2);
            $feesLeft = round($feesLeft - $rateFees, 2);

            $total = round($incl + $rateFees, 2);

            $result[$rate] = (object) [
                'rate'     => (float) $rate,
                'tickets'  => round($amount, 2),
                'discount' => $rateDiscount,
                'fees'     => $rateFees,
                'total'    => $total,
                'vat'      => self::vatIn($total, (float) $rate),
            ];
        }

        return $result;
    }

    /**
     * The VAT included in an amount, in cents.
     */
    public static function vatIn(float $amount, float $rate): float
    {
        return $rate > 0 ? round($amount - $amount / (100 + $rate) * 100, 2) : 0.0;
    }

    /**
     * The transaction costs over what the customer pays for the tickets. Nothing to pay means
     * no transaction costs either.
     */
    public static function feesFor(float $subtotal, object $terms): float
    {
        if (round($subtotal, 2) <= 0)
        {
            return 0.0;
        }

        switch ((int) $terms->fee_type)
        {
            case self::FEE_FIXED:
                return round((float) $terms->fee_rate, 2);

            case self::FEE_VARIABLE:
                return round($subtotal / 100 * (float) $terms->fee_rate, 2);

            default:
                return 0.0;
        }
    }

    /**
     * The terms of the transaction costs of an order (fee_type and fee_rate): the ones kept
     * for it, or the current Configuration for a cart that hasn't been through checkout.
     */
    public static function terms($ordercode): object
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('fee_type'), $db->quoteName('fee_rate')])
            ->from($db->quoteName('#__ticketstation_ordertotals'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode));

        $db->setQuery($query);
        $row = $db->loadObject();

        if ($row && $row->fee_type !== null)
        {
            return (object) ['fee_type' => (int) $row->fee_type, 'fee_rate' => (float) $row->fee_rate];
        }

        return self::configTerms();
    }

    /**
     * Keeps the terms for an order: the current Configuration, or none at all ($fees = false)
     * for an order that never pays transaction costs. Replaces terms kept earlier; the coupon
     * of the order stays.
     */
    public static function capture($ordercode, bool $fees = true): bool
    {
        $terms = $fees ? self::configTerms() : (object) ['fee_type' => self::FEE_NONE, 'fee_rate' => 0.0];

        return self::store($ordercode, [
            'fee_type' => $terms->fee_type,
            'fee_rate' => $terms->fee_rate,
            'captured' => Factory::getDate()->toSql(),
        ]);
    }

    /**
     * The coupon of an order and its terms as they were when it was applied, or null.
     *
     * @return  object|null  coupon, discount_type (1 = percentage, 0 = amount), discount_amount,
     *                       coupon_tickets (the tickets it counts for, "12,14"; empty: all)
     */
    public static function coupon($ordercode): ?object
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('coupon'), $db->quoteName('discount_type'), $db->quoteName('discount_amount'), $db->quoteName('coupon_tickets')])
            ->from($db->quoteName('#__ticketstation_ordertotals'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('coupon') . ' IS NOT NULL')
            ->where($db->quoteName('coupon') . ' != ' . $db->quote(''));

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Keeps a coupon and its current terms for an order; the terms of its transaction costs
     * stay as they are.
     */
    public static function setCoupon($ordercode, string $code, int $type, float $amount, string $tickets = ''): bool
    {
        return self::store($ordercode, [
            'coupon'          => $code,
            'discount_type'   => $type,
            'discount_amount' => $amount,
            'coupon_tickets'  => $tickets,
        ]);
    }

    /**
     * Writes these fields to the row of an order, creating the row when there is none yet.
     */
    private static function store($ordercode, array $fields): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $row            = (object) $fields;
        $row->ordercode = (string) $ordercode;

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_ordertotals'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode));

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0)
        {
            return $db->updateObject('#__ticketstation_ordertotals', $row, 'ordercode');
        }

        return $db->insertObject('#__ticketstation_ordertotals', $row);
    }

    /**
     * Checkout of an order (Order::update()): its terms and coupon follow it to its final
     * ordercode. An order that already has terms keeps them, for instance a reservation or
     * waiting-list order paid through a payment link; any other order gets the current
     * Configuration.
     */
    public static function move($from, $to): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        if ((string) $from !== (string) $to)
        {
            // Ordercodes get reused once the highest order is removed: a new order doesn't
            // inherit the terms or the coupon of the removed one.
            self::remove($to);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ticketstation_ordertotals'))
                ->set($db->quoteName('ordercode') . ' = ' . $db->quote((string) $to))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $from));

            $db->setQuery($query)->execute();
        }

        if (!self::captured($to))
        {
            self::capture($to);
        }
    }

    /**
     * Whether the terms of the transaction costs are kept for an order.
     */
    public static function captured($ordercode): bool
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_ordertotals'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('fee_type') . ' IS NOT NULL');

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    public static function remove($ordercode): void
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_ordertotals'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode));

        $db->setQuery($query)->execute();
    }

    /**
     * Removes the terms and coupons of orders that no longer have any rows (cleaned up or
     * deleted), which also gives the use of such a coupon back (see Coupon::usage()).
     */
    public static function sweep(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery(
            'DELETE ' . $db->quoteName('ot') . ' FROM ' . $db->quoteName('#__ticketstation_ordertotals', 'ot')
            . ' LEFT JOIN ' . $db->quoteName('#__ticketstation_orders', 'o')
            . ' ON ' . $db->quoteName('o.ordercode') . ' = ' . $db->quoteName('ot.ordercode')
            . ' WHERE ' . $db->quoteName('o.orderid') . ' IS NULL'
        )->execute();
    }

    private static function configTerms(): object
    {
        $config = (new Config)->getPartialConfig(['variable_transcosts', 'transactioncosts', 'transcosts']);
        $type   = (int) ($config->variable_transcosts ?? self::FEE_FIXED);

        switch ($type)
        {
            case self::FEE_VARIABLE:
                $rate = (float) $config->transcosts;
                break;

            case self::FEE_FIXED:
                $rate = (float) $config->transactioncosts;
                break;

            default:
                $type = self::FEE_NONE;
                $rate = 0.0;
        }

        return (object) ['fee_type' => $type, 'fee_rate' => $rate];
    }
}
