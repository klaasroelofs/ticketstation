<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TestData;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

defined('_JEXEC') or die;

/**
 * Takes a customer from the order on to the payment: to the payment provider, or, for an order of
 * nothing, straight to the result page. Used by the payment page and by the combined checkout
 * page, which pays in the same request that saves the customer's details.
 *
 * @since  2.26.0
 */
final class PaymentStarter
{
    /**
     * The payment methods to choose from: only when the provider offers more than one.
     *
     * @return  PaymentMethodOption[]
     */
    public static function methods(): array
    {
        $provider = ProviderRegistry::active();

        if ($provider instanceof MethodAwareInterface)
        {
            $methods = $provider->getCheckoutMethods(ProviderRegistry::currency());

            if (count($methods) > 1)
            {
                return $methods;
            }
        }

        return [];
    }

    /**
     * Starts the payment of an order. Always ends the request: with a redirect to the provider or
     * to the result page, or back to where the customer can fix what stopped it.
     *
     * @param   int          $ordercode  the order's final ordercode
     * @param   string|null  $method     the payment method the customer chose, if any
     *
     * @return  void
     */
    public static function start(int $ordercode, ?string $method): void
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        ## Test mode is for logged-in staff only: a visitor who still has a cart from before the
        ## mode was switched on can't pay it.
        if (Shop::isClosed())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_NOT_AVAILABLE'), 'error');
            $app->redirect(self::url('upcoming'));
        }

        ## Something left to pay? A retry after a failed payment may come after the Ticketcleaner
        ## removed the order, and an empty order must not go through as a free one.
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->where($db->quoteName('paid') . ' != 1');
        $db->setQuery($query);

        if ((int) $db->loadResult() === 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ORDER_NO_LONGER_AVAILABLE'), 'error');
            $app->redirect(self::url('upcoming'));
        }

        $orderamount = OrderTotals::get($ordercode, true)->total;

        ## With online payments switched off only an order of nothing goes through; paid tickets
        ## are sold at the box office. Covers a cart filled before the switch, and payment links.
        if ($orderamount > 0 && !Shop::paymentsOn())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_ORDER'), 'error');
            $app->redirect(self::url('cart'));
        }

        ## An order is paid in the mode it was made in: a test order never takes live money, and a live
        ## order is never "paid" with test money (the shop was switched between test and live meanwhile).
        if ($orderamount != 0 && TestData::ofOrder($ordercode) !== TestData::mode())
        {
            $app->enqueueMessage(Text::_(TestData::mode() === 1 ? 'COM_TICKETSTATION_TESTMODE_ORDER_IS_LIVE' : 'COM_TICKETSTATION_TESTMODE_ORDER_IS_TEST'), 'error');
            $app->redirect(self::url('cart'));
        }

        ## In test mode a provider without a test environment would take real money.
        if ($orderamount != 0 && Shop::testPaymentsBlocked())
        {
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_TESTMODE_NO_TEST_ENVIRONMENT', ProviderRegistry::active()->getTitle()), 'error');
            $app->redirect(self::url('cart'));
        }

        if ($orderamount != 0)
        {
            ## Only set up the provider when the order actually goes there: a provider without
            ## credentials would break free orders on a site without them.
            try
            {
                $provider    = ProviderRegistry::active();
                $checkoutUrl = PaymentService::start($provider, $ordercode, (float) $orderamount, self::chosenMethod($provider, $method));
            }
            catch (\RuntimeException $e)
            {
                PaymentLog::add('Error while creating payment: ' . $e->getMessage());
                exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
            }

            header('Location: ' . $checkoutUrl);

            return;
        }

        ## Amount is 0: the order never goes to a payment provider.
        ## Generate a random token for the redirect (not the guessable ordercode).
        $freeToken = bin2hex(random_bytes(32));

        ## Store the token in a session-side map (token => ordercode) so freeorder() can verify it.
        ## Keyed by token rather than a single slot, so a second free checkout started in another
        ## tab (or before the first redirect is followed) doesn't clobber an earlier one still in
        ## flight under the same session.
        $session    = $app->getSession();
        $freeTokens = $session->get('ticketstation.free_order_tokens', []);

        $freeTokens[$freeToken] = $ordercode;
        $session->set('ticketstation.free_order_tokens', $freeTokens);

        ## Process the order
        if (self::processFreeOrder($ordercode))
        {
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&controller=payment&task=freeorder&token=' . $freeToken . ($itemid ? '&Itemid=' . $itemid : '')));
        }
    }

    /**
     * Completes an order of nothing: marks it paid and sends the tickets.
     *
     * @param   int  $ordercode
     *
     * @return  bool
     */
    public static function processFreeOrder($ordercode): bool
    {
        PaymentLog::add('processFreeOrder');
        PaymentLog::add('Sent ordercode: ' . $ordercode);

        ## Start the API to process everything.
        $newPayment = new PaymentAPI((int) $ordercode);

        ## Update the order state in the order table:
        $paymentState = $newPayment->updateOrder();

        ## No payment was made as the order is free, so no transaction costs either (the invoice
        ## reads them from the order).
        OrderTotals::capture($ordercode, false);

        ## if state is true, create the tickets:
        $ticketCreator = $paymentState == true ? $newPayment->createTickets() : false;

        ## if tickets has been created:
        if ($ticketCreator == true && PaymentService::sendTicketsDirectly())
        {
            $newPayment->sendTickets();
        }

        return true;
    }

    /**
     * The payment method the customer chose, when it is one the provider offers; null otherwise
     * (no choice made, or a method that isn't offered), which leaves the choice to the provider.
     */
    private static function chosenMethod($provider, ?string $method): ?string
    {
        if ($method === null || $method === '' || !$provider instanceof MethodAwareInterface)
        {
            return null;
        }

        foreach ($provider->getCheckoutMethods(ProviderRegistry::currency()) as $option)
        {
            if ($option->id === $method)
            {
                return $method;
            }
        }

        return null;
    }

    private static function url(string $view): string
    {
        $itemid = TicketstationFunctions::getSiteItemid();

        return Route::_('index.php?option=com_ticketstation&view=' . $view . ($itemid ? '&Itemid=' . $itemid : ''), false);
    }
}
