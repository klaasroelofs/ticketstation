<?php

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
    private $font = 'Raleway';

    /**
     * @param   int    $ticketid  The ticket being edited (used to find its uploaded background).
     * @param   array  $data      The posted jform values from the ticket edit form.
     *
     * @return  string  The generated PDF, as a binary string.
     */
    function generate($ticketid, array $data)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $config = $db->loadObject();

        $creator = new ticketcreator($ticketid);
        $dummy   = $this->getDummyContent();

        ## Ticket size, same rules as ticketcreator::doPDF()
        if (!empty($data['override_ticketsize'])) {
            $ticket_size = explode(",", $data['override_ticketsize']);
        } elseif (($data['ticket_size'] ?? '') == 'A4') {
            $ticket_size = explode(",", '210,297');
        } else {
            $ticket_size = explode(",", '148,210');
        }

        $orientation = ($data['ticket_orientation'] ?? '') ?: 'P';

        require_once __DIR__ . '/PDF/FPDI_EAN13.php';

        $pdf = new FPDI_EAN13();
        $pdf->AddPage($orientation, $ticket_size);

        ## Background image/PDF: same lookup as ticketcreator::doPDF()
        $background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.jpg';

        if (DefaultTicketLayout::applies($ticketid)) {
            DefaultTicketLayout::drawBackground($pdf);

            if (!DefaultTicketLayout::hasFields($data)) {
                $data = array_merge($data, DefaultTicketLayout::defaultFields($pdf));
            }
        } elseif (!file_exists($background)) {
            $sourcefile = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.pdf';

            $pdf->setSourceFile($sourcefile);
            $tplIdx = $pdf->importPage(1);
            $pdf->useTemplate($tplIdx, 0, 0, 210);
        } elseif (($data['ticket_size'] ?? '') == 'A4') {
            if ($orientation == 'P') {
                $pdf->Image($background, 0, 0, 210, 290);
            } else {
                $pdf->Image($background, 0, 0, 290, 210);
            }
        } else {
            if ($orientation == 'P') {
                $pdf->Image($background, 0, 0, 148.5, 210);
            } else {
                $pdf->Image($background, 0, 0, 210, 148.5);
            }
        }

        ## EVENTNAME / TICKETNAME / FREE TEXT 1 all share the same drawing rules.
        $this->writeField($pdf, $creator, $data, 'eventname', $dummy['eventname']);
        $this->writeField($pdf, $creator, $data, 'ticketname', $dummy['ticketname']);
        $this->writeField($pdf, $creator, $data, 'freetext_1', $dummy['freetext_1']);

        ## ORDERREFERENCE
        if ($this->hasPosition($data, 'orderreference_position')) {
            $position = explode("-", $data['orderreference_position']);
            $pdf->SetFont($this->font, 'B', ($data['orderreference_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['orderreference_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);

            if (($data['orderreference_centered'] ?? '0') == 1) {
                $mid_x = ($pdf->GetPageWidth() / 2) - ($pdf->GetPageWidth() * 0.04);
                $pdf->SetXY($mid_x - ($pdf->GetStringWidth($dummy['remarks']) / 2), $position[1]);
            } else {
                $pdf->SetXY($position[0], $position[1]);
            }

            $pdf->Write(0, PdfEncoding::toLatin1($dummy['remarks']));
        }

        ## STARTDATE
        if ($this->hasPosition($data, 'ticketdate_position')) {
            $position = explode("-", $data['ticketdate_position']);
            $pdf->SetFont($this->font, 'B', ($data['ticketdate_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ticketdate_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_DATE') . ' ' . date("d-m-Y", strtotime($dummy['startdate'])) . ' || ' . TicketLanguage::_('COM_TICKETSTATION_PDF_START') . ' ' . TicketLanguage::sprintf('COM_TICKETSTATION_PDF_TIME', date("H:i", strtotime($dummy['startdate']))));
        }

        ## PRICE
        if ($this->hasPosition($data, 'ticketprice_position')) {
            $position = explode("-", $data['ticketprice_position']);
            $pdf->SetFont($this->font, 'B', ($data['ticketprice_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ticketprice_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $price = TicketstationFunctions::showprice($config->priceformat, $dummy['ticketprice'], '');

            if ($config->use_euros_in_pdf == 2) {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . chr(128) . ' ' . $price);
            } elseif ($config->use_euros_in_pdf == 3) {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . chr(0x00A3) . ' ' . $price);
            } else {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . $price);
            }
        }

        ## ORDERDATE
        if ($this->hasPosition($data, 'orderdate_position')) {
            $position = explode("-", $data['orderdate_position']);
            $pdf->SetFont($this->font, 'B', ($data['orderdate_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['orderdate_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $orderdate = Date::_($dummy['orderdate'], $config->dateformat ?: 'd-m-Y');

            $pdf->Write(0, PdfEncoding::toLatin1($orderdate));
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
                $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_ORDERED_BY')));
                $pdf->SetFont($this->font, '', $fontsize);
                $pdf->SetXY($position[0], $position[1] + 5);
                $pdf->Write(0, substr(PdfEncoding::toLatin1($dummy['firstname'] . ' ' . $dummy['name']), 0, 35));
            } else {
                $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_ORDERED_BY') . ' ' . $dummy['firstname'] . ' ' . $dummy['name']));
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
                $pdf->Write(0, ($data['orderticketindex_prependtext'] ?? '') . ' ' . $dummy['ticket_volgnummer'] . '/' . $dummy['tickets_in_order']);
            } else {
                $pdf->SetFont($this->font, '', ($data['orderticketindex_fontsize'] ?? '') ?: 10);
                $pdf->Write(0, $dummy['ticket_volgnummer'] . '/' . $dummy['tickets_in_order']);
            }
        }

        ## ORDERNUMBER
        if ($this->hasPosition($data, 'ordernumber_position')) {
            $position = explode("-", $data['ordernumber_position']);
            $pdf->SetFont($this->font, '', ($data['ordernumber_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['ordernumber_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->TextWithDirection($position[0], $position[1], $dummy['eventcode'] . '-' . $dummy['ticketcode'] . '  |  ' . $dummy['ordercode'] . '-' . $dummy['orderid'], 'U');
        }

        ## SEATNUMBER
        if ($this->hasPosition($data, 'seatnumber_position')) {
            $position = explode("-", $data['seatnumber_position']);
            $pdf->SetFont($this->font, 'B', ($data['seatnumber_fontsize'] ?? '') ?: 10);
            $rgb = $creator->hexToRgb(($data['seatnumber_fontcolor'] ?? '') ?: '000000');
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);
            $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_SEAT_NUMBER') . ' ' . $dummy['seat_row'] . $dummy['seat_id']);
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

    private function getDummyContent()
    {
        return [
            'eventname'         => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_EVENT'),
            'ticketname'        => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_TICKET'),
            'freetext_1'        => Text::_('COM_TICKETSTATION_PREVIEW_SAMPLE_FREETEXT'),
            'startdate'         => date('Y-m-d H:i:s'),
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
