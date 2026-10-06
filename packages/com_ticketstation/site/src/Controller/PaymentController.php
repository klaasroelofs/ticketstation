<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Payment\MethodAwareInterface;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentLog;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentService;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * Ticketstation Payment Controller
 *
 * Takes the customer to the payment provider and receives what comes back: the customer (return)
 * and the provider's server (webhook). What a payment means for the order is decided in
 * PaymentService; the provider itself is behind PaymentProviderInterface.
 *
 * @since  0.2.11
 */
class PaymentController extends BaseController
{
    private $ordercode;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();

        // Get Ordercode
        $this->ordercode = $jinput->get('ordercode', '0', 'int');
    }

    function makepayment()
    {

        $jinput = Factory::getApplication()->getInput();
        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Test mode is for logged-in staff only: a visitor who still has a cart from before the
        ## mode was switched on can't pay it.
        if (Shop::isClosed()) {
            $itemid = TicketstationFunctions::getSiteItemid();
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_NOT_AVAILABLE'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));
        }

        $this->ordercode = $jinput->get('ordercode', '0', 'int');

        ## Something left to pay? A retry after a failed payment may come after the Ticketcleaner
        ## removed the order, and an empty order must not go through as a free one.
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $this->ordercode)
            ->where($db->quoteName('paid') . ' != 1');
        $db->setQuery($query);

        if ((int) $db->loadResult() === 0) {
            $itemid = TicketstationFunctions::getSiteItemid();
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ORDER_NO_LONGER_AVAILABLE'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''), false));
        }

        $orderamount = OrderTotals::get($this->ordercode, true)->total;

        ## With online payments switched off only an order of nothing goes through; paid tickets
        ## are sold at the box office. Covers a cart filled before the switch, and payment links.
        if ($orderamount > 0 && !Shop::paymentsOn()) {
            $itemid = TicketstationFunctions::getSiteItemid();
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_ORDER'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''), false));
        }

        if ($orderamount != 0) {

            ## Only set up the provider when the order actually goes there: a provider without
            ## credentials would break free orders on a site without them.
            try {
                $provider    = ProviderRegistry::active();
                $checkoutUrl = PaymentService::start($provider, (int) $this->ordercode, (float) $orderamount, $this->chosenMethod($provider));
            } catch (\RuntimeException $e) {
                PaymentLog::add('Error while creating payment: ' . $e->getMessage());
                exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
            }

            header("Location: " . $checkoutUrl);

        } else {

            ## Amount is 0: the order never goes to a payment provider.
            ## Generate a random token for the redirect (not the guessable ordercode).
            $free_token = bin2hex(random_bytes(32));

            ## Store the token in a session-side map (token => ordercode) so freeorder() can
            ## verify it. Keyed by token rather than a single slot, so a second free checkout
            ## started in another tab (or before the first redirect is followed) doesn't
            ## clobber an earlier one still in flight under the same session.
            $freeSession = Factory::getApplication()->getSession();
            $freeTokens = $freeSession->get('ticketstation.free_order_tokens', []);
            $freeTokens[$free_token] = $this->ordercode;
            $freeSession->set('ticketstation.free_order_tokens', $freeTokens);

            ## Process the order
            if ($this->processFreeOrder($this->ordercode)) {
                ## Redirect to freeorder with the token
                $itemid = TicketstationFunctions::getSiteItemid();
                Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&controller=payment&task=freeorder&token=' . $free_token . ($itemid ? '&Itemid=' . $itemid : '')));
            }

        }

    }

    function processFreeOrder($ordercode)
    {
        PaymentLog::add('processFreeOrder');
        PaymentLog::add('Sent ordercode: ' . $ordercode);

        $order_id = $ordercode;

        ## Start the API to process everything.
        $newPayment = new PaymentAPI((int)$order_id);

        ## Update the order state in the order table:
        $payment_state = $newPayment->updateOrder();

        ## No payment was made as the order is free, so no transaction costs either (the
        ## invoice reads them from the order).
        OrderTotals::capture($ordercode, false);

        ## if state is true, create the tickets:
        if ($payment_state == true) {
            $ticket_creator = $newPayment->createTickets();
        } else {
            $ticket_creator = false;
        }

        ## if tickets has been created:
        if ($ticket_creator == true && PaymentService::sendTicketsDirectly()) {
            $newPayment->sendTickets();
        }

        return true;
    }

    /**
     * The customer comes back from the payment provider, with the token of the payment attempt.
     */
    function return()
    {
        // Do NOT add checkToken() here — this is a redirect callback from the payment provider,
        // not a browser form submission, so Joomla CSRF tokens do not apply.

        $jinput = Factory::getApplication()->getInput();
        $return_token = $jinput->getString('order', '');

        if ($return_token == '') {
            exit('No return token has been sent back -- looks more like an attack');
        }

        ## Start the API to process everything.
        $newPayment = new PaymentAPI(0);

        ## Look up the temp transaction using the secure random token (not guessable md5).
        $temp_transaction = $newPayment->getTempTransactionResult($return_token);

        if (!$temp_transaction) {
            exit('Invalid or expired payment token');
        }

        ## Clear session and mark this browser as authorized for this order.
        $newPayment = new PaymentAPI((int)$temp_transaction->ordercode);
        $newPayment->clearSession();

        ## Marking this browser session as authorized to view/download this order's tickets.
        Factory::getApplication()->getSession()->set('ticketstation.authorized_ordercode', (int) $temp_transaction->ordercode);

        PaymentLog::add('Payment processed. Customer redirected to payment result screen');

        $itemid = TicketstationFunctions::getSiteItemid();
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&task=paymentresult.return&ordercode=' . $temp_transaction->ordercode . ($itemid ? '&Itemid=' . $itemid : '')));

    }

    /**
     * The return address of payments that were started before provider-neutral addresses: always
     * Mollie. Payments are still on their way back to it, so it stays.
     */
    function mollie()
    {
        $this->return();
    }

    function freeorder()
    {

        $jinput = Factory::getApplication()->getInput();
        $session = Factory::getApplication()->getSession();

        ## Get the token from query parameter (generated in makepayment for a free order).
        $free_token = $jinput->getString('token', '');

        ## Look the token up in the session-side map (token => ordercode). Using
        ## hash_equals-safe array key lookup here is fine since PHP array key
        ## lookup is not a secret-comparison timing channel the way a direct
        ## string compare against a single stored value could be perceived to be;
        ## the token itself is still the 256-bit secret being checked for presence.
        $freeTokens = $session->get('ticketstation.free_order_tokens', []);

        if ($free_token === '' || !isset($freeTokens[$free_token])) {
            exit('Invalid or missing order token');
        }

        $ordercode = $freeTokens[$free_token];

        ## Single-use: remove only this token, leaving any other free checkout still in
        ## flight under this session untouched.
        unset($freeTokens[$free_token]);
        $session->set('ticketstation.free_order_tokens', $freeTokens);

        ## Check if order is processed
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . " = " . (int)$ordercode);

        $db->setQuery($query);
        $order = $db->loadObjectList();

        ## Clearing the session:
        $session->clear('ordercode');

        ## Marking this browser session as authorized (see return() above).
        $session->set('ticketstation.authorized_ordercode', (int) $ordercode);

        $itemid = TicketstationFunctions::getSiteItemid();
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $ordercode . ($itemid ? '&Itemid=' . $itemid : '')));

    }

    /**
     * The payment provider's server reports a payment. The provider is named in the address
     * (&provider=); an unknown one gets a plain 404. A provider that is switched off still gets
     * its webhooks: payments, refunds and chargebacks of earlier orders are reported there.
     */
    function webhook()
    {
        // Do NOT add checkToken() here — this is a server-to-server webhook callback from the
        // payment provider, not a browser form submission, so Joomla CSRF tokens do not apply.
        // The provider checks the call is genuine itself.

        $provider = ProviderRegistry::get(Factory::getApplication()->getInput()->getCmd('provider', ''));

        $this->processWebhook($provider);
    }

    /**
     * The webhook address of payments that were started before provider-neutral addresses:
     * always Mollie. Payments are still on their way back to it, and refunds and chargebacks of
     * earlier payments are reported there too, so it stays.
     */
    function IPNProcessPayment()
    {
        $this->processWebhook(ProviderRegistry::get('mollie'));
    }

    /**
     * The payment method the customer chose on the payment page, when it is one the provider
     * offers; null otherwise (a form without a choice, or a method that isn't offered), which
     * leaves the choice to the provider.
     */
    private function chosenMethod($provider): ?string
    {
        $method = Factory::getApplication()->getInput()->post->getCmd('method', '');

        if ($method === '' || !$provider instanceof MethodAwareInterface) {
            return null;
        }

        foreach ($provider->getCheckoutMethods(ProviderRegistry::currency()) as $option) {
            if ($option->id === $method) {
                return $method;
            }
        }

        return null;
    }

    private function processWebhook($provider)
    {
        if ($provider === null) {
            http_response_code(404);
            exit();
        }

        $app = Factory::getApplication();

        [$status, $body] = PaymentService::handleWebhook($provider, $app->getInput(), (string) file_get_contents('php://input'));

        if ($status !== 200) {
            http_response_code($status);
        }

        exit($body);
    }

}
