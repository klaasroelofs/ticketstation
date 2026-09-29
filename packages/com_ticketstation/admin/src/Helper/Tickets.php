<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;


## no direct access
defined('_JEXEC') or die('Restricted access');

class Tickets
{
    /**
     * The order of the tickets of one order: per event, tickets without a seat first, then the
     * seats by row and seat number, and otherwise in the order they were added. The number
     * printed on a ticket ("3/33") and the page order of the combined PDF both use it, so they
     * always match. Without the order id at the end, tickets without a seat came out in an
     * arbitrary order.
     *
     * @param   string  $orderAlias  alias of #__ticketstation_orders in the query
     * @param   string  $seatAlias   alias of the joined #__ticketstation_seatplancoords
     *
     * @return  string[]  for $query->order()
     */
    public static function pdfOrder(string $orderAlias = 'a', string $seatAlias = 'ext'): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return [
            $db->quoteName($orderAlias . '.eventid') . ' ASC',
            $db->quoteName($seatAlias . '.row_name') . ' ASC',
            $db->quoteName($seatAlias . '.seatid') . ' ASC',
            $db->quoteName($orderAlias . '.orderid') . ' ASC',
        ];
    }

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
            // A blocked seat goes back to blocked, any other seat to free.
            $db->quoteName('booked') . ' = ' . $db->quoteName('blocked'),
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