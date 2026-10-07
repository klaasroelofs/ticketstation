<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentStarter;
use Ticketstation\Component\Ticketstation\Site\Model\CartModel;

/**
 * What the combined checkout page shows of the cart: the lines with their totals, and the payment
 * block under the form. The page and the background requests that refresh it (after a change of
 * quantity or a coupon) get both from here, so they cannot differ.
 *
 * @since  2.26.0
 */
final class CartPage
{
    /**
     * The cart of the current session, with what the page needs to show it and to pay it.
     *
     * @return  object
     */
    public static function data(): object
    {
        $cart      = new CartModel;
        $config    = $cart->getConfig();
        $ordercode = (int) Factory::getApplication()->getSession()->get('ordercode');

        return (object) [
            'ordercode'    => $ordercode,
            'items'        => $cart->getData(),
            'waiters'      => $cart->getWaiters(),
            'coords'       => $cart->getExtData(),
            'config'       => $config,
            'totals'       => OrderTotals::get($ordercode, true),
            'customerNote' => $config->show_remark_field == 1 ? $cart->getCustomerNote() : '',
            'paymentsOn'   => Shop::paymentsOn(),
            'methods'      => PaymentStarter::methods(),
        ];
    }

    /**
     * Whether there is nothing left to order, nor to put on a waiting list.
     */
    public static function isEmpty(object $data): bool
    {
        return !$data->items && !$data->waiters;
    }

    /**
     * The cart lines with their totals.
     */
    public static function renderLines(object $data): string
    {
        return self::layout('cart_lines', [
            'items'     => $data->items,
            'waiters'   => $data->waiters,
            'coords'    => $data->coords,
            'config'    => $data->config,
            'totals'    => $data->totals,
            'ordercode' => $data->ordercode,
        ]);
    }

    /**
     * What sits between the form and the pay button: payment methods, terms, notes and the button.
     */
    public static function renderPay(object $data, string $method = ''): string
    {
        return self::layout('checkout_pay', [
            'hasItems'   => count($data->items) > 0,
            'totals'     => $data->totals,
            'config'     => $data->config,
            'paymentsOn' => $data->paymentsOn,
            'methods'    => $data->methods,
            'selected'   => $method,
        ]);
    }

    /**
     * Everything the page replaces after a change in the background: the cart lines and the
     * payment block, or, when the cart turned out empty, where to go instead.
     *
     * @return  array
     */
    public static function fragments(): array
    {
        $data = self::data();

        if (self::isEmpty($data))
        {
            $itemid = TicketstationFunctions::getSiteItemid();

            return [
                'empty'    => true,
                'redirect' => Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''), false),
            ];
        }

        return [
            'empty' => false,
            'lines' => self::renderLines($data),
            'pay'   => self::renderPay($data),
        ];
    }

    /**
     * The messages that were queued during this request, as alerts. Taking them out of the queue
     * keeps them from showing up again on the next page.
     *
     * @param   string[]  $types  the kinds of message to pass on; the others are dropped
     *
     * @return  string
     */
    public static function messages(array $types = ['error', 'warning', 'message', 'info', 'notice', 'success']): string
    {
        $classes = ['error' => 'danger', 'warning' => 'warning'];
        $html    = '';

        foreach (Factory::getApplication()->getMessageQueue(true) as $message)
        {
            if (in_array($message['type'], $types, true))
            {
                $html .= '<div class="ts-alert ts-alert--' . ($classes[$message['type']] ?? 'success') . '" role="alert">' . $message['message'] . '</div>';
            }
        }

        return $html;
    }

    /**
     * Answers a background request with the refreshed page parts, and ends the request.
     *
     * @param   bool      $ok     whether the change was made (the coupon was accepted)
     * @param   string[]  $types  the kinds of queued message to show
     *
     * @return  void
     */
    public static function respond(bool $ok = true, array $types = ['error', 'warning']): void
    {
        $app      = Factory::getApplication();
        $messages = self::messages($types);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode(array_merge(['ok' => $ok, 'messages' => $messages], self::fragments()));

        $app->close();
    }

    private static function layout(string $layout, array $displayData): string
    {
        return LayoutHelper::render($layout, $displayData, null, ['component' => 'com_ticketstation', 'client' => 0]);
    }
}
