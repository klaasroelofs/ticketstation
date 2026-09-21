<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use stdClass;

defined('_JEXEC') or die;

class paymentAPI
{
    private $ordercode;
    private $orderCount;
    private $orderData;

    function __construct($eid)
    {
        ## Setting the $eid as var
        $this->ordercode = $eid;
    }

    ## inserting the temporary transaction to the temporary transaction tbale.
    public function insertTempTransaction($userid, $transid)
    {
        $transaction = new stdClass();
        $transaction->transaction_number = $transid;
        $transaction->userid = (int)$userid;
        $transaction->ordercode = (int)$this->ordercode;
        $transaction->processed = 0;

        // Insert the object into the temporary transaction table.
        $result = Factory::getContainer()->get('DatabaseDriver')
            ->insertObject('#__ticketstation_transactions_temp', $transaction);

        if ($result == true) {
            return true;
        }
        else {
            return false;
        }
    }

    ## update the temporary transaction to "processed"
    ## Needs to feeded with the unique transaction ID
    ## This transaction ID has been entered by yourself with the saveTransaction() functionality.
    public function updateTempTransaction($transid, $state = 1, $message = 1)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('processed') . ' = ' . $db->quote((int)$state),
            $db->quoteName('message') . ' = ' . $db->quote($message)
        );

        $conditions = array(
            $db->quoteName('transaction_number') . ' = ' . $db->quote($transid)
        );

        $query->update($db->quoteName('#__ticketstation_transactions_temp'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        ## When query goes wrong.. Show message with error.
        if (!$db->execute()) {
            return false;
        }
        else {
            return true;
        }
    }

    ## Check the count of the temporary transactions
    ## Every transaction maybe entered once. It will return false if there are more than 0 transactions.
    public function checkTempTransactionAmount($intTrxId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_transactions_temp'));
        $query->where($db->quoteName('transaction_number') . ' = ' . $db->quote($intTrxId));

        $db->setQuery($query);
        $temp_transaction = $db->loadObjectList();

        return count($temp_transaction);
    }

    ## Check the count of the temporary transactions
    ## Every transaction maybe entered once. It will return false if there are more than 0 transactions.
    public function getTempTransactionResult($intTrxId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_transactions_temp'));
        $query->where($db->quoteName('transaction_number') . ' = ' . $db->quote($intTrxId));

        $db->setQuery($query);

        return $db->loadObject();
    }

    ## save the transaction details to the transaction table
    ## transaction ID 	= VARCHAR(60) --> MUST be sanitized by yourself!
    ## amount 			= DOUBLE --> 0.90 / 12.34 are valid doubles
    ## type 			= VARCHAR(50) --> type transaction information (EG: PayPal, IDEAL, Sofort)
    ## details 			= TEXT (may have unlimited transaction and must be sanitized by yourself!)

    public function saveTransaction($transid, $userid, $details, $amount, $type)
    {
        $transaction = new stdClass();
        $transaction->transid = $transid;
        $transaction->userid = (int)$userid;
        $transaction->details = $details;
        $transaction->amount = $amount;
        $transaction->type = $type;
        $transaction->orderid = (int)$this->ordercode;

        $result = Factory::getContainer()->get('DatabaseDriver')
            ->insertObject('#__ticketstation_transactions', $transaction);

        if ($result == true) {
            History::log($this->ordercode, 'transaction_created', 'Transaction created with amount ' . $amount . ', payment method: ' . $type, ['amount' => $amount, 'method' => $type]);
            return true;
        }
        else {
            return false;
        }

    }

    ## update the order details in the ticketbox, fed by the ordercode.
    public function paymentPendingState()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('paid') . ' = ' . $db->quote('3'),
            $db->quoteName('published') . ' = 1'
        );

        $conditions = array(
            $db->quoteName('ordercode') . ' = ' . $db->quote((int)$this->ordercode)
        );

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        if ($db->execute()) {
            return true;
        }
        else {
            return false;
        }
    }

    ## update the order details in the ticketbox, feeded by the ordercode.
    public function updateOrder()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('paid') . ' = 1',
            $db->quoteName('published') . ' = 1'
        );

        $conditions = array(
            $db->quoteName('ordercode') . ' = ' . $db->quote((int)$this->ordercode)
        );

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ($result) {
            History::log($this->ordercode, 'order_paid', 'New order status: Paid', ['status' => 'paid']);
        }

        return $result;
    }

    ## Create the tickets for this client, feeded by the ordercode.
    public function createTickets()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int)$this->ordercode));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        ## Loop through the items to create the tickets:
        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $row = $data[$i];

            if (isset($row->orderid)) {
                ## Create the tickets for this order:
                $creator = new ticketcreator((int)$row->orderid);
                $creator->doPDF();
            }
        }

        if (count($data)) {
            History::log($this->ordercode, 'tickets_generated', 'Tickets generated');
        }

        return true;
    }

    public function getPaymentState()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(array('COUNT(orderid) AS total'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . " = " . $db->quote($this->ordercode))
            ->where($db->quoteName('paid') . ' != 1');

        $db->setQuery($query);
        $status = $db->loadObject();

        if ($status->total > 0) {
            return false;
        }

        return true;
    }

    ## sending the tickets to the customer fed by the ordercode
    public function sendTickets()
    {

        ## Sending the ticket immediatly to the client.
        $creator = new SendonPayment((int)$this->ordercode);
        $creator->send();

        $client = $this->getUserInformation();
        $email  = is_object($client) ? $client->emailaddress : null;
        History::log($this->ordercode, 'tickets_sent', 'Tickets sent to ' . ($email ?: 'customer'), ['email' => $email]);

        ## If invoicing is set to send automatically, generate and email it right alongside
        ## the tickets - this is the single shared point every payment-completion route
        ## (Mollie, Complete Order, Reservation) already goes through.
        $config = $this->getConfig();

        if ($config->send_invoice == 1)
        {
            (new Invoice)->create((int) $this->ordercode);
        }

        return true;
    }

    ## clear the session for Ticketstation
    public function clearSession()
    {
        ## Removing the session, it's not needed anymore.
        $app = Factory::getApplication();
        $session = $app->getSession();
        $session->clear('ordercode');
        $session->clear('coupon');

        return true;
    }

    ## send the confirmation to the client.
    public function sendConfirmation()
    {
        $sendconfirmation = new confirmation((int)$this->ordercode);
        $sendconfirmation->doConfirm();
        $sendconfirmation->doSend();

        $client = $this->getUserInformation();
        $email  = is_object($client) ? $client->emailaddress : null;
        History::log($this->ordercode, 'confirmation_sent', 'Confirmation sent to ' . ($email ?: 'customer'), ['email' => $email]);

        return true;
    }

    ## Creating an order list which can be used in emails.
    public function getWaitingList()
    {
        $config = $this->getConfig();

        $select = array('o.*', 't.*', 'e.eventname', 'c.*', 't.ticketdate', 't.starttime', 't.location',
            't.locationinfo', 'e.groupname', 't.eventcode', 't.ticketprice AS price');

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_waitinglist', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int)$this->ordercode));
        $query->group('o.id');

        $db->setQuery($query);
        $this->orderData = $db->loadObjectList();
        $this->orderCount = count($this->orderData);

        $orders = '<ul>';

        for ($i = 0, $n = count($this->orderData); $i < $n; $i++) {

            $row = $this->orderData[$i];

            $price = (new TicketstationFunctions)->showprice($config->priceformat, $row->ticketprice, $config->valuta);
            $ticketdate = date($config->dateformat, strtotime($row->ticketdate));

            $orders .= '<li>[ ' . $row->id . ' ] - [ ' . $ticketdate . ' ] - <strong>' . $row->ticketname . '</strong> [ ' . $price . ' ]</li>';
        }

        $orders .= '</ul>';

        return $orders;
    }

    ## Creating an order list which can be used in emails.
    public function getOrderList($pro_installed = 0)
    {

        $config = $this->getConfig();

        if ($config->pro_installed == 1) {
            $select = array('o.*', 't.*', 'e.eventname', 'c.*', 't.startdate',
                'o.paid', 'e.eventcode', 't.ticketcode',
                't.ticketprice AS price', 'ext.seatid', 'ext.row_name');
        }
        else {
            $select = array('o.*', 't.*', 'e.eventname', 'c.*', 't.startdate', 'o.paid', 'e.eventcode', 't.ticketcode', 't.ticketprice AS price');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');

        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');

        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int)$this->ordercode));
        $query->group('o.orderid');

        $db->setQuery($query);

        $this->orderData = $db->loadObjectList();
        $this->orderCount = count($this->orderData);

        $orders = '<ul>';

        for ($i = 0, $n = count($this->orderData); $i < $n; $i++) {
            $row = $this->orderData[$i];

            $price = $row->ticketprice;
            $ticketdate = date($config->dateformat, strtotime($row->startdate));

            if ($row->seatid == '') {
                $orders .= '<li>[ ' . $row->orderid . ' ] - [ ' . $ticketdate . ' ] - <strong>' . $row->ticketname . '</strong> [ ' . $price . ' ]</li>';
            }
            else {
                $orders .= '<li>[ ' . $row->orderid . ' ] - [ ' . $ticketdate . ' ] - <strong>' . $row->ticketname . '</strong> [ ' . $price . ' ]
					 [ ' . Text::_('COM_TICKETSTATION_SEAT_NR') . ' ' . $row->row_name . $row->seatid . ' ]</li>';
            }
        }

        $orders .= '</ul>';

        return $orders;
    }

    public function getPaymentStateForEmails()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('COUNT(orderid) AS total'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int)$this->ordercode));
        $query->where($db->quoteName('paid') . ' = ' . $db->quote(0));

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function getOrderCount()
    {
        return $this->orderCount;
    }

    public function getOrderData()
    {
        return $this->orderData;
    }

    public function getUserInformation()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('c.*'));
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int)$this->ordercode));
        $query->group('o.orderid');

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = 1');

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function generatePaymentLink($ordercode = null)
    {
        if (!$ordercode) {
            $ordercode = $this->ordercode;
        }

        $encoded_link = base64_encode('ordercode=' . $ordercode);
        $paymentlink = URI::root() . 'index.php?option=com_ticketstation&controller=validate&task=pay&order=' . $encoded_link;

        return $paymentlink;
    }

    public function generateConfirmationLink($ordercode = null)
    {
        if (!$ordercode) {
            $ordercode = $this->ordercode;
        }

        $encoded_link = base64_encode('ordercode=' . $ordercode);
        $confirmation = URI::root() . 'index.php?option=com_ticketstation&controller=validate&task=waitinglist&order=' . $encoded_link;

        return $confirmation;
    }
}