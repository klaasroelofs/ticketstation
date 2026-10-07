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
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentLog;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentService;
use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentStarter;
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
        ## The order itself is checked and started in PaymentStarter, which the combined checkout
        ## page uses as well.
        $this->ordercode = Factory::getApplication()->getInput()->get('ordercode', '0', 'int');

        PaymentStarter::start((int) $this->ordercode, Factory::getApplication()->getInput()->post->getCmd('method', ''));
    }

    function processFreeOrder($ordercode)
    {
        return PaymentStarter::processFreeOrder($ordercode);
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
