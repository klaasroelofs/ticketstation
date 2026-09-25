<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;


defined('_JEXEC') or die('Restricted access');

/**
 * Sends the waiting-list confirmation email (template 4). This class used to also build and
 * mail a PDF order confirmation (doConfirm()/doSend()); that was removed because nothing in
 * the UI could trigger it any more.
 */
class Confirmation
{
    private $eid;

    function __construct($eid)
    {
        ## Setting the $eid as var
        $this->eid = $eid;
    }

    public function SendWaitingList()
    {
        ## Check if the class exsists:
        if ( ! class_exists(PaymentAPI::class))
        {
            exit("Class is not available to process transactions.");
        }

        $payment_helper = new PaymentAPI((int) $this->eid);
        $db             = Factory::getContainer()->get('DatabaseDriver');
        $query          = $db->getQuery(true);
        $query->select(['COUNT(id) AS total']);
        $query->from($db->quoteName('#__ticketstation_waitinglist'));
        $query->where($db->quoteName('ordercode') . ' = ' . (int) $this->eid);
        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result->total == 0)
        {
            return true;
        }

        $query = $db->getQuery(true);
        $query->select(['c.clientid AS userid', 'w.id AS waitinglist_id']);
        $query->from($db->quoteName('#__ticketstation_waitinglist', 'w'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('w.userid') . ')');
        $query->where($db->quoteName('w.ordercode') . ' = ' . (int) $this->eid);
        $query->group('w.ordercode');
        $db->setQuery($query);
        $user = $db->loadObject();

        $config = $payment_helper->getConfig();
        ## Global things for this email:

        $date       = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        $date       = date($config->dateformat, strtotime($date));
        $to_be_paid = (new getAmount())->_getAmount($this->eid);
        $price      = TicketstationFunctions::showprice($config->priceformat, $to_be_paid, $config->valuta);

        ## getOrderCount() only reflects the previous getWaitingList()/getOrderList() call,
        ## so it must run after getWaitingList() below, not before it.
        $orderlist     = $payment_helper->getWaitingList();
        $total_tickets = $payment_helper->getOrderCount();
        $message       = new eTicketsMessage;

        ## Generate confirmation link using waitinglist validation token (if available)
        $confirmationlink = '';
        if ($user && isset($user->waitinglist_id))
        {
            $confirmationlink = $payment_helper->generateWaitingListConfirmationLink($user->waitinglist_id);
        }
        if (empty($confirmationlink))
        {
            ## Fallback to ordercode-based link for backward compatibility
            $confirmationlink = $payment_helper->generateConfirmationLink($this->eid);
        }

        $variables = [
            'orderlist'        => $orderlist,
            'orderdate'        => $date,
            'ordercode'        => $this->eid,
            'price'            => $price,
            'confirmationlink' => $confirmationlink,
            'tickets'          => $total_tickets,
        ];

        $message->id(4)
            ->user($user->userid)
            ->variables($variables)
            ->send();

        $query      = $db->getQuery(true);
        $fields     = [
            $db->quoteName('sent') . ' = ' . $db->quote('1'),
            $db->quoteName('date_sent') . ' = ' . $db->quote(date('Y-m-d H:i:s')),
        ];
        $conditions = [$db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->eid)];

        $query->update($db->quoteName('#__ticketstation_waitinglist'))
            ->set($fields)
            ->where($conditions);
        $db->setQuery($query);

        if ($db->execute() == true)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

}