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
use Joomla\Database\QueryInterface;

defined('_JEXEC') or die;


## no direct access
defined('_JEXEC') or die('Restricted access');

class Tickets
{
    /**
     * Sorts the tickets of one order: per event, per parent ticket (a child ticket goes with its
     * parent; the parent with the earliest ticket date first), and within that tickets without
     * a seat first, then the seats by row and seat number, and otherwise in the order they were
     * added. The number printed on a ticket ("3/33") and the page order of the combined PDF both
     * use it, so they always match. Without the order id at the end, tickets without a seat came
     * out in an arbitrary order.
     *
     * @param   QueryInterface  $query       query on #__ticketstation_orders; gets two joins
     *                                       (pdf_t, pdf_p) and its order
     * @param   string          $orderAlias  alias of #__ticketstation_orders in the query
     * @param   string          $seatAlias   alias of the joined #__ticketstation_seatplancoords
     */
    public static function orderForPdf(QueryInterface $query, string $orderAlias = 'a', string $seatAlias = 'ext'): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'pdf_t') . ' ON ' . $db->quoteName('pdf_t.ticketid') . ' = ' . $db->quoteName($orderAlias . '.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'pdf_p') . ' ON ' . $db->quoteName('pdf_p.ticketid') . ' = ' . $db->quoteName('pdf_t.parent') . ' AND ' . $db->quoteName('pdf_t.parent') . ' > 0')
            ->order([
                $db->quoteName($orderAlias . '.eventid') . ' ASC',
                'IFNULL(' . $db->quoteName('pdf_p.startdate') . ', ' . $db->quoteName('pdf_t.startdate') . ') ASC',
                'IFNULL(' . $db->quoteName('pdf_p.ticketid') . ', ' . $db->quoteName('pdf_t.ticketid') . ') ASC',
                $db->quoteName($seatAlias . '.row_name') . ' ASC',
                'CAST(' . $db->quoteName($seatAlias . '.seatid') . ' AS UNSIGNED) ASC',
                $db->quoteName($seatAlias . '.seatid') . ' ASC',
                $db->quoteName($orderAlias . '.orderid') . ' ASC',
            ]);
    }

    /**
     * The file name of the PDF of an order with a single (valid) ticket, and where it is kept.
     */
    public static function singleName($ordercode): string
    {
        return 'Ticket-' . (int) $ordercode . '.pdf';
    }

    public static function singlePath($ordercode): string
    {
        return JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/' . self::singleName($ordercode);
    }

    /**
     * The file name of the PDF with all tickets of an order. The customer sees it as the name of
     * the attachment and of the download.
     */
    public static function combinedName($ordercode): string
    {
        return 'Tickets-' . (int) $ordercode . '.pdf';
    }

    /**
     * Where the PDF with all tickets of an order is kept (not reachable from the web).
     */
    public static function combinedPath($ordercode): string
    {
        return JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/' . self::combinedName($ordercode);
    }

    /**
     * @param $ordercode
     *
     * @since 1.0.0
     */
    public function removeCombinedTicketFromServer($ordercode)
    {
        ## Path to a combined ticket is as below:
        $file = self::combinedPath($ordercode);

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
    public function removeTicketFromServer($ordercode, $barcode)
    {
        ## Path to a single ticket is as below:
        $file = self::singlePath($ordercode);

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