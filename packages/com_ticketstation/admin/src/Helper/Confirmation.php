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

        ## Only rows that haven't had this mail yet. A promoted customer who follows the payment
        ## link goes through checkout again, which calls this method; their rows were mailed
        ## (and promoted) before, so they must not get a second confirmation link.
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $this->eid)
            ->where($db->quoteName('sent') . ' = 0')
            ->where($db->quoteName('processed') . ' = 0');
        $db->setQuery($query);
        $ids = array_map('intval', $db->loadColumn());

        if (!$ids)
        {
            return true;
        }

        $query = $db->getQuery(true);
        $query->select(['c.clientid AS userid', 'w.id AS waitinglist_id']);
        $query->from($db->quoteName('#__ticketstation_waitinglist', 'w'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('w.userid') . ')');
        $query->whereIn($db->quoteName('w.id'), $ids);
        $query->group('w.ordercode');
        $db->setQuery($query);
        $user = $db->loadObject();

        $config = $payment_helper->getConfig();
        ## Global things for this email:

        ## The mail goes out when the signup is completed at checkout, so today is its date.
        $date       = Date::_(Factory::getDate()->toSql(), $config->dateformat);

        ## getOrderCount() only reflects the previous getWaitingList()/getOrderList() call,
        ## so it must run after getWaitingList() below, not before it.
        $orderlist     = $payment_helper->getWaitingList($ids);
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

        ## See eTicketsMessage::TEMPLATE_FIELDS. There is no {price}: a waiting-list signup has
        ## no order rows yet. tickets isn't offered any more, but stays for older templates.
        $variables = [
            'orderlist'        => $orderlist,
            'orderdate'        => $date,
            'ordercode'        => $this->eid,
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
        $query->update($db->quoteName('#__ticketstation_waitinglist'))
            ->set($fields)
            ->whereIn($db->quoteName('id'), $ids);
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