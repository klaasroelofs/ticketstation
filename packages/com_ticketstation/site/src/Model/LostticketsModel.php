<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SendTicketCopy;

/**
 * Ticketstation Losttickets Model: mails a customer the tickets they still need.
 */
class LostticketsModel extends BaseDatabaseModel
{
    /**
     * An order gets at most one copy per this many minutes, so the form can't be used to
     * flood a customer's inbox.
     */
    private const THROTTLE_MINUTES = 15;

    /**
     * Mails a copy of the tickets of every paid order placed with this email address that
     * still has a ticket for an event that hasn't ended yet, one mail per order, the same
     * mail the Box Office sends with "Resend tickets".
     *
     * @param   string  $email  The address the customer entered.
     *
     * @return  integer  The number of orders mailed.
     */
    public function resend(string $email): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('o.ordercode'))
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->where($db->quoteName('c.emailaddress') . ' = ' . $db->quote($email))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where($db->quoteName('o.pdfcreated') . ' = 1')
            ->where(Refund::validSql('o'))
            ->where($db->quoteName('t.enddate') . ' >= ' . $db->quote(Date::localNow()));

        $db->setQuery($query);
        $ordercodes = $db->loadColumn();

        $sent = 0;

        foreach ($ordercodes as $ordercode)
        {
            if ($this->recentlySent($ordercode) || !$this->hasTicketFile($ordercode))
            {
                continue;
            }

            try
            {
                $mailed = (new SendTicketCopy((int) $ordercode))->send();
            }
            catch (\Throwable $e)
            {
                // A missing ticket PDF or a mail error only skips this order.
                $mailed = false;
            }

            if ($mailed)
            {
                History::log($ordercode, 'ticket_copy_sent', 'Ticket copy sent on request via the lost tickets page');
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Whether the PDF that SendTicketCopy attaches exists (one ticket: eTicket-<orderid>.pdf,
     * more: eTickets-<ordercode>.pdf), so a customer never gets the mail without tickets.
     */
    private function hasTicketFile(string $ordercode): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('orderid'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $ordercode))
            ->where(Refund::validSql());

        $db->setQuery($query);
        $orderids = $db->loadColumn();

        $folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';
        $file   = count($orderids) > 1 ? 'eTickets-' . (int) $ordercode . '.pdf' : 'eTicket-' . (int) ($orderids[0] ?? 0) . '.pdf';

        return is_file($folder . $file);
    }

    /**
     * Whether this order already got a ticket copy within the throttle window.
     */
    private function recentlySent(string $ordercode): bool
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $since = Factory::getDate('-' . self::THROTTLE_MINUTES . ' minutes')->toSql();

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_history'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
            ->where($db->quoteName('event_type') . ' = ' . $db->quote('ticket_copy_sent'))
            ->where($db->quoteName('created') . ' >= ' . $db->quote($since));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }
}
