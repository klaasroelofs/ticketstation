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

defined('_JEXEC') or die;

require_once (JPATH_COMPONENT . '/autoloader.php');

/**
 * Renders an in-memory preview PDF for the "Ticket Layout" tab.
 *
 * Mirrors the field-drawing logic of ticketcreator::doPDF(), but uses dummy
 * content for every printable field and the font colour/size/position/ticket
 * size values currently entered (but not necessarily saved yet) in the form.
 * Unlike doPDF(), this never touches the orders table and never writes the
 * resulting PDF to disk - it simply returns the PDF as a string.
 *
 * @since 1.8.0
 */
class TicketPreviewCreator
{
    private $font = 'raleway';

    /**
     * @param   int    $ticketid  The ticket being edited (used to find its uploaded background).
     * @param   array  $data      The posted jform values from the ticket edit form.
     *
     * @return  string  The generated PDF, as a binary string.
     */
    function generate($ticketid, array $data)
    {
        $creator = new ticketcreator($ticketid);
        $texts   = $this->sampleTexts($data);

        ## Ticket size, same rules as ticketcreator::doPDF()
        if (!empty($data['override_ticketsize'])) {
            $ticket_size = explode(",", $data['override_ticketsize']);
        } elseif (($data['ticket_size'] ?? '') == 'A4') {
            $ticket_size = explode(",", '210,297');
        } else {
            $ticket_size = explode(",", '148,210');
        }

        $orientation = ($data['ticket_orientation'] ?? '') ?: 'P';
        $this->font  = TicketFont::family($data['ticket_font'] ?? null);

        require_once __DIR__ . '/PDF/FPDI_EAN13.php';

        $pdf = new FPDI_EAN13();
        ## One page with fixed positions, same as ticketcreator::doPDF()
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage($orientation, $ticket_size);

        ## Background: the built-in layout or the uploaded design, same as ticketcreator::doPDF()
        if (DefaultTicketLayout::applies($ticketid)) {
            DefaultTicketLayout::drawBackground($pdf);

            if (!DefaultTicketLayout::hasFields($data)) {
                $data = array_merge($data, DefaultTicketLayout::defaultFields($pdf));
            }
        } else {
            TicketDesign::draw($pdf, $ticketid);
        }

        ## EVENTNAME / TICKETNAME / FREE TEXT 1 all share the same drawing rules.
        $this->writeField($pdf, $creator, $data, 'eventname', $texts['eventname']);
        $this->writeField($pdf, $creator, $data, 'ticketname', $texts['ticketname']);
        $this->writeField($pdf, $creator, $data, 'freetext_1', $texts['freetext_1']);

        ## ORDERREFERENCE
        if ($this->hasPosition($data, 'orderreference_position')) {
            $position = explode("-", $data['orderreference_position']);
            $pdf->SetFont($this->font, 'B', ($data['orderreference_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['orderreference_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);

            if (($data['orderreference_centered'] ?? '0') == 1) {
                $mid_x = ($pdf->GetPageWidth() / 2) - ($pdf->GetPageWidth() * 0.04);
                $pdf->SetXY($mid_x - ($pdf->GetStringWidth($texts['orderreference']) / 2), $position[1]);
            } else {
                $pdf->SetXY($position[0], $position[1]);
            }

            $pdf->Write(0, PdfEncoding::toLatin1($texts['orderreference']));
        }

        ## STARTDATE
        if ($this->hasPosition($data, 'ticketdate_position')) {
            $position = explode("-", $data['ticketdate_position']);
            $pdf->SetFont($this->font, 'B', ($data['ticketdate_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ticketdate_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, $texts['ticketdate']);
        }

        ## VENUE
        $this->writeField($pdf, $creator, $data, 'venue', $texts['venue']);

        ## PRICE
        if ($this->hasPosition($data, 'ticketprice_position')) {
            $position = explode("-", $data['ticketprice_position']);
            $pdf->SetFont($this->font, 'B', ($data['ticketprice_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ticketprice_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($texts['ticketprice']));
        }

        ## ORDERDATE
        if ($this->hasPosition($data, 'orderdate_position')) {
            $position = explode("-", $data['orderdate_position']);
            $pdf->SetFont($this->font, 'B', ($data['orderdate_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['orderdate_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($texts['orderdate']));
        }

        ## CLIENTNAME
        if ($this->hasPosition($data, 'client_position')) {
            $position = explode("-", $data['client_position']);
            $fontsize = ($data['client_fontsize'] ?? '') ?: 10;
            $pdf->SetFont($this->font, 'B', $fontsize);
            $rgb = $creator->hexToRgb(($data['client_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            if (($data['ticket_size'] ?? '') == 'A5') {
                $pdf->SetFont($this->font, 'B', $fontsize + 1);
                $pdf->Write(0, PdfEncoding::toLatin1($texts['client_orderedby']));
                $pdf->SetFont($this->font, '', $fontsize);
                $pdf->SetXY($position[0], $position[1] + 5);
                $pdf->Write(0, substr(PdfEncoding::toLatin1($texts['client_name']), 0, 35));
            } else {
                $pdf->Write(0, PdfEncoding::toLatin1($texts['client_orderedby'] . ' ' . $texts['client_name']));
            }
        }

        ## TICKETINDEX
        if ($this->hasPosition($data, 'orderticketindex_position')) {
            $position = explode("-", $data['orderticketindex_position']);
            $rgb = $creator->hexToRgb(($data['orderticketindex_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            if (($data['orderticketindex_prependtext_print'] ?? '0') == '1') {
                $pdf->SetFont($this->font, 'B', ($data['orderticketindex_fontsize'] ?? '') ?: 10);
                $pdf->Write(0, ($data['orderticketindex_prependtext'] ?? '') . ' ' . $texts['orderticketindex']);
            } else {
                $pdf->SetFont($this->font, '', ($data['orderticketindex_fontsize'] ?? '') ?: 10);
                $pdf->Write(0, $texts['orderticketindex']);
            }
        }

        ## ORDERNUMBER
        if ($this->hasPosition($data, 'ordernumber_position')) {
            $position = explode("-", $data['ordernumber_position']);
            $pdf->SetFont($this->font, '', ($data['ordernumber_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ordernumber_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->TextWithDirection($position[0], $position[1], $texts['ordernumber'], 'U');
        }

        ## SEATNUMBER
        if ($this->hasPosition($data, 'seatnumber_position')) {
            $position = explode("-", $data['seatnumber_position']);
            $pdf->SetFont($this->font, 'B', ($data['seatnumber_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['seatnumber_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);
            $pdf->Write(0, $texts['seatnumber']);
        }

        ## QR CODE - a fixed dummy payload, never a real barcode, and never stored on an order.
        if ($this->hasPosition($data, 'qrcode_position')) {
            $position = explode("-", $data['qrcode_position']);
            $qrwidth  = !empty($data['qrcode_width']) ? (int)$data['qrcode_width'] : 30;
            $barcode  = 'PREVIEW' . str_pad((string)$ticketid, 10, '0', STR_PAD_LEFT);

            $creator->get_qr_image_with_logo($barcode, $qrwidth);

            $cache_folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/';
            $pdf->Image($cache_folder . $barcode . '.png', $position[0], $position[1], 0, 0);

            File::delete($cache_folder . $barcode . '.png');
        }

        return $pdf->Output('S');
    }

    private function hasPosition(array $data, $key)
    {
        return !empty($data[$key]) && strpos($data[$key], '-') !== false;
    }

    private function writeField($pdf, $creator, array $data, $key, $value)
    {
        if (!$this->hasPosition($data, $key . '_position')) {
            return;
        }

        $position = explode("-", $data[$key . '_position']);
        $pdf->SetFont($this->font, 'B', ($data[$key . '_fontsize'] ?? '') ?: 10);
        $rgb = $creator->hexToRgb(($data[$key . '_fontcolor'] ?? '') ?: '000000');
        $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
        $pdf->SetXY($position[0], $position[1]);

        $pdf->Write(0, PdfEncoding::toLatin1($value));
    }

    /**
     * The sample text of every printable field, in UTF-8, exactly as the preview prints it. The
     * layout editor on the "Ticket Layout" tab shows the same texts (TicketController::
     * TicketLayoutEditor()), so the editor and the preview always agree.
     *
     * "client_orderedby" and "client_name" are printed on one line, or on two lines on A5
     * (see generate()); "orderticketindex" is printed after the optional prepend text.
     */
    public function sampleTexts(array $data): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $dummy = $this->getDummyContent();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . ' = 1');

        $config = $db->setQuery($query)->loadObject();

        ## The venue chosen on the form, so the preview shows its real length; sample text otherwise.
        $venueText = $dummy['venue'];

        if (!empty($data['venue'])) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['venue', 'city']))
                ->from($db->quoteName('#__ticketstation_venues'))
                ->where($db->quoteName('id') . ' = ' . (int) $data['venue']);

            $venue = $db->setQuery($query)->loadObject();

            if ($venue) {
                $venueText = (new ticketcreator(0))->venueText($venue->venue, $venue->city);
            }
        }

        // Currency and price format from the Configuration, as on the invoice.
        $price = trim(TicketstationFunctions::showprice($config->priceformat, $dummy['ticketprice'], $config->valuta));

        return [
            'eventname'        => $dummy['eventname'],
            'ticketname'       => $dummy['ticketname'],
            'freetext_1'       => $dummy['freetext_1'],
            'orderreference'   => $dummy['remarks'],
            'ticketdate'       => TicketLanguage::_('COM_TICKETSTATION_PDF_DATE') . ' ' . date("d-m-Y", strtotime($dummy['startdate'])) . ' || ' . TicketLanguage::_('COM_TICKETSTATION_PDF_START') . ' ' . TicketLanguage::sprintf('COM_TICKETSTATION_PDF_TIME', date("H:i", strtotime($dummy['startdate']))),
            'venue'            => $venueText,
            'ticketprice'      => TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . $price,
            'orderdate'        => Date::_($dummy['orderdate'], $config->dateformat ?: 'd-m-Y'),
            'client_orderedby' => TicketLanguage::_('COM_TICKETSTATION_PDF_ORDERED_BY'),
            'client_name'      => $dummy['firstname'] . ' ' . $dummy['name'],
            'orderticketindex' => $dummy['ticket_volgnummer'] . '/' . $dummy['tickets_in_order'],
            'ordernumber'      => $dummy['eventcode'] . '-' . $dummy['ticketcode'] . '  |  ' . $dummy['ordercode'] . '-' . $dummy['orderid'],
            'seatnumber'       => TicketLanguage::_('COM_TICKETSTATION_PDF_SEAT_NUMBER') . ' ' . $dummy['seat_row'] . $dummy['seat_id'],
        ];
    }

    private function getDummyContent()
    {
        return [
            'eventname'         => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_EVENT'),
            'ticketname'        => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_TICKET'),
            'freetext_1'        => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_FREETEXT'),
            'startdate'         => date('Y-m-d H:i:s'),
            'venue'             => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_VENUE'),
            'ticketprice'       => 25,
            'orderdate'         => date('Y-m-d H:i:s'),
            'firstname'         => 'Jan',
            'name'              => 'Janssen',
            'remarks'           => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_REFERENCE'),
            'ticket_volgnummer' => 2,
            'tickets_in_order'  => 4,
            'eventcode'         => 'EVT',
            'ticketcode'        => 'TIX',
            'ordercode'         => 100000,
            'orderid'           => 1,
            'seat_row'          => 'A',
            'seat_id'           => 12,
        ];
    }
}
