<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Module\TicketstationBasket\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * Reads the visitor's current Ticketstation cart. All figures come from the
 * com_ticketstation helpers, so the module always agrees with the component.
 *
 * @since  2.0.0
 */
class BasketHelper
{
    /**
     * Whether com_ticketstation is installed and its helpers can be autoloaded.
     *
     * @return  boolean
     *
     * @since   2.0.0
     */
    public function isComponentAvailable(): bool
    {
        return class_exists(Order::class) && class_exists(getAmount::class);
    }

    /**
     * Number of tickets in the cart of the current session.
     *
     * Uses the same helper as the component's `itemcount` AJAX task, so the value
     * rendered here is identical to the one the component's JavaScript writes into
     * `#basket-item-count` after a ticket is added.
     *
     * @return  integer
     *
     * @since   2.0.0
     */
    public function getItemCount(): int
    {
        return $this->getOrderCount() + $this->getWaitingCount();
    }

    /**
     * Number of tickets in the cart that are actually ordered (excluding the waiting list).
     *
     * @return  integer
     *
     * @since   2.1.1
     */
    public function getOrderCount(): int
    {
        return (int) (new Order())->getOrdersCountByOrdercode();
    }

    /**
     * Number of tickets the visitor has put on the waiting list.
     *
     * @return  integer
     *
     * @since   2.1.1
     */
    public function getWaitingCount(): int
    {
        return (int) (new Order())->getOrdersCountByOrdercode('#__ticketstation_waitinglist');
    }

    /**
     * Totals for the full cart, calculated exactly like the "Winkelmand" block on the
     * upcoming view and the component's `updatecart` task: the subtotal is what is left
     * of the order total after removing the fees (so it is already net of any coupon).
     *
     * @param   integer  $itemCount  Number of tickets in the cart
     *
     * @return  object  The ready-to-print price strings subtotal, fees and total.
     *
     * @since   2.0.0
     */
    public function getTotals(int $itemCount): object
    {
        $ordercode = (int) Factory::getApplication()->getSession()->get('ordercode');
        $config    = (new Config())->getPartialConfig(['priceformat', 'valuta', 'variable_transcosts']);
        $amounts   = new getAmount();
        $functions = new TicketstationFunctions();

        $total = $itemCount > 0 ? (float) $amounts->_getAmount($ordercode) : 0.0;
        $fees  = $itemCount > 0 ? (float) $amounts->_getFees($ordercode) : 0.0;

        $format = static fn (float $price): string => $functions->showprice($config->priceformat ?? 1, $price, $config->valuta ?? '€');

        return (object) [
            'subtotal' => $format($total - $fees),
            'fees'     => $format($fees),
            'total'    => $format($total),
            // Transaction costs switched off in the component: the fees row must not be shown.
            'showFees' => (int) ($config->variable_transcosts ?? 0) !== 2,
        ];
    }

    /**
     * SEF URL to the component's cart view.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getCartUrl(): string
    {
        $itemid = TicketstationFunctions::getSiteItemid();

        return Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
    }
}
