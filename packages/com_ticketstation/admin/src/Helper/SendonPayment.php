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
use setasign\Fpdi\FPDI_EAN13;
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

    function combinetickets($info)
    {

        $initrow = $info[0];

        require_once __DIR__ . '/PDF/FPDI_EAN13.php';

        $pdf = new FPDI_EAN13();

        $foutn = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTickets-'.$initrow->ordercode.'.pdf';

        for ($i = 0, $n = count($info); $i < $n; $i++ ){

            $row  = $info[$i];
            $fn = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-'.$row->orderid.'.pdf';
            $pdf->setSourceFile($fn);
            $tplIdx = $pdf->importPage(1);
            $size   = $pdf->getTemplateSize($tplIdx);
            $pdf->addPage();
            $pdf->useTemplate($tplIdx, 0, 0, $size['w'], null, true);

        }

        $file = basename(tempnam('.', 'tmp'));
        rename($file, JPATH_SITE . '/tmp/' . $file .'.pdf');
        $file .= '.pdf';

        //Save PDF to file now!!
        $pdf->Output(JPATH_SITE . '/tmp/'. $file, 'F');

        //Copy the file to a new directory.
        $src  = JPATH_SITE . '/tmp/' . $file;

        //The new name for the ticket
        $dest = $foutn;

        File::copy($src, $dest);
        File::delete($src);

        //Delete separate PDF's
        for ($i = 0, $n = count($info); $i < $n; $i++ ){

            $row  = $info[$i];
            $src = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-'.$row->orderid.'.pdf';
            File::delete($src);

        }

        return $foutn;
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
        $query->order($db->quoteName('ext.seatid') . ' ASC');

        $db->setQuery($query);
        $info = $db->loadObjectList();

        if(count($info)>1)
        {
            $attachment = $this->combinetickets($info);
        } else {
            $attachment = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-'.$info[0]->orderid.'.pdf';
        }

        //get the payment status for this order:
        $status = $payment_helper->getPaymentStateForEmails();
        $config = $payment_helper->getConfig();
        $user 	= $payment_helper->getUserInformation();

        if ($status->total > 0)
        {
            $paymentstatus = '<span style="font-color=#FF0000;">'.Text::_( 'COM_TICKETSTATION_ORDERSTATUS_UNPAID' ).'</span>';
        }
        else
        {
            $paymentstatus = '<<span style="font-color=#006600;">'.Text::_( 'COM_TICKETSTATION_ORDERSTATUS_PAID' ).'</span>';
        }

        $to_be_paid 	= (new getAmount())->_getAmount($this->eid);
        $price 			= (new TicketstationFunctions())->showprice($config->priceformat ,$to_be_paid , $config->valuta);

        require_once __DIR__ . '/eTicketsMessage.php';

        $message = new eTicketsMessage();

        $variables = array(
            'orderlist' 		=> $payment_helper->getOrderList(),
            'orderdate' 		=> $info[0]->orderdate,
            'ordercode' 		=> $this->eid,
            'price' 			=> $price,
            'paymentstatus' 	=> $paymentstatus,
            'ordercount'		=> $payment_helper->getOrderCount(),
        );

        $message->id('1')
            ->user($user->clientid)
            ->variables($variables);

        $message->attachment($attachment);

        $message->send();

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