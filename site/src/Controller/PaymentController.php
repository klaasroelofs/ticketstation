<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentMethod;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Payment Controller
 * @since  0.2.11
 */
class PaymentController extends BaseController
{
    private $ordercode;
    private $mollieconfig;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get Ordercode
        $this->ordercode = $jinput->get('ordercode', '0', 'int');

        // Get Mollie config from database
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        $this->mollieconfig = $db->loadObject();


    }

    function makepayment()
    {

        $jinput = Factory::getApplication()->getInput();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $this->ordercode = $jinput->get('ordercode', '0', 'int');

        $orderamount = (new getAmount)->_getAmount($this->ordercode);
        //$return_url = URI::root() . 'index.php?option=com_ticketstation&controller=payment&task=mollie';
        $return_url = URI::root() . 'index.php?option=com_ticketstation&controller=payment&task=mollie';
        $notify_url = URI::root() . 'index.php?option=com_ticketstation&controller=payment&task=IPNProcessPayment';

        if ($this->mollieconfig->test_mode == '1') {
            $api_key = $this->mollieconfig->api_key_test;
        } else {
            $api_key = $this->mollieconfig->api_key;
        }

        require_once JPATH_COMPONENT . "/vendor/autoload.php";

        $mollie = new MollieApiClient();
        $mollie->setApiKey($api_key);

        if (($orderamount != 0) && ($this->mollieconfig->bypass_mode == '0')) {

            $protocol = isset($_SERVER['HTTPS']) && strcasecmp('off', $_SERVER['HTTPS']) !== 0 ? "https" : "http";
            $hostname = $_SERVER['HTTP_HOST'];
            $path = dirname(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']);

            ## Force total of the order in this format:
            $ordertotal = number_format($orderamount, 2, '.', '');

            ## Start the API to process everything.
            $newPayment = new PaymentAPI((int)$this->ordercode);
            $transactions = $newPayment->checkTempTransactionAmount();

            ## If there are no transactions, insert now.
            if ($transactions == 0) {
                ## Get the user object:
                //$user =  JFactory::getUser();

                $query = $db->getQuery(true)
                    ->select($db->quoteName('userid'))
                    ->from($db->quoteName('#__ticketstation_orders'))
                    ->where($db->quoteName('ordercode') . ' = ' . $db->quote($this->ordercode));

                $db->setQuery($query);

                $userid = $db->loadResult();

                ## Let the API insert a new payment to the temp transaction table.
                ## insertTempTransaction now generates and returns a random token instead of using md5(ordercode).
                $return_token = $newPayment->insertTempTransaction($userid, md5($this->ordercode));

                ## If there is no new created temporary transaction, quit here.
                if (!$return_token) {
                    exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
                }
            } else {
                ## Transaction already exists for this ordercode; look it up to get the token
                $existing = $newPayment->getTempTransactionByOrdercode($this->ordercode);
                $return_token = $existing->return_token;

                ## Existing rows created before the return_token column existed (or otherwise
                ## missing a token) would otherwise send the customer to Mollie with an empty
                ## 'order=' redirect parameter. Backfill a fresh token onto that row instead.
                if (!$return_token) {
                    $return_token = $newPayment->refreshReturnToken($existing->id);

                    if (!$return_token) {
                        exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
                    }
                }
            }

            try {
                $payment = $mollie->payments->create(array(
                    "amount" => array(
                        "value"     => $ordertotal,
                        "currency"  => "EUR",
                    ),
                    "method"        => PaymentMethod::IDEAL,
                    "description"   => $this->mollieconfig->description . ' ' . $this->ordercode,
                    "redirectUrl"   => $return_url . '&order=' . $return_token,
                    "webhookUrl"    => $notify_url,
                    "locale"        => $this->mollieconfig->mollie_language,
                    "metadata"      => array(
                        "order_id"      => $this->ordercode,
                    ),
                ));
            } catch (\Mollie\Api\Exceptions\ApiException $e) {
                $this->log('Mollie API error while creating payment: ' . $e->getMessage());
                exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
            }

            History::log($this->ordercode, 'payment_initiated', 'Payment initiated at Mollie (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id]);

            if ($this->mollieconfig->change_payment_state == 1) {
                $pending_payment = $newPayment->paymentPendingState();
            }

            header("Location: " . $payment->getCheckoutUrl());

        } else {

            ## Amount is 0 or Mollie Config is in Bypass-mode
            ## Generate a random token for bypass authorization (not the guessable ordercode).
            $bypass_token = bin2hex(random_bytes(32));

            ## Store the bypass token in a session-side map (token => ordercode) so
            ## molliebypass() can verify it. Keyed by token rather than a single slot,
            ## so a second bypass-eligible checkout started in another tab (or before
            ## the first redirect is followed) doesn't clobber an earlier one still in
            ## flight under the same session.
            $bypassSession = Factory::getApplication()->getSession();
            $bypassTokens = $bypassSession->get('ticketstation.bypass_tokens', []);
            $bypassTokens[$bypass_token] = $this->ordercode;
            $bypassSession->set('ticketstation.bypass_tokens', $bypassTokens);

            ## Process the order
            if ($this->ProcessBypassMollie($this->ordercode)) {
                ## Bypass or amount = 0 >> redirect to mollieBypass with token
                $itemid = TicketstationFunctions::getSiteItemid();
                Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&controller=payment&task=molliebypass&token=' . $bypass_token . ($itemid ? '&Itemid=' . $itemid : '')));
            }

        }

        //$method = $mollie->methods->get(\Mollie\Api\Types\PaymentMethod::IDEAL, ["include" => "issuers"]);

    }

    function ProcessBypassMollie($ordercode)
    {
        $this->log('ProcessBypassMollie');
        $this->log('Sent ordercode: ' . $ordercode);

        $order_id = $ordercode;

        ## Start the API to process everything.
        $newPayment = new PaymentAPI((int)$order_id);

        ## Update the order state in the order table:
        $payment_state = $newPayment->updateOrder();

        ## set fees to 0. No payment was done as we bypass Mollie, therefore no fees applicable
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('fees') . ' = 0',
        );

        $conditions = array(
            $db->quoteName('ordercode') . ' = ' . $db->quote((int)$ordercode)
        );

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);
        $db->execute();

        ## if state is true, create the tickets:
        if ($payment_state == true) {
            $ticket_creator = $newPayment->createTickets();
        } else {
            $ticket_creator = false;
        }

        ## if tickets has been created:
        if ($ticket_creator == true && $this->mollieconfig->send_tickets_directly == 1) {
            $newPayment->sendTickets();
        }

        return true;
    }

    function mollie()
    {
        // Do NOT add checkToken() here — this is a redirect callback from Mollie,
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

        $this->log('Payment processed. Customer redirected to payment result screen');

        $itemid = TicketstationFunctions::getSiteItemid();
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&task=paymentresult.return&ordercode=' . $temp_transaction->ordercode . ($itemid ? '&Itemid=' . $itemid : '')));

    }

    function molliebypass()
    {

        $jinput = Factory::getApplication()->getInput();
        $session = Factory::getApplication()->getSession();

        ## Get the token from query parameter (generated in makepayment for bypass mode).
        $bypass_token = $jinput->getString('token', '');

        ## Look the token up in the session-side map (token => ordercode). Using
        ## hash_equals-safe array key lookup here is fine since PHP array key
        ## lookup is not a secret-comparison timing channel the way a direct
        ## string compare against a single stored value could be perceived to be;
        ## the token itself is still the 256-bit secret being checked for presence.
        $bypassTokens = $session->get('ticketstation.bypass_tokens', []);

        if ($bypass_token === '' || !isset($bypassTokens[$bypass_token])) {
            exit('Invalid or missing bypass authorization token');
        }

        $ordercode = $bypassTokens[$bypass_token];

        ## Single-use: remove only this token, leaving any other bypass-eligible
        ## checkout still in flight under this session untouched.
        unset($bypassTokens[$bypass_token]);
        $session->set('ticketstation.bypass_tokens', $bypassTokens);

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
        $session->clear('coupon');

        ## Marking this browser session as authorized (see mollie() above).
        $session->set('ticketstation.authorized_ordercode', (int) $ordercode);

        $itemid = TicketstationFunctions::getSiteItemid();
        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $ordercode . ($itemid ? '&Itemid=' . $itemid : '')));

    }

    function IPNProcessPayment()
    {
        // Do NOT add checkToken() here — this is a server-to-server webhook callback from Mollie,
        // not a browser form submission, so Joomla CSRF tokens do not apply.

        $this->log('IPN script called by Mollie');

        $response = Factory::getApplication()->getInput()->post->getArray();

        if ($this->mollieconfig->test_mode == '1') {
            $api_key = $this->mollieconfig->api_key_test;
        } else {
            $api_key = $this->mollieconfig->api_key;
        }

        require_once JPATH_COMPONENT . "/vendor/autoload.php";

        $mollie = new MollieApiClient();
        $mollie->setApiKey($api_key);

        try {
            $payment = $mollie->payments->get($response['id']);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $this->log('Mollie API error while fetching payment: ' . $e->getMessage());
            exit('Unable to retrieve payment from Mollie.');
        }

        $order_id = $payment->metadata->order_id;
        $this->log('Sent ordercode: ' . $order_id);


        ## add all information to a string:
        $payment_details = http_build_query($payment);

        ## Start the API to process everything.
        $newPayment = new PaymentAPI((int)$order_id);
        ## Look up temp transaction by ordercode (now returns the one with return_token).
        ## Webhooks only have the ordercode from metadata, not the token.
        $tmpTransaction = $newPayment->getTempTransactionByOrdercode($order_id);

        if (!$tmpTransaction) {
            $this->log('No temporary transaction in the database, script has been stopped.');
            exit('No temporary transaction in the database.');
        }

        ## Extract the return_token for use in updateTempTransaction calls.
        $return_token = $tmpTransaction->return_token;

        ## PAYMENT SUCCESFULL
        if ($payment->isPaid() == true) {
            ## Getting the amounts for this order.
            $amount = (new getAmount)->_getAmount((int)$order_id, 1);

            $netto_price = number_format($amount, 2, '.', '');
            $paid_price = $payment->amount->value;

            $this->log('Amount to be paid:' . $netto_price);
            $this->log('Amount paid by customer:' . $payment->amount->value);

            if ($netto_price == $paid_price) {
                ## Update the order state in the order table:
                $payment_state = $newPayment->updateOrder();

                ## Insert transaction details:
                $newPayment->saveTransaction($order_id, $tmpTransaction->userid, $payment_details, $paid_price, ucfirst($payment->method));

                ## if state is true, create the tickets:
                if ($payment_state == true) {
                    $ticket_creator = $newPayment->createTickets();
                    $this->log('Tickets created');
                }

                ## set temporary payment to 1 (paid order)
                $newPayment->updateTempTransaction($return_token, '1', $response['id']);

                ## if tickets has been created:
                if ($ticket_creator == true && $this->mollieconfig->send_tickets_directly == 1) {
                    $newPayment->sendTickets();
                    $this->log('Tickets sent');
                }

                ## Clearing the session:
                $newPayment->clearSession();
                exit();
            } else {
                ## set temporary payment to 2 (wrong amount)
                $newPayment->updateTempTransaction($return_token, '2');
                exit();
            }
        } elseif ($payment->isOpen() == true) {
            $newPayment->updateTempTransaction($return_token, '3');
            exit();
        } elseif ($payment->isPending() == true) {
            $newPayment->updateTempTransaction($return_token, '4');
            exit();
        } elseif ($payment->isFailed() == true) {
            $newPayment->updateTempTransaction($return_token, '5');
            History::log($order_id, 'payment_failed', 'Payment failed at Mollie (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } elseif ($payment->isCanceled() == true) {
            $newPayment->updateTempTransaction($return_token, '5');
            History::log($order_id, 'payment_cancelled', 'Payment cancelled by customer (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } elseif ($payment->isExpired() == true) {
            $newPayment->updateTempTransaction($return_token, '5');
            History::log($order_id, 'payment_expired', 'Payment expired before completion (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } else {
            $newPayment->updateTempTransaction($return_token, '5');
            exit();
        }

        ## set temporary payment to 0 (payment is returning without state)
        $newPayment->updateTempTransaction($return_token, '0');
        exit();
    }

    //TODO: werkend maken
    private function log($text)
    {

        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.file');

        if ($text != '') {
            $msg = date("Y-m-d H:i:s", time()) . ' - ' . $text . "\n";
        } else {
            $msg = "\n";
        }

        ## Open log file for writing to it:
        $logfile = fopen(JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/log/mollie_log.txt', "a");
        ## Add the new log:
        fputs($logfile, $msg);
        ## Close the log file again:
        fclose($logfile);

        return true;
    }

}
