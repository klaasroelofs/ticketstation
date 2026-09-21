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

class Tickets
{
    /**
     * @param $ordercode
     *
     * @since 1.0.0
     */
    public function removeCombinedTicketFromServer($ordercode)
    {
        ## Path to a combined ticket is as below:
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTickets-' . $ordercode . '.pdf';

        if (file_exists( $file ))
        {
            $this->removeFileFromServer($file);
        }

        return;
    }

    /**
     * @param $ordercode
     *
     * @since 1.0.0
     */
    public function removeTicketFromServer($orderid, $barcode)
    {
        ## Path to a single ticket is as below:
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTicket-' . $orderid . '.pdf';

        if (file_exists( $file ))
        {
            $this->removeFileFromServer($file);
        }

        ## Bijbehorende QR-code uit de folder qrcodes verwijderen:

        $file_qr = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/qrcodes/' . $barcode . '.png';

        if (file_exists( $file_qr ))
        {
            $this->removeFileFromServer($file_qr);
        }

        return;
    }

    /**
     * @param $ordercode
     *
     * @since 1.0.0
     */
    public function removeMultiTicketFromServer($ordercode)
    {
        $file = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/multi-' . $ordercode.'.pdf';

        if (file_exists( $file ))
        {
            $this->removeFileFromServer($file);
        }

        return;
    }

    /**
     * @param $path
     * @since 1.0.0
     */
    private function removeFileFromServer($path=null)
    {

        if (file_exists($path))
        {
            File::delete($path);
            return true;
        }

        return false;

    }

    /**
     * @param $ticketid
     *
     * @since 1.0.0
     */
    public function decreaseTicketTotals($ticketid=null)
    {
        if(!$ticketid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('totaltickets') . ' = totaltickets-1'
        );

        $conditions = array(
            $db->quoteName('ticketid') . ' = '.$ticketid
        );

        $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result)
        {
            return false;
        }

        return true;
    }

    /**
     * @param $ticketid
     *
     * @since 1.0.0
     */
    public function increaseTicketTotals($ticketid=null)
    {
        if(!$ticketid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('totaltickets') . ' = totaltickets+1'
        );

        $conditions = array(
            $db->quoteName('ticketid') . ' = '.$ticketid
        );

        $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result)
        {
            return false;
        }

        return true;
    }

    /**
     * @param $orderid
     *
     * @since 1.0.0
     */
    public function resetSeatSate($orderid=null)
    {
        if(!$orderid)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('booked') . ' = 0',
            $db->quoteName('orderid') . ' = 0'
        );

        $conditions = array(
            $db->quoteName('orderid') . ' = '.$orderid
        );

        $query->update($db->quoteName('#__ticketstation_seatplancoords'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if (!$result) {
            return false;
        }
    }
}