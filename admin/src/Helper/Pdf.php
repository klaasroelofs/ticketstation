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

## no direct access

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

class Pdf
{
    private $config;
    private $db;

    public function __construct()
    {
        $this->config = (new Config)->get();
        $this->db     = Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Replacing image tags with full url path.
     * This way mPDF can handle the image and parse it into the template.
     *
     * @param $html
     *
     * @return mixed
     *
     * @since 3.5.0
     */
    protected function replaceImageSources($html)
    {
        preg_match_all('@QrCode="([^"]+)"@', $html, $match);

        // Getting all images in an array
        $images = array_pop($match);

        foreach ($images as $image)
        {
            $path = Uri::root();
            $html = str_replace($image, $path . '/' . $image, $html);
        }

        return $html;
    }

    /** Preparing the orderlist for the confiration and the invoice.
     *
     * @param     $ordercode
     * @param     $userid
     * @param int $template
     *
     * @return string
     *
     * @since 3.5.0
     */
    protected function getOrderlist($ordercode, $userid, $template = 151)
    {
        // Getting the items for the PDF
        $items = $this->getItemsForPdf($ordercode);

        // returning the orders in html format
        return $this->getOrderlistAsHtml($items, $userid, $template);
    }

    private function getOrderlistAsHtml($items, $userid, $template)
    {
        // Empty list
        $html = '';

        // Loop through all items.
        foreach ($items as $item)
        {
            $variables = [
                'item_amount'   => $item->amount,
                'item_price'    => Price::_($item->total_price),
                'item_vat'      => Price::_($item->vat),
                'item_discount' => Price::_($item->discount),
                'item_vat_pct'  => $item->item_vat_pct,
                'price_ex_vat'  => Price::_($item->price_ex_vat),
                'ticket_price'  => Price::_($item->ticket_price),
                'ticket_name'   => $item->ticket_name,
                'ticket_date'   => Date::_($item->ticket_date),
                'ticket_group'  => $item->ticket_group,
                'event_name'    => $item->event_name,
                'venue_name'    => $item->venue_name,
                'venue_street'  => $item->venue_street,
                'venue_zipcode' => $item->venue_zipcode,
                'venue_city'    => $item->venue_city,
            ];

            // Instantiate a new meesage
            $message = new eTicketsMessage;

            // Create an orderlist for the confirmation
            $html .= $message->id($template)
                ->user($userid)
                ->variables($variables)
                ->getBody();
        }

        return $html;
    }

    /**
     * Getting the information for items on this invoice/confirmation.
     *
     * @param $ordercode
     *
     * @return mixed
     *
     * @since 3.5.0
     */
    private function getItemsForPdf($ordercode)
    {
        $select = [
            'COUNT(o.orderid) AS amount',
            'o.*',
            't.ticketname AS ticket_name',
            't.ticketprice AS ticket_price',
            'e.eventname AS event_name',
            't.ticketdate AS ticket_date',
            'e.groupname AS ticket_group',
            't.eventcode AS event_code',
            't.starttime as event_start',
            'o.vat_percentage as item_vat_pct',
            'SUM(o.price_excluding_vat) AS price_ex_vat',
            'SUM(o.price) AS total_price',
            'SUM(o.vat) AS vat',
            'SUM(o.discount) AS discount',
            'o.vat_percentage AS vat_pct',
            'v.venue AS venue_name',
            'v.street AS venue_street',
            'v.zipcode AS venue_zipcode',
            'v.city AS venue_city',
        ];

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($select)
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')')
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')')
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON (' . $db->quoteName('t.venue') . ' = ' . $db->quoteName('v.id') . ')')
            ->where($db->quoteName('o.ordercode') . ' = ' . $ordercode)
            ->group('o.ticketid');

        $db->setQuery($query);

        $items = $db->loadObjectList();

        foreach ($items as &$item)
        {
            if ($item->seat_sector != 0)
            {
                $ordered = $this->getSeatInformation($item->seat_sector);

                $item->seat_number = $ordered->seatid;
                $item->row_number  = $ordered->row_name;
            }
            else
            {
                $item->seat_number = '';
                $item->row_number  = '';
            }
        }

        return $items;
    }

    /**
     * Getting seat information for the invoice.
     *
     * @param $seat_sector
     *
     * @return mixed
     *
     * @since 3.5.0
     */
    private function getSeatInformation($seat_sector)
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ticketstation_seatplancoords'))
            ->where($this->db->quoteName('id') . ' = ' . $seat_sector);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    /**
     * Checks if the library is present in our Joomla environment.
     *
     * @return bool
     *
     * @since 3.5.0
     */
    protected function checkLibrary()
    {
        // Check if the library is installed.
        if ( ! file_exists(JPATH_ROOT . '/libraries/mpdf/vendor/autoload.php'))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PDF_LIBRARY_IS_NOT_INSTALLED'), 'error');

            return false;
        }

        // Loading mPDF library if installed.
        require_once JPATH_ROOT . '/libraries/mpdf/vendor/autoload.php';

        return true;
    }

    /**
     * Turning on BIG SELECTS for some hosting providers this is required in order to make this big queries to obtain everything.
     *
     * @return bool
     *
     * @since 3.5.0
     */
    protected function setBigSelects()
    {
        return QueryHelper::enableBigSelects($this->db);
    }
}