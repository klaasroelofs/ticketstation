<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Joomla\CMS\Factory;

use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

defined('_JEXEC') or die;


class ticketcreator
{

    private $eid;
    private $font;
    private $orderid;

    function __construct($eid)
    {
        $this->eid = $eid;
    }

    /**
     * Makes the ticket PDF of one order row.
     *
     * @param   bool  $newCode  Give the ticket a new QR code, so a copy sent earlier no longer
     *                          scans (for a lost or stolen ticket). Otherwise a ticket keeps the
     *                          code it got the first time, so making the file again (after
     *                          changing the Order Reference, removing another ticket, ...)
     *                          leaves the tickets the customer already has valid.
     * @param   Pdf|null  $into  Draw the ticket as a new page of this document instead of making
     *                          a file of its own; the caller saves the document. That is how the
     *                          tickets of one order become a single PDF (see createOrderFile()).
     */
    function doPDF($newCode = false, $into = null)
    {
        ## Is the payment provider in test mode? Then the tickets are recognisable test tickets.
        $db = Factory::getContainer()->get('DatabaseDriver');

        $provider    = ProviderRegistry::active();
        $mollie_test = $provider !== null && $provider->isTestMode() ? 1 : 0;

        ## Load Ticketstation config
        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $config = $db->loadObject();

        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $select = array(
            'o.*',
            't.*',
            'c.*',
            'e.eventname',
            'e.eventcode',
            'e.eventid',
            't.ticketprice AS price',
            'r.remarks',
            'ext.seatid'
        );

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));

        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_remarks', 'r') . ' ON (' . $db->quoteName('r.ordercode') . ' = ' . $db->quoteName('o.ordercode') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');

        $query->where($db->quoteName('o.orderid') . ' = ' . $db->quote((int)$this->eid));
        $query->group('o.orderid');

        $db->setQuery($query);
        $order = $db->loadObject();

        ## The font chosen on the ticket's Ticket Layout tab
        $this->font = TicketFont::family($order->ticket_font ?? null);

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_venues'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote((int)$order->venue));

        $db->setQuery($query);
        $venue = $db->loadObject();

        ##Ticketnummering
        ## Let op: deze volgorde moet gelijk zijn aan de sortering die wordt gebruikt bij het samenvoegen
        ## van de losse ticket-PDF's (Tickets::orderForPdf()), anders klopt het opgedrukte paginanummer niet
        ## meer met de positie van het ticket in het gecombineerde PDF-bestand.
        $query = $db->getQuery(true);

        $query->select('o.orderid');
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int)$order->ordercode));
        $query->where(Refund::validSql('o'));
        Tickets::orderForPdf($query, 'o');

        $db->setQuery($query);
        $ticket_ids_in_order = $db->loadObjectList();

        $tickets_in_order = count($ticket_ids_in_order);

        while ($volgnummer = current($ticket_ids_in_order)) {
            if ($volgnummer->orderid == $this->eid) {
                $ticket_volgnummer = key($ticket_ids_in_order) + 1;
            }
            next($ticket_ids_in_order);
        }


        ## Define the ticket size from the ticket tables:
        if(!empty($order->override_ticketsize)) {
            $ticket_size = explode(",", $order->override_ticketsize);
        } elseif ($order->ticket_size == 'A4') {
            $ticket_size = explode(",", '210,297');
        } else {
            $ticket_size = explode(",", '148,210');
        }

        $pdf = $into ?? new Pdf();

        ## A ticket is exactly one page with fixed positions. Without this, FPDF starts a new page
        ## as soon as a field is written in the bottom 2 cm (or below the ticket), and every field
        ## after it ends up on that extra page.
        $pdf->SetAutoPageBreak(false);

        ## add a page
        $pdf->AddPage($order->ticket_orientation, $ticket_size);

        ## Background: the built-in layout, or the uploaded design (see TicketDesign)
        if(DefaultTicketLayout::applies($order->ticketid))
        {
            ## No design uploaded for this ticket: use the built-in layout, and its default
            ## fields as well when no field positions have been filled in for this ticket.
            DefaultTicketLayout::drawBackground($pdf);

            if(!DefaultTicketLayout::hasFields($order))
            {
                foreach(DefaultTicketLayout::defaultFields($pdf) as $key => $value)
                {
                    $order->$key = $value;
                }
            }
        }
        else
        {
            ## The uploaded design (JPG or PDF), stretched over the whole page.
            TicketDesign::draw($pdf, $order->ticketid);
        }

        /*######## THIS IS THE PART THAT WILL COME FROM THE XML FILE #########

        $form_data = json_decode($order->required_information);

        ## For now it is not yet possible to have more then one xml file.
        $xml_form = Uri::root() . '/components/com_ticketstation/models/forms/information.xml';

        ## Check if it realy there:
        if(file_exists($xml_form))
        {
            $xml_data = simplexml_load_file($xml_form);
        }

        ## Getting the JForm part:
        $form = Form::getInstance('adminForm', $xml_form);

        foreach ($form->getFieldset($fieldset->name) as $field):

            ## Getting the position for the specific field:
            foreach ($xml_data->fieldset->field as $xml)
            {
                if($xml['name'] == $field->name)
                {
                    $pdf_position = $xml['pdf_position'];
                    $pdf_fontsize = $xml['pdf_fontsize'];
                    $pdf_fontcolor = $xml['pdf_fontcolor'];
                }
            }

            ## Get the field name:
            $item = $field->name;
            $value_to_show = $form_data->$item->$item;

            $position = explode("-", $pdf_position);
            $font_color = explode("-", $pdf_fontcolor);

            $pdf->SetFont($this->font, '', $fontsize);
            $pdf->SetTextColor($font_color[0], $font_color[1], $font_color[2]);
            $pdf->SetXY($position[0], $position[1]);

            ## Writing the eventname on the ticket now.
            $pdf->Write(0, PdfEncoding::toLatin1($value_to_show));

        endforeach;

        ######## END OF PRINTING REQUIRED DATA ON THE TICKETS ##########*/

        ## EVENTNAME
        if(strpos($order->eventname_position, '-') !== false)
        {
            $position = explode("-", $order->eventname_position);
            $pdf->SetFont($this->font, 'B', $order->eventname_fontsize);
            $rgb = $this->hexToRgb($order->eventname_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($order->eventname));
        }

        ## TICKETNAME
        if(strpos($order->ticketname_position, '-') !== false)
        {
            $position = explode("-", $order->ticketname_position);
            $pdf->SetFont($this->font, 'B', $order->ticketname_fontsize);
            $rgb = $this->hexToRgb($order->ticketname_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($order->ticketname));
        }

        ## ORDERREFERENCE
        if($order->remarks != '')
        {
            if (strpos($order->orderreference_position, '-') !== false)
            {
                $position = explode("-", $order->orderreference_position);
                $pdf->SetFont($this->font, 'B', $order->orderreference_fontsize);
                $rgb = $this->hexToRgb($order->orderreference_fontcolor);
                $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);

                if($order->orderreference_centered == 1)
                {
                    $mid_x = ($pdf->GetPageWidth() / 2) - ($pdf->GetPageWidth() * 0.04); // Midden van de pagina bepalen en corrigeren met 4%
                    $pdf->SetXY($mid_x - ($pdf->GetStringWidth($order->remarks) / 2), $position[1]);
                } else {
                    $pdf->SetXY($position[0], $position[1]);
                }

                $pdf->Write(0, PdfEncoding::toLatin1($order->remarks));
            }
        }

        ## FREE TEXT 1
        if(strpos($order->freetext_1_position, '-') !== false)
        {
            $position = explode("-", $order->freetext_1_position);
            $pdf->SetFont($this->font, 'B', $order->freetext_1_fontsize);
            $rgb = $this->hexToRgb($order->freetext_1_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($order->freetext_1));
        }

        ## STARTDATE
        if(strpos($order->ticketdate_position, '-') !== false)
        {
            $position = explode("-", $order->ticketdate_position);
            $pdf->SetFont($this->font, 'B', $order->ticketdate_fontsize);
            $rgb = $this->hexToRgb($order->ticketdate_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_DATE') . ' ' . date("d-m-Y", strtotime($order->startdate)) . ' || ' . TicketLanguage::_('COM_TICKETSTATION_PDF_START') . ' ' . TicketLanguage::sprintf('COM_TICKETSTATION_PDF_TIME', date("H:i", strtotime($order->startdate))));
        }

        ## DOORS OPEN
        if(strpos((string) $order->doors_open_position, '-') !== false && trim((string) $order->doors_open) !== '')
        {
            $position = explode("-", $order->doors_open_position);
            $pdf->SetFont($this->font, 'B', $order->doors_open_fontsize);
            $rgb = $this->hexToRgb($order->doors_open_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_DOORS_OPEN') . ' ' . TicketLanguage::sprintf('COM_TICKETSTATION_PDF_TIME', $order->doors_open)));
        }

        ## VENUE
        if(strpos((string) $order->venue_position, '-') !== false && $venue)
        {
            $position = explode("-", $order->venue_position);
            $pdf->SetFont($this->font, 'B', $order->venue_fontsize);
            $rgb = $this->hexToRgb($order->venue_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $pdf->Write(0, PdfEncoding::toLatin1($this->venueText($venue->venue, $venue->city)));
        }

        ## PRICE
        if(strpos($order->ticketprice_position, '-') !== false)
        {
            $position = explode("-", $order->ticketprice_position);
            $pdf->SetFont($this->font, 'B', $order->ticketprice_fontsize);
            $rgb = $this->hexToRgb($order->ticketprice_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            // Currency and price format from the Configuration, as on the invoice.
            $price = trim((new TicketstationFunctions)->showprice($config->priceformat, $order->ticketprice, $config->valuta));

            $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . $price));
        }

        ## ORDERDATE
        if(strpos($order->orderdate_position, '-') !== false)
        {
            $position = explode("-", $order->orderdate_position);
            $pdf->SetFont($this->font, 'B', $order->orderdate_fontsize);
            $rgb = $this->hexToRgb($order->orderdate_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $orderdate = Date::_($order->orderdate, $config->dateformat);

            $pdf->Write(0, PdfEncoding::toLatin1($orderdate));
        }

        ## CLIENTNAME
        if(strpos($order->client_position, '-') !== false)
        {
            $position = explode("-", $order->client_position);
            $pdf->SetFont($this->font, 'B', $order->client_fontsize);
            $rgb = $this->hexToRgb($order->client_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            // Bij formaat A5 wordt "Besteld door" en Clientname onder elkaar gezet, anders past het niet
            if ($order->ticket_size == 'A5') {
                $pdf->SetFont($this->font, 'B', $order->client_fontsize + 1);
                $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_ORDERED_BY')));
                $pdf->SetFont($this->font, '', $order->client_fontsize);
                $pdf->SetXY($position[0], $position[1] + 5);
                $pdf->Write(0, substr((PdfEncoding::toLatin1($order->firstname) . ' ' . PdfEncoding::toLatin1($order->name)), 0, 35)); // naam afkorten op 35 tekens, anders past het niet
            } else {
                $pdf->Write(0, PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_ORDERED_BY')) . ' ' . PdfEncoding::toLatin1($order->firstname) . ' ' . PdfEncoding::toLatin1($order->name));
            }
        }

        ## TICKETINDEX
        if((strpos($order->orderticketindex_position, '-') !== false) && ($tickets_in_order > 1))
        {
            $position = explode("-", $order->orderticketindex_position);
            $rgb = $this->hexToRgb($order->orderticketindex_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            if ($order->orderticketindex_prependtext_print == '1') {
                $pdf->SetFont($this->font, 'B', $order->orderticketindex_fontsize);
                $pdf->Write(0, $order->orderticketindex_prependtext . ' ' . $ticket_volgnummer . '/' . $tickets_in_order);
            } else {
                $pdf->SetFont($this->font, '', $order->orderticketindex_fontsize);
                $pdf->Write(0, $ticket_volgnummer . '/' . $tickets_in_order);
            }
        }

        ## ORDERNUMBER
        if(strpos($order->ordernumber_position	, '-') !== false)
        {
            $position = explode("-", $order->ordernumber_position);
            $pdf->SetFont($this->font, '', $order->ordernumber_fontsize);
            $rgb = $this->hexToRgb($order->ordernumber_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            if ($order->ticketcode != $order->eventcode) {
                $pdf->TextWithDirection($position[0], $position[1], $order->eventcode . '-' . $order->ticketcode . '  |  ' . $order->ordercode . '-' . $order->orderid, 'U');
            } else {
                $pdf->TextWithDirection($position[0], $position[1], $order->ticketcode . '  |  ' . $order->ordercode . '-' . $order->orderid, 'U');
            }
        }

        ## SEATNUMBER
        if($order->seat_sector != 0)
        {
            $query = $db->getQuery(true);

            $query->select('*');
            $query->from($db->quoteName('#__ticketstation_seatplancoords'));
            $query->where($db->quoteName('id') . ' = ' . $db->quote($order->seat_sector));

            $db->setQuery($query);
            $seat = $db->loadObject();

            if(strpos($order->seatnumber_position, '-') !== false)
            {
                $position = explode("-", $order->seatnumber_position);
                $pdf->SetFont($this->font, 'B', $order->seatnumber_fontsize);
                $rgb = $this->hexToRgb($order->seatnumber_fontcolor);
                $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
                $pdf->SetXY($position[0], $position[1]);
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_SEAT_NUMBER') . ' ' . $seat->row_name . $seat->seatid);
            }
        }

        ## CREATING A BARCODE
        ## The barcode/QR content must not be derivable from the (sequential, predictable)
        ## ordercode/orderid, otherwise a valid code for one ticket lets you guess a valid
        ## code for another. So it's a random per-ticket token, unrelated to those numbers.
        ## If Mollie Test Mode is on, use a fixed value so test tickets stay recognisable.
        ## A ticket keeps the code it already has (see $newCode), except the fixed test code once
        ## Test Mode is off.
        $query = $db->getQuery(true)
            ->select($db->quoteName('barcode'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' = ' . (int) $this->eid);

        $db->setQuery($query);
        $current = (string) $db->loadResult();

        $test_code = '123456789012';
        $keep      = !$newCode && $current !== '' && $current !== '0' && ($current !== $test_code || $mollie_test == 1);

        if ($keep) {
            $barcode = $current;
        } elseif ($mollie_test == 1) {
            $barcode = $test_code;
        } else {
            $barcode = bin2hex(random_bytes(16));
        }

        ## The QR image the Box Office shows for a code that was replaced is of no use any more.
        if ($barcode !== $current && $current !== '' && $current !== '0') {
            $old_qr = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/qrcodes/' . basename($current) . '.png';

            if (file_exists($old_qr)) {
                File::delete($old_qr);
            }
        }

        $this->orderid = $order->orderid;

        ## Store the barcode with this ticket in the database
        $query = $db->getQuery(true);

        $fields = array($db->quoteName('barcode') . ' = ' . $db->quote($barcode));
        $conditions = array($db->quoteName('orderid') . ' = ' . $db->quote((int)$this->eid));

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ($result == false) {
            return false;
        }

        ## PRINT QR CODE ON TICKET IF SET
        if(strpos($order->qrcode_position	, '-') !== false) {
            $position = explode("-", $order->qrcode_position);
            // creating the QR Code image. Will be stored in cache folder
            $this->get_qr_image_with_logo($barcode, $order->qrcode_width);


            ## Getting the generated code.
            $cache_folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/';
            $pdf->Image($cache_folder . $barcode . '.png', $position[0], $position[1], 0, 0);

            //$pdf->SetFont($this->font, 'B', 8);
            //$pdf->SetXY($position[0], $position[1] - 2);
            //$pdf->Write(0, $barcode);

            ## We do want to remove the QR code again.
            File::delete($cache_folder . $barcode . '.png');


            ## Als de Mollie-plugin in test-modus staat, QR-code onleesbaar maken
            if ($mollie_test == '1') {

                ## te printen tekst definiëren
                $mark_unreadable = PdfEncoding::toLatin1(TicketLanguage::_('COM_TICKETSTATION_PDF_INVALID')); // LET OP: een langere tekst heeft tot gevolg dat ook het bijstellen van de posities enigszins aangepast moeten worden!

                ## startpositie bepalen, hoek linksonder in QR-code
                ## (1 pixel ≈ 0,2646 mm / image is 96 dpi)
                $qrcode_position = explode("-", $order->qrcode_position);
                $bar_x = (int)$qrcode_position[0] + ($order->qrcode_width * 0.02);                                // positie x bijstellen naar rechts met 2% van de breedte van de QR-code
                $bar_y = (int)$qrcode_position[1] + ($order->qrcode_width * 0.2646) + ($order->qrcode_width * 0.02);    // positie y laten zakken met de breedte van de QR-code + 2% van de breedte van de QR-code

                ## bepalen hoeveel ruimte er is in de diagonaal van de QR-code (stelling van pythagoras)
                $space_available = sqrt((($order->qrcode_width * 0.2646) * ($order->qrcode_width * 0.2646)) + (($order->qrcode_width * 0.2646) * ($order->qrcode_width * 0.2646)));

                ## fontsize bepalen op basis van ruimte beschikbaar
                ## fontsize bijstellen, net zolang totdat deze in de beschikbare ruimte past
                $x = 80;    // start met font-size 80 (als tekst korter wordt, dit mogelijk iets verhogen)
                $pdf->SetFont($this->font, 'B', $x);

                while ($pdf->GetStringWidth($mark_unreadable) > $space_available) {
                    $x -= 0.5;                                // fontsize steeds bijstellen met 0.5
                    $pdf->SetFont($this->font, 'B', $x);    // nieuwe fontsize definiëren
                }

                //$pdf->SetTextColor(187,39,33); 	// Huibuuke-rood
                $pdf->SetTextColor(255, 0, 0);    // Rood
                $pdf->TextWithRotation($bar_x, $bar_y, $mark_unreadable, 45, 0);

            }
        }

        if ($into === null)
        {
            self::savePdf($pdf, Tickets::singlePath($order->ordercode));
        }

        $query = $db->getQuery(true);

        $fields = array($db->quoteName('pdfcreated') . ' = ' . $db->quote('1'));
        $conditions = array($db->quoteName('orderid') . ' = ' . $db->quote($this->eid));

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if($result == false)
        {
            return false;
        }

        return true;

    }

    /**
     * The ids of an order's valid tickets, in the order of the pages of its combined PDF.
     *
     * @return  int[]
     */
    public static function validOrderIds(int $ordercode): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('a.orderid'))
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON ' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('a.orderid'))
            ->where($db->quoteName('a.ordercode') . ' = ' . $db->quote($ordercode))
            ->where(Refund::validSql('a'));
        Tickets::orderForPdf($query, 'a');

        $db->setQuery($query);

        return array_map('intval', $db->loadColumn());
    }

    /**
     * Makes the ticket file of an order: Ticket-<ordercode>.pdf for a single ticket, or one
     * Tickets-<ordercode>.pdf with all tickets for more.
     *
     * The tickets of an order are drawn into one document, not made one by one and glued together
     * afterwards: a design that is used more than once in one PDF is stored once, while glued
     * together every ticket would carry a full copy of its design (30 tickets with a 1 MB design
     * would weigh over 30 MB).
     *
     * @param   int[]  $orderids    the valid tickets of the order, in page order (validOrderIds())
     * @param   int[]  $newCodeIds  tickets that get a new QR code; the others keep theirs
     *
     * @return  bool
     */
    public static function createOrderFile(int $ordercode, array $orderids, array $newCodeIds = []): bool
    {
        if (!$orderids)
        {
            return true;
        }

        if (count($orderids) === 1)
        {
            if ((new self($orderids[0]))->doPDF(in_array($orderids[0], $newCodeIds, true)) === false)
            {
                return false;
            }

            ## Downloads prefer the file with all tickets, so one left over from before would win
            self::deleteFile(Tickets::combinedPath($ordercode));

            return true;
        }

        $pdf = new Pdf();

        foreach ($orderids as $orderid)
        {
            if ((new self($orderid))->doPDF(in_array($orderid, $newCodeIds, true), $pdf) === false)
            {
                return false;
            }
        }

        self::savePdf($pdf, Tickets::combinedPath($ordercode));

        ## The file of the single ticket this order had before others joined it
        self::deleteFile(Tickets::singlePath($ordercode));

        return true;
    }

    private static function deleteFile(string $path): void
    {
        if (file_exists($path))
        {
            File::delete($path);
        }
    }

    /**
     * Saves a PDF document to its place in the tickets folder (not reachable from the web).
     */
    private static function savePdf($pdf, string $destination): void
    {
        $temp = tempnam(JPATH_SITE . '/tmp', 'tkt');

        $pdf->Output($temp, 'F');

        File::copy($temp, $destination);
        File::delete($temp);
    }

    /**
     * The text of the "Venue" field: the venue name and city, e.g. "De Oosterpoort, Groningen".
     */
    function venueText($name, $city)
    {
        return implode(', ', array_filter([trim((string) $name), trim((string) $city)], 'strlen'));
    }

    function hexToRgb($hex, $alpha = false) {
        $hex      = str_replace('#', '', $hex);
        $length   = strlen($hex);
        $rgb['r'] = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
        $rgb['g'] = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
        $rgb['b'] = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
        if ( $alpha ) {
            $rgb['a'] = $alpha;
        }
        return $rgb;
    }

        function get_qr_image_with_logo($barcode, $qr_width, $destinationpath='', $filetype = 'PNG')
        {
            require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

            if ($destinationpath == '') {
                $destinationpath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/' . $barcode . '.png';
            }

            if ($filetype == 'SVG') {
                $writer = new SvgWriter();
            } else {
                $writer = new PngWriter();
            }

            $qrCode = new QrCode(
                data: (string) $barcode,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: (int) $qr_width,
                margin: 0,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );

            $result = $writer->write($qrCode);

            // Save it to a file
            $result->saveToFile($destinationpath);

        }
}