<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeEnlarge;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Joomla\CMS\Factory;

use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use setasign\Fpdi\FPDI_EAN13;


defined('_JEXEC') or die;

require_once (JPATH_COMPONENT . '/autoloader.php');

class ticketcreator
{

    private $eid;
    private $font;
    private $orderid;

    function __construct($eid)
    {
        $this->eid = $eid;
        $this->font = 'Raleway';
        //$this->font = 'helvetica';
    }

    function doPDF()
    {
        ## Load Mollie config to determine testmode on/off
        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Making the query for showing all the clients in list function
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        $mollieconfig = $db->loadObject();

        $mollie_test = $mollieconfig->test_mode;

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

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_venues'));
        $query->where($db->quoteName('id') . ' = ' . $db->quote((int)$order->venue));

        $db->setQuery($query);
        $locations = $db->loadObject();

        ##Ticketnummering
        ## Let op: deze volgorde moet gelijk zijn aan de sortering die wordt gebruikt bij het samenvoegen
        ## van de losse ticket-PDF's (ORDER BY seatid ASC), anders klopt het opgedrukte paginanummer niet
        ## meer met de positie van het ticket in het gecombineerde PDF-bestand.
        $query = $db->getQuery(true);

        $query->select('o.orderid');
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int)$order->ordercode));
        $query->order($db->quoteName('ext.seatid') . ' ASC');

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

        require_once __DIR__ . '/PDF/FPDI_EAN13.php';

        $pdf = new FPDI_EAN13();

        ## add a page
        $pdf->AddPage($order->ticket_orientation, $ticket_size);

        ## Image for the background :) <-- JPG file is now better to use then PDF. So use it if it exists
        $background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $order->ticketid . '.jpg';

        if(!file_exists($background))
        {
            ## This should be the source file if there is an uploaded PDF file for this event.
            $sourcefile = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $order->ticketid . '.pdf';

            ## Check if there is an updated source PDF file.
            if(!file_exists($sourcefile))
            {
                $sourcefile = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket.pdf';
            }

            ## set the sourcefile
            $pdf->setSourceFile($sourcefile);
            ## import page 1
            $tplIdx = $pdf->importPage(1);
            ## use the imported page and place it at point 0,0 with a width of 210 mm (A4 Format)
            $pdf->useTemplate($tplIdx, 0, 0, 210);

        }
        else
        {

            if($order->ticket_size == 'A4')
            {
                if($order->ticket_orientation == 'P')
                {
                    $pdf->Image($background, 0, 0, 210, 290);
                }
                else
                {
                    $pdf->Image($background, 0, 0, 290, 210);
                }
            }
            else
            {
                if($order->ticket_orientation == 'P')
                {
                    $pdf->Image($background, 0, 0, 148.5, 210);
                }
                else
                {
                    $pdf->Image($background, 0, 0, 210, 148.5);
                }
            }
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

        ## PRICE
        if(strpos($order->ticketprice_position, '-') !== false)
        {
            $position = explode("-", $order->ticketprice_position);
            $pdf->SetFont($this->font, 'B', $order->ticketprice_fontsize);
            $rgb = $this->hexToRgb($order->ticketprice_fontcolor);
            $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
            $pdf->SetXY($position[0], $position[1]);

            $price = (new TicketstationFunctions)->showprice($config->priceformat, $order->ticketprice, '');

            if($config->use_euros_in_pdf == 2)
            {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . chr(128) . ' ' . $price);
            }
            elseif($config->use_euros_in_pdf == 3)
            {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . chr(0x00A3) . ' ' . $price);
            }
            else
            {
                $pdf->Write(0, TicketLanguage::_('COM_TICKETSTATION_PDF_PRICE') . ' ' . $price);
            }
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
        if ($mollie_test == 1) {
            $barcode = '123456789012';
        } else {
            $barcode = bin2hex(random_bytes(16));
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

        $file = basename(tempnam('.', 'tmp'));
        rename($file, JPATH_SITE . '/tmp/' . $file . '.pdf');
        $file .= '.pdf';

        ## Save PDF to file now!!
        $pdf->Output(JPATH_SITE . '/tmp/' . $file, 'F');

        ## Now move the file away for security reasons
        ## Copy the file to a new directory.
        $src = JPATH_SITE . '/tmp/' . $file;

        ## The new name for the ticket
        $dest = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-' . $order->orderid . '.pdf';

        ## Copy the file now.
        File::copy($src, $dest);
        ## The old temporary file needs to be deleted.
        File::delete($src);

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

            if ($destinationpath == '') {
                $destinationpath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/cache/' . $barcode . '.png';
            }

            if ($filetype == 'SVG') {
                /*
                // Add logo
                $logopath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/qrlogo.svg';
                $logo = Logo::create($logopath)
                    ->setResizeToWidth($qr_width / 3.3)
                    ->setResizeToHeight(($qr_width / 3.3) / 0.8136) //factor 0,8136 komt voort uit aspect-ratio van Huibuuke logo
                    ->setPunchoutBackground(true);
                */
                $writer = new SvgWriter();
            } else {
                /*
                // Add logo
                $logopath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/qrlogo.png';
                $logo = Logo::create($logopath)
                    ->setResizeToWidth($qr_width / 4);
                */
                $writer = new PngWriter();
            }

            // Create QR code
            $qrCode = QrCode::create($barcode)
                ->setEncoding(new Encoding('UTF-8'))
                //->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
                //->setErrorCorrectionLevel(new ErrorCorrectionLevelQuartile())
                //->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
                ->setSize($qr_width)
                ->setMargin(0)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
                //->setRoundBlockSizeMode(new RoundBlockSizeModeEnlarge())
                //->setRoundBlockSizeMode(new RoundBlockSizeModeNone())
                ->setForegroundColor(new Color(0, 0, 0))
                ->setBackgroundColor(new Color(255, 255, 255));

            //Toevoegen logo momenteel (2-2023) uitgeschakeld ivm scanbaarheid
            //$result = $writer->write($qrCode, $logo);
            $result = $writer->write($qrCode);

            // Save it to a file
            $result->saveToFile($destinationpath);

        }
}