<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;

defined('_JEXEC') or die;


## no direct access
defined('_JEXEC') or die('Restricted access');

class SendonPayment
{
    private $eid;


    function __construct($eid)
    {
        $this->eid = $eid;
    }

    function send()
    {

        //Start helper:
        $payment_helper = new PaymentAPI( $this->eid );

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(
            array('a.*', 'c.name', 'c.emailaddress', 'c.firstname', 'e.eventname', 't.ticket_size', 't.ticket_orientation')
        );
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT',$db->quoteName('#__ticketstation_clients', 'c') . ' ON ('.$db->quoteName('a.userid').' = '.$db->quoteName('c.clientid') .')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' .$db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' .$db->quoteName('t.ticketid'). ' = ' .$db->quoteName('a.ticketid'). ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('a.orderid') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = '. $db->quote((int)$this->eid));
        $query->where(Refund::validSql('a'));
        Tickets::orderForPdf($query, 'a');

        $db->setQuery($query);
        $info = $db->loadObjectList();

        ## The file is made when the tickets are created; an order whose file is gone (or was never
        ## made under the current name) gets it made again here. Does nothing when it exists.
        Refund::refreshTicketFiles((int) $this->eid);

        $attachment = count($info) > 1 ? Tickets::combinedPath($this->eid) : Tickets::singlePath($this->eid);

        //get the payment status for this order:
        $status = $payment_helper->getPaymentStateForEmails();
        $config = $payment_helper->getConfig();
        $user 	= $payment_helper->getUserInformation();

        if ($status->total > 0)
        {
            $paymentstatus = '<span style="font-color=#FF0000;">'.TicketLanguage::_('COM_TICKETSTATION_ORDERSTATUS_UNPAID').'</span>';
        }
        else
        {
            $paymentstatus = '<span style="font-color=#006600;">'.TicketLanguage::_('COM_TICKETSTATION_ORDERSTATUS_PAID').'</span>';
        }

        require_once __DIR__ . '/eTicketsMessage.php';

        $message = new eTicketsMessage();

        ## The order placeholders every order mail gets (see eTicketsMessage::TEMPLATE_FIELDS);
        ## paymentstatus and ordercount aren't offered any more, but stay for older templates.
        $variables = eTicketsMessage::orderVariables((int) $this->eid);
        $variables['paymentstatus'] = $paymentstatus;
        $variables['ordercount']    = count($info);

        ## "Add to Apple/Google Wallet" (empty when no wallet is switched on)
        $variables['walletbuttons'] = Wallet::buttons((int) $this->eid, true);

        $message->id('1')
            ->user($user->clientid)
            ->variables($variables);

        if ($variables['walletbuttons'] !== '')
        {
            $message->appendPlaceholder('walletbuttons');
        }

        $message->attachment($attachment);

        ## "Add to calendar": the order's events as an .ics file next to the tickets
        $message->stringAttachment(Calendar::ics((int) $this->eid), 'event-' . (int) $this->eid . '.ics', 'text/calendar');

        ## A mail that didn't go out leaves the order as "tickets not sent" (Needs attention).
        if ( ! $message->send())
        {
            return false;
        }

        ## Mark as PDF Sent
        $query = $db->getQuery(true);
        $fields = array(
            $db->quoteName('pdfsent') . ' = 1'
        );
        $conditions = array(
            $db->quoteName('ordercode') . ' = ' . $db->quote((int)$this->eid)
        );
        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        if( !$db->execute() ){
            return false;
        }

        return true;
    }

}