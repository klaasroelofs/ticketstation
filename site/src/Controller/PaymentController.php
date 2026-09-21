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

            try {
                $payment = $mollie->payments->create(array(
                    "amount" => array(
                        "value"     => $ordertotal,
                        "currency"  => "EUR",
                    ),
                    "method"        => PaymentMethod::IDEAL,
                    "description"   => $this->mollieconfig->description . ' ' . $this->ordercode,
                    "redirectUrl"   => $return_url . '&order=' . md5($this->ordercode),
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

            ## Start the API to process everything.
            $newPayment = new PaymentAPI((int)$this->ordercode);
            $transactions = $newPayment->checkTempTransactionAmount(md5($this->ordercode));

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

                ## Let the API insert a new payment to the temp transaction table:
                $temp_transaction = $newPayment->insertTempTransaction($userid, md5($this->ordercode));

                ## If there is no new created temporary transaction, quit here.
                if (!$temp_transaction) {
                    exit(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
                }
            }

            if ($this->mollieconfig->change_payment_state == 1) {
                $pending_payment = $newPayment->paymentPendingState();
            }

            header("Location: " . $payment->getCheckoutUrl());

        } else {

            ## Amount is 0 or Mollie Config is in Bypass-mode
            ## Process the order
            if ($this->ProcessBypassMollie($this->ordercode)) {
                ## Bypass or amount = 0 >> redirect to mollieBypass
                header("Location: index.php?option=com_ticketstation&controller=payment&task=molliebypass&order=" . $this->ordercode);
                exit();
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
        $newPayment = new paymentAPI((int)$order_id);

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

        $jinput = Factory::getApplication()->getInput();
        $tmp_transaction_id = $jinput->getString('order', '');

        if ($tmp_transaction_id == '') {
            exit('No tmp transaction has been sent back -- looks more like an attack');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('transaction_number') . ' = ' . $db->quote($tmp_transaction_id));

        $db->setQuery($query);
        $temp_transaction = $db->loadObject();

        ## Start the API to process everything.
        $newPayment = new paymentAPI((int)$temp_transaction->ordercode);
        ## Clearing the session:
        $newPayment->clearSession();

        ## Marking this browser session as authorized to view/download this order's tickets.
        ## Ordercodes are short and sequential, so without this, guessing one would be enough
        ## to reach someone else's payment result page or ticket download.
        Factory::getApplication()->getSession()->set('ticketstation.authorized_ordercode', (int) $temp_transaction->ordercode);

        $this->log('Payment processed. Customer redirected to payment result screen');

        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&task=paymentresult.return&ordercode=' . $temp_transaction->ordercode));

    }

    function molliebypass()
    {

        $jinput = Factory::getApplication()->getInput();
        $ordercode = $jinput->getString('order', '');

        ##Check if order is processed
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . " = " . (int)$ordercode);

        $db->setQuery($query);
        $order = $db->loadObjectList();


        ## Clearing the session:
        $session = Factory::getApplication()->getSession();
        $session->clear('ordercode');
        $session->clear('coupon');

        ## Marking this browser session as authorized (see mollie() above).
        $session->set('ticketstation.authorized_ordercode', (int) $ordercode);

        Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $ordercode));

    }

    //TODO: functie gemaakt om ticketcreator te testen
    function maketickets()
    {
        $jinput = Factory::getApplication()->getInput();
        $ordercode = $jinput->get('ordercode', '0', 'int');
        $newPayment = new paymentAPI((int)$ordercode);

        $newPayment->createTickets();

    }

    //TODO: functie gemaakt om sendonpayment te testen
    function sendticketmail()
    {
        $jinput = Factory::getApplication()->getInput();
        $ordercode = $jinput->get('ordercode', '0', 'int');
        $newPayment = new paymentAPI((int)$ordercode);

        $newPayment->sendTickets();

    }

    function IPNProcessPayment()
    {
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
        $newPayment = new paymentAPI((int)$order_id);
        $tmpTransaction = $newPayment->getTempTransactionResult(md5($order_id));

        if (!$tmpTransaction) {
            $this->log('No temporary transaction in the database, script has been stopped.');
            exit('No temporary transaction in the database.');
        }

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
                $newPayment->updateTempTransaction(md5($order_id), '1', $response['id']);

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
                $newPayment->updateTempTransaction(md5($order_id), '2');
                exit();
            }
        } elseif ($payment->isOpen() == true) {
            $newPayment->updateTempTransaction(md5($order_id), '3');
            exit();
        } elseif ($payment->isPending() == true) {
            $newPayment->updateTempTransaction(md5($order_id), '4');
            exit();
        } elseif ($payment->isFailed() == true) {
            $newPayment->updateTempTransaction(md5($order_id), '5');
            History::log($order_id, 'payment_failed', 'Payment failed at Mollie (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } elseif ($payment->isCanceled() == true) {
            $newPayment->updateTempTransaction(md5($order_id), '5');
            History::log($order_id, 'payment_cancelled', 'Payment cancelled by customer (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } elseif ($payment->isExpired() == true) {
            $newPayment->updateTempTransaction(md5($order_id), '5');
            History::log($order_id, 'payment_expired', 'Payment expired before completion (transaction ' . $payment->id . ')', ['mollie_id' => $payment->id, 'method' => $payment->method]);
            exit();
        } else {
            $newPayment->updateTempTransaction(md5($order_id), '5');
            exit();
        }

        ## set temporary payment to 0 (payment is returning without state)
        $newPayment->updateTempTransaction(md5($order_id), '0');
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
