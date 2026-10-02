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
use Joomla\CMS\Uri\Uri;
use stdClass;
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

    function send()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        require_once __DIR__ . '/PaymentAPI.php';

        $payment_helper = new PaymentAPI((int) $this->eid);

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
        $query->where(Refund::validSql('a'));
        Tickets::orderForPdf($query, 'a');

        $db->setQuery($query);
        $info = $db->loadObjectList();

        $attachment = count($info) > 1 ? Tickets::combinedPath($this->eid) : Tickets::singlePath($this->eid);

        $user = $payment_helper->getUserInformation();

        require_once __DIR__ . '/eTicketsMessage.php';

        $message = new eTicketsMessage;

        ## The same order placeholders as the mail after payment (see eTicketsMessage::TEMPLATE_FIELDS).
        $variables = eTicketsMessage::orderVariables((int) $this->eid);

        ## "Add to Apple/Google Wallet" (empty when no wallet is switched on)
        $variables['walletbuttons'] = Wallet::buttons((int) $this->eid, true);

        $message->id('2')
            ->user($user->clientid)
            ->variables($variables);

        if ($variables['walletbuttons'] !== '')
        {
            $message->appendPlaceholder('walletbuttons');
        }

        $message->attachment($attachment);

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