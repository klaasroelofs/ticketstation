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


defined('_JEXEC') or die('Restricted access');

class Amount
{
    private $order_items;
    private $order_total;
    private $order_discount;
    private $order_fees;
    private $order_price_ex;
    private $total_discounted;
    private $order_total_discounted_ex_vat;

    /**
     * Sets the date for the cart and the database amounts.
     *
     * @since 3.5.0
     */
    public function setCartTotals()
    {
        $total = $this->getAmount();

        $this->order_items                   = $total->items;
        $this->order_total                   = $total->total;
        $this->order_discount                = $total->discount;
        $this->order_fees                    = $total->fees;
        $this->order_price_ex                = $total->price_ex;
        $this->total_discounted              = $total->total_discounted;
        $this->order_total_discounted_ex_vat = $total->total_discounted - $total->vat;

        return true;
    }

    /**
     * @param $ordercode
     *
     * @return string
     *
     * @since 3.5.0
     */
    public function getAmountsAsJsonObject($ordercode)
    {
        $total = $this->getAmountByOrdercode($ordercode);

        return json_encode([
            'order_items'                   => $total->items,
            'order_total'                   => $total->total_discounted,
            'order_discount'                => $total->discount,
            'order_fees'                    => $total->fees,
            'order_vat'                     => $total->vat,
            'order_price_ex'                => $total->price_ex,
            'order_total_without_discount'  => $total->total,
            'order_total_discounted_ex_vat' => $total->total_discounted - $total->vat,
        ]);
    }

    /**
     * Getting the ordersfrom the database by the current ordercode.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getAmount($ordercode = null)
    {
        $app = Factory::getApplication();
        $session = $app->getSession();

        // Check ordercode and re-use it.
        $ordercode = ! empty($ordercode) ? $ordercode : $session->get('ordercode');
        //$ordercode = ! empty($ordercode) ? $ordercode : \JFactory::getSession()->get('ordercode');

        $db = Factory::getContainer()->get('DatabaseDriver');

        // discount/fees/vat/price_excluding_vat default to NULL on orders that never went
        // through a coupon/fee/vat calculation (e.g. no coupon applied). SUM() of an
        // all-NULL column returns NULL in MySQL, which would otherwise poison every total
        // that subtracts or adds it (total_discounted, total_discounted_ex_vat) - COALESCE
        // each one to 0 first.
        $query = $db->getQuery(true)
            ->select([
                'COUNT(orderid) as items',
                'SUM(price) as total',
                'SUM(price)-SUM(COALESCE(discount, 0)) as total_discounted',
                'SUM(COALESCE(discount, 0)) as discount',
                'SUM(COALESCE(fees, 0)) as fees',
                'SUM(COALESCE(vat, 0)) as vat',
                'SUM(COALESCE(price_excluding_vat, 0)) as price_ex',
                '(SUM(price)-SUM(COALESCE(discount, 0))) - SUM(COALESCE(vat, 0)) as total_discounted_ex_vat',
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->group('ordercode');

        $db->setQuery($query);

        $amounts = $db->loadObject();

        // todo making fees configurable per ticket.
        $this->setFeesByOrderTotals($amounts->total);

        $amounts->fees                    = $this->order_fees;
        $amounts->total_discounted_ex_vat = $amounts->total_discounted - $amounts->vat;

        return $amounts;
    }

    /**
     * Getting the ordersfrom the database by the given ordercode.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getAmountByOrdercode($ordercode)
    {
        return $this->getAmount($ordercode);
    }

    /**
     * Setting order feess
     *
     * @param $amount
     *
     * @since 3.5.0
     */
    private function setFeesByOrderTotals($amount)
    {
        $config = (new Config)->getPartialConfig(['variable_transcosts', 'transactioncosts', 'transcosts']);

        if ($config->variable_transcosts == 2)
        {
            // Transaction costs are switched off completely.
            $this->order_fees = 0;
        }
        elseif ($config->variable_transcosts != 1)
        {
            // When no variable transaction costs are here.
            $this->order_fees = $config->transactioncosts;
        }
        else
        {
            // Total order amount for ordercode (eid) --> variable cost is on.
            $this->order_fees = (($amount / 100) * $config->transcosts) + $config->transactioncosts;
        }
    }

    /**
     * Returns an array with data for the price.
     *
     * @param $price
     * @param $vat_amt
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function calculateVatFromPrice($price, $vat_amt)
    {
        return [
            'vat_amount'          => $price / (100 + $vat_amt) * $vat_amt,
            'vat_percentage'      => $vat_amt,
            'price_excluding_vat' => $price - ($price / (100 + $vat_amt) * $vat_amt),
        ];
    }

    /**
     * Return Netto Price for the order..
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getNettoPrice()
    {
        return $this->order_price_ex;
    }

    /**
     * Return Fees for this order.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getFees()
    {
        return $this->order_fees;
    }

    /**
     * Return the discount.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getDiscount()
    {
        return $this->order_discount;
    }

    /**
     * Return Bruto Price for the order.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getBrutoPrice()
    {
        return $this->order_total;
    }

    /**
     * Return ordered items in cart.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getOrderItems()
    {
        return $this->order_items;
    }

    /**
     * Return total discounted price.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getDiscountedBrutoPrice()
    {
        return $this->total_discounted;
    }

    /**
     * Return total discounted price.
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getDiscountedNettoPrice()
    {
        return $this->order_total_discounted_ex_vat;
    }
}