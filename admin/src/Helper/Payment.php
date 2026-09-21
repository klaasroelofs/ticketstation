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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use stdClass;


defined('_JEXEC') or die('Restricted access');

class Payment
{
    private $ordercode;
    private $orderData;
    private $orderCount;

    function __construct($ordercode = null)
    {
        $this->ordercode = ($ordercode) ? $ordercode : 0;
    }

    /**
     * Setting a custom ordercode when it was not present already.
     *
     * @param $ordercode
     *
     * @since 1.0.0
     */
    public function setOrdercode($ordercode)
    {
        $this->ordercode = $ordercode;
    }

    /**
     * Inserting the temporary transaction to the temporary transaction tbale.
     *
     * @param $userid
     * @param $transid
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function insertTempTransaction($userid, $transid)
    {
        // Instantiate the amounts
        $amount = new Amount;

        $transaction                     = new \stdClass;
        $transaction->transaction_number = $transid;
        $transaction->userid             = (int) $userid;
        $transaction->ordercode          = (int) $this->ordercode;
        $transaction->amounts            = $amount->getAmountsAsJsonObject($this->ordercode);
        $transaction->processed          = 0;

        // Insert the object into the temporary transaction table.
        return Factory::getContainer()->get('DatabaseDriver')->insertObject('#__ticketstation_transactions_temp', $transaction);
    }

    /**
     * update the temporary transaction to any state
     * Needs to feeded with the unique transaction ID
     * This transaction ID has been entered by yourself with the saveTransaction() functionality
     *
     * @param     $transid
     * @param int $state
     * @param int $message
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function updateTempTransaction($transid, $state = 1, $message = 1)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('processed') . ' = ' . $db->quote((int) $state),
            $db->quoteName('message') . ' = ' . $db->quote($message),
        ];

        $conditions = [
            $db->quoteName('transaction_number') . ' = ' . $db->quote($transid),
        ];

        $query->update($db->quoteName('#__ticketstation_transactions_temp'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        return $db->execute();
    }

    /**
     * This option gives the abillity to obtain all orrder data at once for all templates in payment plugins.
     *
     * @param $ordercode
     * @param $userid
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function getDefaultVariablesForTemplates($ordercode, $userid)
    {
        $price         = (new Amount)->getAmountByOrdercode($ordercode);
        $transaction   = (new Transaction)->getTransactionDetails($ordercode);
        $invoice       = (new Invoice)->getInvoiceByOrderode($ordercode, $userid);
        $payment_state = (new Order)->isOrderPending($ordercode) ? Text::_('COM_TICKETSTATION_ORDERSTATUS_UNPAID') : Text::_('COM_TICKETSTATION_ORDERSTATUS_PAID');

        return [
            'client_id'                      => $userid,
            'order_date'                     => Date::_('NOW'),
            'order_list'                     => $this->getOrderList(),
            'order_number'                   => $ordercode,
            'ordered_items'                  => $price->items,
            'invoice_id'                     => !(empty($invoice->invoiceid)) ? $invoice->invoiceid : '',
            'invoice_bruto'                  => Price::_($price->price_ex),
            'invoice_discount'               => Price::_($price->discount),
            'invoice_vat'                    => Price::_($price->vat),
            'invoice_netto'                  => Price::_($price->total_discounted),
            'invoice_fees'                   => Price::_($price->fees),
            'invoice_discounted_ex_vat'      => Price::_($price->total_discounted_ex_vat),
            'invoice_price_without_discount' => Price::_($price->total_discounted),
            'payment_transaction'            => ! empty($transaction->transid) ? $transaction->transid : null,
            'payment_date'                   => ! empty($transaction->date) ? $transaction->date : null,
            'payment_provider'               => ! empty($transaction->type) ? $transaction->type : null,
            'payment_state'                  => $payment_state,
            'payment_link'                   => $this->generatePaymentLink($ordercode),
            'brutoprice'                     => Price::_($price->price_ex),
            'discount'                       => Price::_($price->discount),
            'totalvat'                       => Price::_($price->vat),
            'nettoprice'                     => Price::_($price->total),
        ];
    }

    /**
     * Check the count of the temporary transactions.
     * Every transaction maybe entered once. It will return false if there are more than 0 transactions.
     *
     * @param $transaction_number
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function checkTempTransactionAmount($transaction_number)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('transaction_number') . ' = ' . $db->quote($transaction_number));

        $db->setQuery($query);

        $temp_transaction = $db->loadObjectList();

        return count($temp_transaction);
    }

    /**
     * @param $transaction_number
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getTempTransactionResult($transaction_number)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('transaction_number') . ' = ' . $db->quote($transaction_number));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     *    $ave the transaction details to the transaction table
     *
     * @param $transid
     * @param $userid
     * @param $details
     * @param $amount
     * @param $type
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function saveTransaction($transid, $userid, $details, $amount, $type)
    {
        $transaction          = new stdClass;
        $transaction->transid = $transid;
        $transaction->userid  = (int) $userid;
        $transaction->details = $details;
        $transaction->amount  = $amount;
        $transaction->type    = $type;
        $transaction->orderid = (int) $this->ordercode;

        return Factory::getContainer()->get('DatabaseDriver')->insertObject('#__ticketstation_transactions', $transaction);
    }

    ## update the order details in the ticketbox, feeded by the ordercode.
    public function paymentPendingState()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('paid') . ' = ' . $db->quote('3'),
            $db->quoteName('published') . ' = 1',
        ];

        $conditions = [
            $db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->ordercode),
        ];

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ($db->execute())
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    ## update the order details in the ticketbox, feeded by the ordercode.
    public function updateOrder()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('paid') . ' = 1',
            $db->quoteName('published') . ' = 1',
        ];

        $conditions = [
            $db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->ordercode),
        ];

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ($db->execute())
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    ## Create the tickets for this client, feeded by the ordercode.
    public function createTickets()
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->ordercode));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        ## Loop through the items to create the tickets:
        for ($i = 0, $n = count($data); $i < $n; $i++)
        {
            $row = $data[$i];


            if (isset($row->orderid))
            {
                ## Create the tickets for this order:
                $creator = new ticketcreator((int) $row->orderid);
                $creator->doPDF();
            }
        }

        return true;
    }

    public function getPaymentState()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['COUNT(orderid) AS total'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . " = " . $db->quote($this->ordercode))
            ->where($db->quoteName('paid') . ' != 1');

        $db->setQuery($query);
        $status = $db->loadObject();

        if ($status->total > 0)
        {
            return false;
        }

        return true;
    }

    ## sending the tickets to the customer feeded by the ordercode
    public function sendTickets()
    {

        ## Sending the ticket immediatly to the client.
        $creator = new sendonpayment((int) $this->ordercode);
        $creator->send();

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
        $sendconfirmation = new confirmation((int) $this->ordercode);
        $sendconfirmation->doConfirm();
        $sendconfirmation->doSend();

        return true;
    }

    ## Creating an order list which can be used in emails.
    public function getWaitingList()
    {
        $config = $this->getConfig();

        $select = [
            'o.*', 't.*', 'e.eventname', 'c.*', 't.ticketdate', 't.starttime', 't.location',
            't.locationinfo', 'e.groupname', 't.eventcode', 't.ticketprice AS price',
        ];

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_waitinglist', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->ordercode));
        $query->group('o.id');

        $db->setQuery($query);
        $this->orderData  = $db->loadObjectList();
        $this->orderCount = count($data);

        $orders = '<ul>';

        for ($i = 0, $n = count($this->orderData); $i < $n; $i++)
        {

            $row = $this->orderData[$i];

            $price      = showprice($config->priceformat, $row->ticketprice, $config->valuta);
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


        $select = [
            'o.*', 't.*', 'e.eventname', 'c.*', 't.ticketdate', 't.starttime', 't.location',
            't.locationinfo', 'o.paid', 'e.groupname', 't.eventcode',
            't.ticketprice AS price', 'ext.seatid', 'ext.row_name',
        ];

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');

        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');

        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->ordercode));
        $query->group('o.orderid');

        $db->setQuery($query);

        $this->orderData  = $db->loadObjectList();
        $this->orderCount = count($this->orderData);

        $orders = '<ul>';

        for ($i = 0, $n = count($this->orderData); $i < $n; $i++)
        {
            $row = $this->orderData[$i];

            $price      = $row->ticketprice;
            $ticketdate = date($config->dateformat, strtotime($row->ticketdate));

            if ($row->seatid == '')
            {
                $orders .= '<li>[ ' . $row->orderid . ' ] - [ ' . $ticketdate . ' ] - <strong>' . $row->ticketname . '</strong> [ ' . $price . ' ]</li>';
            }
            else
            {
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

        $query->select(['COUNT(orderid) AS total']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->ordercode));
        $query->where($db->quoteName('paid') . ' = ' . $db->quote(0));

        $db->setQuery($query);

        return $db->loadObjectList();
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

        $query->select(['c.*']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->ordercode));
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
        if ( ! $ordercode)
        {
            $ordercode = $this->ordercode;
        }

        $encoded_link = base64_encode('ordercode=' . $ordercode);
        $paymentlink  = Uri::root() . 'index.php?option=com_ticketstation&controller=validate&task=pay&order=' . $encoded_link;

        return $paymentlink;
    }

    public function generateConfirmationLink($ordercode = null)
    {
        if ( ! $ordercode)
        {
            $ordercode = $this->ordercode;
        }

        $encoded_link = base64_encode('ordercode=' . $ordercode);
        $confirmation = Uri::root() . 'index.php?option=com_ticketstation&controller=validate&task=waitinglist&order=' . $encoded_link;

        return $confirmation;
    }
}