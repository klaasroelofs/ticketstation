<?php
/**
 * @package     ${NAMESPACE}
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */


namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;


/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


## no direct access
defined('_JEXEC') or die('Restricted access');

class Ticket
{
    /**
     * Getting ticket details by ticketid.
     *
     * @param $ticketid
     *
     * @return mixed
     *
     * @since 0.2.10
     */
    public function getTicketDetailsById($ticketid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . " = " . (int ) $ticketid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting tickets sold by ticketid.
     *
     * @param $ticketid
     *
     * @return mixed
     *
     * @since 3.5.4
     */
    public function getTicketsSoldById($ticketid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('COUNT(orderid) AS total_tickets_sold');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ticketid') . " = " . (int ) $ticketid);

        $db->setQuery($query);
        return $db->loadResult();

    }

    /**
     * Getting Pro Details from the Pro tables
     *
     * @param $ticketid
     *
     * @return mixed
     *
     * @since 3.5.4
     */
    public function getticketstationProTicketDetails($ticketid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_seatplansettings'))
            ->where($db->quoteName('ticketid') . " = " . (int) $ticketid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Removing a combined ticket.
     *
     * @param $ordercode
     *
     * @since 3.5.4
     */
    public function removeCombinedTicketFromServer($ordercode)
    {
        ## Path to a combined ticket is as below:
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTickets-' . $ordercode . '.pdf';

        if (file_exists($file))
        {
            $this->removeFileFromServer($file);
        }

        return;
    }

    /**
     * Removing a ticket from the server.
     *
     * @param $orderid
     *
     * @since 3.5.4
     */
    public function removeTicketFromServer($orderid)
    {
        ## Path to a combined ticket is as below:
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTicket-' . $orderid . '.pdf';

        if (file_exists($file))
        {
            $this->removeFileFromServer($file);
        }

        return;
    }

    /**
     * Removing a multi-ticket from the server.
     *
     * @param $ordercode
     *
     * @since 3.5.4
     */
    public function removeMultiTicketFromServer($ordercode)
    {
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/multi-' . $ordercode . '.pdf';

        if (file_exists($file))
        {
            $this->removeFileFromServer($file);
        }

        return;
    }

    /**
     * This fucntion removes the requested file from the server.
     *
     * @param null $path
     *
     * @return bool
     *
     * @since 3.5.4
     */
    private function removeFileFromServer($path = null)
    {
        if ( ! $path)
        {
            return false;
        }

        ClientHelper::setCredentialsFromRequest('ftp');

        File::delete($path);

        return true;
    }

    /**
     * Decreasing the total tickets in the database for the given ticket.
     *
     * @param null $ticketid
     *
     * @return bool
     *
     * @since 3.5.4
     */
    public function decreaseTicketTotals($ticketid = null)
    {
        if ( ! $ticketid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('totaltickets') . ' = totaltickets-1',
        ];

        $conditions = [
            $db->quoteName('ticketid') . ' = ' . $ticketid,
        ];

        $query->update($db->quoteName('#__ticketstation_tickets'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }

    /**
     * Increasing the total tickets in the database for the given ticket.
     *
     * @param null $ticketid
     *
     * @return bool
     *
     * @since 3.5.4
     */
    public function increaseTicketTotals($ticketid = null)
    {
        if ( ! $ticketid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('totaltickets') . ' = totaltickets+1',
        ];

        $conditions = [
            $db->quoteName('ticketid') . ' = ' . $ticketid,
        ];

        $query->update($db->quoteName('#__ticketstation_tickets'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }

    /**
     * Resetting the seatstate in the Pro version
     *
     * @param null $orderid
     *
     * @return bool
     *
     * @since 1.o.0
     */
    public function resetSeatSateForProVersion($orderid = null)
    {
        if ( ! $orderid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('booked') . ' = 0',
            $db->quoteName('orderid') . ' = 0',
        ];

        $conditions = [
            $db->quoteName('orderid') . ' = ' . $orderid,
        ];

        $query->update($db->quoteName('#__ticketstation_seatplancoords'))->set($fields)->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }

    /**
     * Laoding all tickets for a specific venue.
     *
     * @param $id
     *
     * @return mixed
     *
     * @since 3.5.4
     */
    public function getTicketsByVenue($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['v.*', 't.ticketname', 't.ticketdate', 't.ticketprice', 't.ticketid AS tid'])
            ->from($db->quoteName('#__ticketstation_venues', 'v'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('v.id') . ' = ' . $db->quoteName('t.venue'))
            ->where($db->quoteName('t.parent') . " = 0")
            ->where($db->quoteName('v.id') . " = " . (int) $id)
            ->where($db->quoteName('t.published') . " = 1")
            ->order('t.ticketdate ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}