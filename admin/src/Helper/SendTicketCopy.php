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

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use stdClass;
use setasign\Fpdi\FPDI_EAN13;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;

defined('_JEXEC') or die;


## no direct access
defined('_JEXEC') or die('Restricted access');

class SendTicketCopy
{
    private $eid;
    private $info;
    private $error;

    function __construct($eid)
    {
        // Setting the $eid as var
        $this->eid = $eid;
    }

    function combinetickets($info)
    {

        $initrow = $info[0];

        if ($initrow->ticket_size == 'A4') {
            $width = 210;
        } else {
            $width = 148;
        }

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
        $db = Factory::getContainer()->get('DatabaseDriver');

        require_once __DIR__ . '/PaymentAPI.php';

        $payment_helper = new paymentAPI((int) $this->eid);

        // Clearing!
        $this->info  = '';
        $this->error = '';

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select(array('a.*', 'c.name', 'c.emailaddress', 'c.firstname', 'e.eventname', 't.ticket_size', 't.ticket_orientation'));
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('a.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid') . ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('a.orderid') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = ' . $db->quote((int) $this->eid));
        $query->order($db->quoteName('ext.seatid') . ' ASC');

        $db->setQuery($query);
        $info = $db->loadObjectList();

        if(count($info)>1)
        {
            $attachment = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTickets-'.$this->eid.'.pdf';
        } else {
            $attachment = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-'.$info[0]->orderid.'.pdf';
        }

        $user = $payment_helper->getUserInformation();

        require_once __DIR__ . '/eTicketsMessage.php';

        $message = new eTicketsMessage;

        $variables = array(
            'orderlist' => $payment_helper->getOrderList(),
        );

        $message->id('2')
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