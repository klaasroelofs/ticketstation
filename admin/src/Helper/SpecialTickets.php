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

use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PDF\FPDI_EAN13;

## no direct access
defined('_JEXEC') or die('Restricted access');

class special
{
    private $eid;
    private $font;
    private $fontsize;

    function __construct($eid)
    {
        ## Setting the $eid as var
        $this->eid      = $eid;
        $this->font     = 'Arial';
        $this->fontsize = 9;
    }

    public function create()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $config = $db->loadObject();

        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $select = array('o.*', 't.*', 'e.eventname', 'c.*', 't.ticketdate', 't.starttime', 't.location',
                't.locationinfo', 'o.paid', 'e.eventcode', 't.ticketcode', 't.ticketprice AS price', 'ext.seatid');

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');

        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid') . ')');

        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->eid));
        $query->group('o.orderid');

        $db->setQuery($query);
        $order = $db->loadObject();

        ## CSetting the size of the document.
        $pdf = new FPDI_EAN13('P', 'mm', 'A4');

        ## add a page
        $pdf->AddPage();

        ## Getting the order date:
        $orderdate = Date::_($order->orderdate, $config->dateformat);

        ### setting the header for the page: (w) 210mm x (h) 90mm
        if (file_exists(Uri::root() . '/administrator/components/com_ticketstation/assets/images/header.jpg'))
        {
            $pdf->Image(Uri::root() . '/administrator/components/com_ticketstation/assets/images/header.jpg', 0, 0, 210, 90);
        }

        $total    = _getAmount($this->eid, 0, 0);
        $fees     = _getFees($this->eid);
        $discount = _getDiscount($this->eid);

        ###############################################
        ## WRITING THE COMPANY INFORMATION ON TICKET ##
        ###############################################

        if ($config->address_format_company == '')
        {
            ##Writing the company name
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 35);
            $pdf->Write(0, PdfEncoding::toLatin1($config->companyname));


            ##Writing the company address
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 45);
            $pdf->Write(0, PdfEncoding::toLatin1($config->address1));

            ##Writing the company zipcode+city
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 50);
            $pdf->Write(0, $config->zipcode . ' ' . PdfEncoding::toLatin1($config->city));

            ##Writing the company phone
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 55);
            $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_PHONE')) . ' ' . PdfEncoding::toLatin1($config->phone));

            ##Writing the company email
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 60);
            $pdf->Write(0, $config->email);

            ##Writing the company website
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 65);
            $pdf->Write(0, $config->website);

            $start_height = $pdf->GetY() + 10;
        }
        else
        {
            if (ini_get('magic_quotes_gpc') == '1')
            {
                $body = stripslashes($config->address_format_company);
            }
            else
            {
                $body = PdfEncoding::toLatin1($config->address_format_company);
            }

            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 40);
            $pdf->MultiCell(0, 5, "$body", 0, 'L', 0);

            $start_height = $pdf->GetY() + 10;
        }

        ################################################
        ## WRITING THE CUSTOMER INFORMATION ON TICKET ##
        ################################################

        if ($order->gender == 1)
        {
            $salutation = Text::_('COM_TICKETSTATION_MR');
        }
        else if ($order->gender == 2)
        {
            $salutation = Text::_('COM_TICKETSTATION_MRS');
        }
        else if ($order->gender == 3)
        {
            $salutation = Text::_('COM_TICKETSTATION_MISS');
        }
        else
        {
            $salutation = Text::_('COM_TICKETSTATION_FAMILY');
        }

        if ($config->address_format_client == '')
        {
            ## Writing the clientname.
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 51);

            if ($order->firstname == '')
            {
                $pdf->Write(0, PdfEncoding::toLatin1($order->name));
            }
            else
            {
                $pdf->Write(0, PdfEncoding::toLatin1($salutation) . PdfEncoding::toLatin1($order->firstname) . ' ' . PdfEncoding::toLatin1($order->name));
            }

            ## Writing the client address.
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 55);
            $pdf->Write(0, PdfEncoding::toLatin1($order->address));

            ## Writing the zipcode & city.
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 59);
            $pdf->Write(0, $order->zipcode . ' ' . PdfEncoding::toLatin1($order->city));

            ## Writing the emailaddress
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 63);
            $pdf->Write(0, $order->emailaddress);

        }
        else
        {

            $client_address = str_replace('%%FIRSTNAME%%', PdfEncoding::toLatin1($order->firstname), $config->address_format_client);
            $client_address = str_replace('%%LASTNAME%%', PdfEncoding::toLatin1($order->name), $client_address);
            $client_address = str_replace('%%SALUTATION%%', PdfEncoding::toLatin1($salutation), $client_address);
            $client_address = str_replace('%%ADDRESS1%%', PdfEncoding::toLatin1($order->address), $client_address);
            $client_address = str_replace('%%ADDRESS2%%', PdfEncoding::toLatin1($order->address2), $client_address);
            $client_address = str_replace('%%ZIPCODE%%', PdfEncoding::toLatin1($order->zipcode), $client_address);
            $client_address = str_replace('%%CITY%%', PdfEncoding::toLatin1($order->city), $client_address);

            if (ini_get('magic_quotes_gpc') == '1')
            {
                $body = stripslashes($client_address);
            }
            else
            {
                $body = $client_address;
            }

            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 51);
            $pdf->MultiCell(0, 5, "$body", 0, 'L', 0);
        }

        ## PRINT THE TICKETS NOW IN LIST ##
        ## FIRST WE HAVE TO GET ALL ITEMS FOR THIS TICKET ##
        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $select = array('o.*', 't.*', 'e.eventname', 'c.*', 't.ticketdate', 't.starttime', 't.location', 't.locationinfo', 'o.paid', 'e.groupname',
            't.eventcode', 't.show_end_date', 't.end_date');

        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');

        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->eid));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        ## Setting the order number:
        $pdf->SetFont($this->font, '', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(10, $start_height);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_ENTRANCE_TICKETS_FOR')) . ' ' . $this->eid);
        ## Writing the name customer:
        $pdf->SetXY(10, $start_height + 5);

        if ($order->firstname == '')
        {
            $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_TICKETS_FOR_CUSTOMER')) . ' ' . PdfEncoding::toLatin1($order->name));
        }
        else
        {
            $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_TICKETS_FOR_CUSTOMER')) . ' ' . PdfEncoding::toLatin1($order->firstname) . ' ' . PdfEncoding::toLatin1($order->name));
        }

        $pdf->SetXY(10, $start_height + 10);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_TICKETS_ORDER_DATE')) . ' ' . $orderdate);

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(10, 105, 200, 105);

        ## Setting the startgrid, DO NOT change this!
        $height = 105;
        $height = $pdf->GetY() + 20;

        ## Setting font color & font for all items below:
        $pdf->SetFont($this->font, '', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);

        for ($i = 0, $n = count($data); $i < $n; $i++)
        {
            $row = $data[$i];

            if ($row->seat_sector != 0)
            {
                $query = $db->getQuery(true);

                $query->select('*');
                $query->from($db->quoteName('#__ticketstation_seatplancoords'));
                $query->where($db->quoteName('id') . ' = ' . $db->quote($row->seat_sector));

                $db->setQuery($query);
                $seat = $db->loadObject();

                $seatnumber = Text::_('COM_TICKETSTATION_SEAT_NR') . ': ' . $seat->row_name . $seat->seatid;

                $pdf->SetXY(10, $height + 2);
                if ($row->parentname != $row->ticketname)
                {
                    $pdf->SetFont($this->font, '', $this->fontsize);
                    $pdf->Write(0, PdfEncoding::toLatin1($row->eventname) . ' (' . PdfEncoding::toLatin1($row->eventcode) . ') - ' .
                        PdfEncoding::toLatin1($row->ticketname) . ' - ' . PdfEncoding::toLatin1($seatnumber));
                }
                else
                {
                    $pdf->SetFont($this->font, '', $this->fontsize);
                    $pdf->Write(0, PdfEncoding::toLatin1($row->eventcode) . ' / ' . PdfEncoding::toLatin1($row->ticketname) . ' - ' . PdfEncoding::toLatin1($seatnumber));
                }
            }
            else
            {
                $pdf->SetXY(10, $height + 2);
                if ($row->parentname != $row->ticketname)
                {
                    $pdf->SetFont($this->font, '', $this->fontsize);
                    $pdf->Write(0, PdfEncoding::toLatin1($row->eventname) . ' (' . PdfEncoding::toLatin1($row->eventcode) . ') - ' . PdfEncoding::toLatin1($row->ticketname));
                }
                else
                {
                    $pdf->SetFont($this->font, '', $this->fontsize);
                    $pdf->Write(0, PdfEncoding::toLatin1($row->eventname) . ' (' . PdfEncoding::toLatin1($row->eventcode) . ') - ' . PdfEncoding::toLatin1($row->ticketname));
                }
            }

            $pdf->SetXY(10, $height + 7);

            $end = '';

            if ($row->show_end_date != 1)
            {
                $ticketdate = date($config->dateformat, strtotime($row->ticketdate));
            }
            else
            {
                $ticketdate = date($config->dateformat, strtotime($row->ticketdate)) . ' - ' . date($config->dateformat, strtotime($row->end_date));
                $end        = ' - ' . date($config->time_format, strtotime($row->end_date));
            }

            if ($row->starttime != '')
            {
                $start = PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_STARTTIME')) . ' ' . Date::_($row->starttime);
            }
            else
            {
                $start = date($config->time_format, strtotime($row->ticketdate));
            }

            if ($config->use_euros_in_pdf == 2)
            {
                ## Fixing the euro issue..
                $price = showprice($config->priceformat, $row->ticketprice, '');
                $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_ORDER')) . ' ' . $row->orderid . ' :: ' .
                    PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_PDF_PRICE')) . ' ' . chr(128) . ' ' .
                    $price . ' :: ' . Text::_('COM_TICKETSTATION_PDF_DATE') . ' ' . $ticketdate . ' :: ' . $start . $end);
            }
            elseif ($config->use_euros_in_pdf == 3)
            {
                $price = showprice($config->priceformat, $row->ticketprice, '');
                $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_ORDER')) . ' ' . $row->orderid . ' :: ' .
                    PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_PDF_PRICE')) . ' ' . chr(0x00A3) . ' ' .
                    $price . ' :: ' . Text::_('COM_TICKETSTATION_PDF_DATE') . ' ' . $ticketdate . ' :: ' . $start . $end);
            }
            else
            {
                $price = showprice($config->priceformat, $row->ticketprice, $config->valuta);
                $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_ORDER')) . $row->orderid . ' :: ' .
                    PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_PDF_PRICE')) . ' ' . $price . ' :: ' .
                    Text::_('COM_TICKETSTATION_PDF_DATE') . ' ' . $ticketdate . ' :: ' . $start . $end);
            }

            ## Getting the height for the closing line -- will only show after the for loop has been completed:
            $y = $pdf->GetY();

            ## Creating the QR Code for printing.
            if ($row->pdf_use_qrcode == 1)
            {
                ## Creating the code:
                $code = $row->ordercode . $row->orderid;

                ## Getting the barcode from the EAN script.
                $barcode = $pdf->EAN13(1, 1, $code, $order->pdf_use_qrcode, 1);

                $cache_folder = Uri::root() . '/administrator/components/com_ticketstation/classes/cache/';
                $pdf->Image($cache_folder . $barcode . '.png', 175, $height, 20, 20);

                $pdf->SetDrawColor(193, 193, 193);

                ## We do want to remove the QR code again.
                ## It is not needed anymore, as ticket has been printed.
                File::delete($cache_folder . $barcode . '.png');
            }
            else
            {
                ## Writing the code on the ticket.
                $pdf->EAN13(160, $height, $row->barcode, 0);

                $pdf->SetDrawColor(193, 193, 193);
                $pdf->Line(10, $height + 20, 200, $height + 20);
            }

            $pdf->SetXY(10, $height + 12);
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_BARCODE')) . ': ' . $row->barcode);

            $height = $height + 25;

            ## Path to a combined ticket is as below:
            $combined_ticket = Uri::root() . '/administrator/components/com_ticketstation/tickets/eTickets-' . $this->eid . '.pdf';

            ## remove tickets if there is a combined one.
            if (file_exists($combined_ticket))
            {
                File::delete($combined_ticket);
            }

            ## Path to a normal ticket is as below:
            $path = Uri::root() . '/administrator/components/com_ticketstation/tickets/eTicket-' . $row->orderid . '.pdf';

            ## Remove single ticket
            if (file_exists($path))
            {
                File::delete($path);
            }

            $space_left = 290 - $pdf->GetY();

            if ($space_left < 40)
            {
                $pdf->AddPage();

                if (file_exists(Uri::root() . '/administrator/components/com_ticketstation/assets/images/header.jpg'))
                {
                    $pdf->Image(Uri::root() . '/administrator/components/com_ticketstation/assets/images/header.jpg', 0, 0, 210, 90);
                }

                ## Set margin for next page:
                $height = 50;
            }
        }

        $file = basename(tempnam('.', 'tmp'));
        rename($file, Uri::root() . '/tmp/e-Tickets-' . $this->eid . '.pdf');
        $file .= '.pdf';

        ## Save PDF to file now!!
        $pdf->Output(Uri::root() . '/tmp/' . $file, 'F');

        ## Now move the file away for security reasons

        ## Copy the file to a new directory.
        $src = Uri::root() . '/tmp/' . $file;

        ## The new name for the ticket
        $dest = Uri::root() . '/administrator/components/com_ticketstation/tickets/eTickets-' . $this->eid . '.pdf';

        ## Copy the file now.
        File::copy($src, $dest);
        ## The old temporary file needs to be deleted.
        File::delete($src);

        return true;
    }

}