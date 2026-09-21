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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PDF\FPDI_EAN13;


defined('_JEXEC') or die('Restricted access');

class confirmation
{
    private $eid;
    private $font;
    private $fontsize;
    private $decode;
    private $to_be_paid;

    function __construct($eid)
    {
        ## Setting the $eid as var
        $this->eid      = $eid;
        $this->font     = 'Arial';
        $this->fontsize = 9;
        $this->decode   = [PdfEncoding::class, 'toLatin1'];
    }

    public function doConfirm()
    {
        $config = (new Config)->get();

        if ($config->send_confirmation_pdf == 2)
        {
            $confirmation = new ConfirmationPDF;
            $confirmation->create($this->eid);

            return true;
        }

        $db      = Factory::getContainer()->get('DatabaseDriver');
        $app     = Factory::getApplication();
        $session = $app->getSession();
        $decode  = $this->decode;
        
        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $select = [
            'o.*',
            't.*',
            'e.eventname',
            'c.*',
            't.ticketdate',
            't.starttime',
            't.location',
            't.locationinfo',
            'o.paid',
            'e.groupname',
            't.eventcode',
            't.ticketprice AS price',
            'ext.seatid',
        ];

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
        ## initiate FPDI
        $pdf = new FPDI_EAN13;
        ## add a page
        $pdf->AddPage();
        ## Getting the order date:
        $orderdate = Date::_($order->orderdate, $config->dateformat);
        $logo      = explode("-", $config->position_logo_confirmation);

        if (file_exists(Uri::root() . '/administrator/components/com_ticketstation/assets/images/confirmation_logo.jpg'))
        {
            $pdf->Image(Uri::root() . '/administrator/components/com_ticketstation/assets/images/confirmation_logo.jpg', $logo[0], $logo[1], 0, 20);
        }

        #############################################
        ## WRITING THE ORDERDATE                   ##
        #############################################
        $query = $db->getQuery(true);
        $query->select(['COUNT(orderid) AS total']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . " = " . $db->quote($this->eid));
        $query->where($db->quoteName('paid') . ' != ' . $db->quote(1));
        $db->setQuery($query);
        $status = $db->loadObject();
        ## Writing the orderdate on the confirmation.
        $pdf->SetFont($this->font, '', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(7, 60);
        $pdf->Write(0, Text::_('COM_TICKETSTATION_ORDERDATE') . ': ' . $orderdate);
        ## Writing the orderdate on the confirmation.
        $pdf->SetFont($this->font, '', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(7, 65);
        $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_PDF_ORDERCONFIRMATION')) . $this->eid);
        $pdf->SetXY(7, 70);

        if ($status->total > 0)
        {
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_PAYMENTSTATUS')) . ': ' . $decode(Text::_('COM_TICKETSTATION_ORDERSTATUS_UNPAID')));
        }
        else
        {
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_PAYMENTSTATUS')) . ': ' . $decode(Text::_('COM_TICKETSTATION_ORDERSTATUS_PAID')));
        }

        ###############################################
        ## WRITING THE COMPANY INFORMATION ON TICKET ##
        ###############################################
        if ($config->address_format_company == '')
        {
            ##Writing the company name
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(7, 14);
            $pdf->Write(0, $decode($config->companyname));
            ##Writing the company address
            $pdf->SetXY(7, 18);
            $pdf->Write(0, $decode($config->address1));
            ##Writing the company zipcode+city
            $pdf->SetXY(7, 22);
            $pdf->Write(0, $config->zipcode . ' ' . $decode($config->city));
            ##Writing the company phone
            $pdf->SetXY(7, 26);
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_PHONE')) . ' ' . $decode($config->phone));
            ##Writing the company email
            $pdf->SetXY(7, 30);
            $pdf->Write(0, $decode($config->email));
            ##Writing the company website
            $pdf->SetXY(7, 34);
            $pdf->Write(0, $decode($config->website));
        }
        else
        {
            if (ini_get('magic_quotes_gpc') == '1')
            {
                $body = stripslashes($config->address_format_company);
            }
            else
            {
                $body = $decode($config->address_format_company);
            }
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(7, 14);
            $pdf->MultiCell(0, 5, "$body", 0, 'L', 0);
        }

        ################################################
        ## WRITING THE CUSTOMER INFORMATION ON TICKET ##
        ################################################

        if ($config->address_format_client == '')
        {
            ## Writing the clientname.
            $pdf->SetFont($this->font, '', $this->fontsize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(135, 51);

            if ($order->firstname == '')
            {
                $pdf->Write(0, $decode($order->name));
            }
            else
            {
                $pdf->Write(0, $decode($order->firstname) . ' ' . $decode($order->name));
            }

            ## Writing the client address.
            $pdf->SetXY(135, 55);
            $pdf->Write(0, $decode($order->address));
            ## Writing the zipcode & city.
            $pdf->SetXY(135, 59);
            $pdf->Write(0, $order->zipcode . ' ' . $decode($order->city));
            ## Writing the zipcode & city.
            $pdf->SetXY(135, 63);
            $pdf->Write(0, $order->emailaddress);
        }
        else
        {
            $client_address = str_replace('%%FIRSTNAME%%', $decode($order->firstname), $config->address_format_client);
            $client_address = str_replace('%%LASTNAME%%', $decode($order->name), $client_address);
            $client_address = str_replace('%%ADDRESS1%%', $decode($order->address), $client_address);
            $client_address = str_replace('%%ADDRESS2%%', $decode($order->address2), $client_address);
            $client_address = str_replace('%%ZIPCODE%%', $decode($order->zipcode), $client_address);
            $client_address = str_replace('%%CITY%%', $decode($order->city), $client_address);

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

        #############################################
        ## WRITING THE GRID TOP TEXTURES HERE      ##
        #############################################
        $pdf->SetDrawColor(193, 193, 193);
        $pdf->Line(10, 96, 200, 96);
        $pdf->Line(10, 96.7, 200, 96.7);
        $pdf->SetFont($this->font, 'B', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(12, 91.5);
        $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_QTY')));
        $pdf->SetFont($this->font, 'B', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(27, 91.5);
        $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TICKETINFORMATION')));
        $pdf->SetFont($this->font, 'B', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(159, 91.5);
        $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TIME')));
        $pdf->SetFont($this->font, 'B', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(182, 91.5);
        $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_PRICE')));

        #############################################
        ## WRITING THE GRID - PLEASE DONT CHANGE   ##
        #############################################

        $query = $db->getQuery(true);
        QueryHelper::enableBigSelects($db);
        $query  = $db->getQuery(true);
        $select = [
            'COUNT(o.orderid) AS total',
            'o.*',
            't.*',
            'e.eventname',
            'c.*',
            't.ticketdate',
            't.starttime',
            't.location',
            't.locationinfo',
            'o.paid',
            'e.groupname',
            't.eventcode',
            't.ticketprice AS price',
            'v.venue AS location',
            'v.city',
            't.show_end_date',
            't.end_date',
        ];
        $query->select($select);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('o.userid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON (' . $db->quoteName('t.venue') . ' = ' . $db->quoteName('v.id') . ')');
        $query->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((int) $this->eid));
        $query->group('o.ticketid');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        ## Setting the startgrid, DON'T change this!
        $height1          = 100;
        $height2          = 104;
        $grand_totalprice = 0.00;
        $payment_status   = 1;
        ## Setting font color & font
        $pdf->SetFont($this->font, '', $this->fontsize);
        $pdf->SetTextColor(0, 0, 0);

        for ($i = 0, $n = count($items); $i < $n; $i++)
        {
            $row = $items[$i];
            if ($row->coupon != '')
            {
                
                $session = $app->getSession();
                ## Gettig the orderid if there is one.
                $couponcode = $session->set('coupon', $row->coupon);
            }
            if ($row->paid != 1)
            {
                $payment_status = 0;
            }

            ## Writing the itemid on the left collumn
            ## Make sure they are centered, see function below
            $chars = strlen($row->total);

            if ($chars == 1)
            {
                $pdf->SetXY(13, $height1);
            }
            if ($chars == 2)
            {
                $pdf->SetXY(12, $height1);
            }
            if ($chars == 3)
            {
                $pdf->SetXY(12, $height1);
            }
            if ($chars == 4)
            {
                $pdf->SetXY(11, $height1);
            }
            if ($chars == 5)
            {
                $pdf->SetXY(10, $height1);
            }

            $pdf->Write(0, $row->total . 'x');

            if ($row->show_end_date != 1)
            {
                $ticketdate = date($config->dateformat, strtotime($row->ticketdate));
            }
            else
            {
                $ticketdate = date($config->dateformat, strtotime($row->ticketdate)) . ' - ' . date($config->dateformat, strtotime($row->end_date));
            }

            $pdf->SetXY(27, $height1);

            if ($row->parentname != $row->ticketname)
            {
                $pdf->Write(0, $decode($row->eventname) . ' (' . $decode($row->eventcode) . ')  ' . $decode($row->ticketname) . ' - (' . $ticketdate . ')');
            }
            else
            {
                $pdf->Write(0, $decode($row->eventcode) . ' / ' . $decode($row->ticketname) . ' (' . $ticketdate . ')');
            }

            if ($row->seat_sector != 0)
            {
                $query = $db->getQuery(true);
                $query->select('*');
                $query->from($db->quoteName('#__ticketstation_seatplancoords'));
                $query->where($db->quoteName('id') . ' = ' . $db->quote($row->seat_sector));
                $db->setQuery($query);
                $seat       = $db->loadObject();
                $seatnumber = Text::_('COM_TICKETSTATION_SEAT_NR') . ': ' . $seat->row_name . $seat->seatid;
                ## Event information, second line
                $pdf->SetXY(27, $height2);
                $pdf->Write(0, $seatnumber . ' -- ' . $decode($row->location) . ' - ' . $decode($row->city));
            }
            else
            {
                ## Event information, second line
                $pdf->SetXY(27, $height2);
                $pdf->Write(0, $decode($row->location) . ' - ' . $decode($row->city));
            }

            if ($order->starttime != '')
            {
                $start_time = $order->starttime;
            }
            else
            {
                $start_time = date($config->time_format, strtotime($order->ticketdate));
            }

            ## Event information, the date field
            $pdf->SetXY(159, $height1);
            $pdf->Write(0, $start_time);

            ## Event information, the date field
            if ($config->use_euros_in_pdf == 2)
            {
                $price = showprice($config->priceformat, $row->ticketprice * $row->total, '');
                $price = chr(128) . ' ' . $price;
            }
            elseif ($config->use_euros_in_pdf == 3)
            {
                $price = showprice($config->priceformat, $row->ticketprice * $row->total, '');
                $price = chr(0x00A3) . ' ' . $price;
            }
            else
            {
                $price = showprice($config->priceformat, $row->ticketprice * $row->total, $config->valuta);
            }

            $pdf->SetXY(180, $height1);
            $pdf->Write(0, $price);
            $y = $pdf->GetY();
            $pdf->SetDrawColor(193, 193, 193);
            $pdf->Line(10, $y + 6.7, 200, $y + 6.7);
            ## Add extra row to the FOR loop.
            $height1 = $height1 + 10;
            $height2 = $height2 + 10;
            ## Count the price for confirmation with pending tickets.
            $grand_totalprice = $grand_totalprice + ($row->ticketprice * $row->total);
        }

        if ($session->get('coupon') == '')
        {
            $pdf->Line(10, $y + 7.4, 200, $y + 7.4);
        }

        ### IF DISCOUNT == TRUE == WRITE AN EXTRA LINE WITH THE DISCOUNT ###
        $discount = 0;

        if ($session->get('coupon') != '')
        {
            $pdf->SetXY(10, $height1);
            $query = $db->getQuery(true);
            $query->select('*');
            $query->from($db->quoteName('#__ticketstation_coupons'));
            $query->where($db->quoteName('coupon_code') . ' = ' . $db->quote($session->get('coupon')));
            $db->setQuery($query);
            $coupon = $db->loadObject();

            if ($coupon->coupon_type == 1)
            {
                $discount = ($grand_totalprice / 100) * $coupon->coupon_discount;
            }
            else
            {
                $discount = $coupon->coupon_discount;
            }

            if ($config->use_euros_in_pdf == 2)
            {
                $price = showprice($config->priceformat, $discount, '');
                $price = chr(128) . ' ' . $price;
            }
            elseif ($config->use_euros_in_pdf == 3)
            {
                $price = showprice($config->priceformat, $discount, '');
                $price = chr(0x00A3) . ' ' . $price;
            }
            else
            {
                $price = showprice($config->priceformat, $discount, $config->valuta);
            }

            $pdf->SetXY(180, $height1);
            $pdf->Write(0, $price . ' -/-');
            $pdf->SetXY(27, $height1);

            if ($discount->coupon_type == 1)
            {
                $tmp = $disco->coupon_discount . '%';
                $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_YOUR_DISCOUNT_PRICE') . ': (' . $tmp . ')'));
            }
            else
            {
                $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_YOUR_DISCOUNT_PRICE')));
            }

            $pdf->SetXY(27, $height2);
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_COUPONCODE') . ': ' . $coupon->coupon_code));
            $y = $pdf->GetY();
            $pdf->SetDrawColor(193, 193, 193);
            $pdf->Line(10, $y + 3, 200, $y + 3);
        }

        if ($session->get('coupon') != '')
        {
            $pdf->Line(10, $y + 3.6, 200, $y + 3.6);
        }
        #############################################
        ## WRITING THE TOTALS OF THE ORDER         ##
        #############################################
        ## GEt the y-position:
        $y = $pdf->GetY();
        $y = $y + 10;

        if ($config->use_euros_in_pdf == 2)
        {
            $price = showprice($config->priceformat, $grand_totalprice - $discount, '');
            $price = chr(128) . ' ' . $price;
        }
        elseif ($config->use_euros_in_pdf == 3)
        {
            $price = showprice($config->priceformat, $grand_totalprice - $discount, '');
            $price = chr(0x00A3) . ' ' . $price;
        }
        else
        {
            $price = showprice($config->priceformat, $grand_totalprices - $discount, $config->valuta);
        }

        ## Event information, the price field
        $pdf->SetXY(180, $y + 10);
        $pdf->Write(0, $price);

        if ($config->variable_transcosts == 1)
        {
            $totalfee = ((($grand_totalprice - $discount) / 100) * $config->transcosts) + $config->transactioncosts;
            ## Event information, the price field
            $pdf->SetXY(150, $y + 15);
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TOTALFEE')));
            ## Event information, the price field
            $pdf->SetXY(150, $y + 21);
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TOTAL')));

            if ($config->use_euros_in_pdf == 2)
            {
                $price = showprice($config->priceformat, $totalfee, '');
                $price = chr(128) . ' ' . $price;
            }
            elseif ($config->use_euros_in_pdf == 3)
            {
                $price = showprice($config->priceformat, $totalfee, '');
                $price = chr(0x00A3) . ' ' . $price;
            }
            else
            {
                $price = showprice($config->priceformat, $totalfee, $config->valuta);
            }

            $pdf->SetXY(180, $y + 15);
            $pdf->Write(0, $price);
            ## Draw a line to count total amount
            $pdf->Line(150, $y + 18, 200, $y + 18);
            $pdf->Line(150, $y + 18.5, 200, $y + 18.5);

            if ($config->use_euros_in_pdf == 2)
            {
                $price = showprice($config->priceformat, $grand_totalprice - $discount + $totalfee, '');
                $price = chr(128) . ' ' . $price;
            }
            elseif ($config->use_euros_in_pdf == 3)
            {
                $price = showprice($config->priceformat, $grand_totalprice - $discount + $totalfee, '');
                $price = chr(0x00A3) . ' ' . $price;
            }
            else
            {
                $price = showprice($config->priceformat, $grand_totalprice - $discount + $totalfee, $config->valuta);
            }

            ## Draw a line to count total amount
            $pdf->Line(150, $y + 23.5, 200, $y + 23.5);
            $pdf->Line(150, $y + 24.1, 200, $y + 24.1);
            $pdf->SetXY(180, $y + 21);
            $pdf->Write(0, $price);
            $pdf->SetXY(150, $y + 10);
            $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_SUBTOTAL')));
        }
        else
        {
            if ($config->transactioncosts != 0)
            {
                $pdf->SetXY(150, $y + 15);
                $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TOTALFEE')));
                ## Event information, the price field
                $pdf->SetXY(150, $y + 21);
                $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_TOTAL')));
                $totalfee = $config->transactioncosts;

                if ($config->use_euros_in_pdf == 2)
                {
                    ## Fixing the euro issue..
                    $price = showprice($config->priceformat, $config->transactioncosts, '');
                    $price = chr(128) . ' ' . $price;
                }
                elseif ($config->use_euros_in_pdf == 3)
                {
                    $price = showprice($config->priceformat, $config->transactioncosts, '');
                    $price = chr(0x00A3) . ' ' . $price;
                }
                else
                {
                    $price = showprice($config->priceformat, $config->transactioncosts, $config->valuta);
                }

                $pdf->SetXY(180, $y + 15);
                $pdf->Write(0, $price);
                ## Draw a line to count total amount
                $pdf->Line(150, $y + 23.5, 200, $y + 23.5);
                $pdf->Line(150, $y + 24.1, 200, $y + 24.1);

                if ($config->use_euros_in_pdf == 2)
                {
                    ## Fixing the euro issue..
                    $price = showprice($config->priceformat, ($grand_totalprice - $discount) + $totalfee, '');
                    $price = chr(128) . ' ' . $price . '-';
                }
                elseif ($config->use_euros_in_pdf == 3)
                {
                    $price = showprice($config->priceformat, ($grand_totalprice - $discount) + $totalfee, '');
                    $price = chr(0x00A3) . ' ' . $price;
                }
                else
                {
                    $price = showprice($config->priceformat, ($grand_totalprice - $discount) + $totalfee, $config->valuta);
                }

                $pdf->SetXY(180, $y + 21);
                $pdf->Write(0, $price);
                ## Draw a line to count total amount
                $pdf->Line(150, $y + 18, 200, $y + 18);
                $pdf->Line(150, $y + 18.5, 200, $y + 18.5);
                $pdf->SetXY(150, $y + 10);
                $pdf->Write(0, $decode(Text::_('COM_TICKETSTATION_SUBTOTAL')));
            }
        }

        $this->to_be_paid = ($grand_totalprice - $discount) + $totalfee;
        $session->set('coupon', '');
        $file = basename(tempnam('.', 'tmp'));

        rename($file, Uri::root() . '/tmp/' . $this->eid . '.pdf');
        $file .= '.pdf';

        ## Save PDF to file now!!
        $pdf->Output(Uri::root() . '/tmp/' . $file, 'F');



        ## Copy the file to a new directory.
        $src = Uri::root() . '/tmp/' . $file;

        ## The new name for the ticket
        $dest = Uri::root() . '/administrator/components/com_ticketstation/tickets/confirmation/' . $this->eid . '.pdf';

        ## Copy the file now.
        File::copy($src, $dest);

        ## The old temporary file needs to be deleted.
        File::delete($src);
    }

    public function doSend()
    {
        $mainframe = Factory::getApplication();

        if ( ! class_exists('paymentAPI'))
        {
            exit("Class is not available to process transactions.");
        }

        $payment_helper = new paymentAPI((int) $this->eid);

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $config = $payment_helper->getConfig();
        $user   = $payment_helper->getUserInformation();

        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote($this->eid));
        $query->where('(' . $db->quoteName('paid') . ' = 0 OR ' . $db->quoteName('paid') . ' = 3)');
        $db->setQuery($query);

        $status = $db->loadObject();

        if (count($status) > 0)
        {
            $paymentstatus = '<span style="font-color=#FF0000;">' . Text::_('COM_TICKETSTATION_ORDERSTATUS_UNPAID') . '</span>';
        }
        else
        {
            $paymentstatus = '<span style="font-color=#006600;">' . Text::_('COM_TICKETSTATION_ORDERSTATUS_PAID') . '</span>';
        }

        $date        = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        $expired     = mktime(0, 0, 0, date("m"), date("d") + $config->removal_days, date("Y"));
        $releasedate = date($config->dateformat, $expired);

        // Getting the amounts from the database.
        $amount  = (new Amount)->getAmountsAsJsonObject($this->eid);
        $amount  = json_decode($amount, true);

        // Setting some prices.
        $price    = $amount['order_total'] + $amount['order_fees'];
        $fees     = ! empty($amount['order_fees']) ? $amount['order_fees'] : 0;
        $discount = ! empty($amount['order_discount']) ? $amount['order_discount'] : 0;

        //Setting the confirmation PDF
        $filename = Uri::root() . '/administrator/components/com_ticketstation/tickets/confirmation/' . $this->eid . '.pdf';

        // Starting the message.
        $message = new eTicketsMessage;

        $variables = [
            'orderlist'     => $payment_helper->getOrderList(),
            'orderdate'     => $date,
            'ordercode'     => $this->eid,
            'price'         => Price::_($price),
            'fees'          => Price::_($fees),
            'discount'      => Price::_($discount),
            'vat_amount'    => Price::_($discount),
            'paymentstatus' => $paymentstatus,
            'tickets'       => $amount['order_items'],
            'releasedate'   => $releasedate,
        ];

        // Sending the message
        $message->id(3)
            ->user($user->userid)
            ->variables($variables)
            ->attachment($filename)
            ->send();

        return true;
    }

    public function SendWaitingList()
    {
        ## Check if the class exsists:
        if ( ! class_exists('paymentAPI'))
        {
            exit("Class is not available to process transactions.");
        }

        $payment_helper = new paymentAPI((int) $this->eid);
        $db             = Factory::getContainer()->get('DatabaseDriver');
        $query          = $db->getQuery(true);
        $query->select(['COUNT(id) AS total']);
        $query->from($db->quoteName('#__ticketstation_waitinglist'));
        $query->where($db->quoteName('ordercode') . ' = ' . (int) $this->eid);
        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result->total == 0)
        {
            return true;
        }

        $query = $db->getQuery(true);
        $query->select(['c.userid', 'w.id AS waitinglist_id']);
        $query->from($db->quoteName('#__ticketstation_waitinglist', 'w'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('c.userid') . ' = ' . $db->quoteName('w.userid') . ')');
        $query->where($db->quoteName('w.ordercode') . ' = ' . (int) $this->eid);
        $query->group('w.ordercode');
        $db->setQuery($query);
        $user = $db->loadObject();

        $config = $payment_helper->getConfig();
        ## Global things for this email:

        $date       = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        $date       = date($config->dateformat, strtotime($date));
        $to_be_paid = (new getAmount())->_getAmount($this->eid);
        $price      = TicketstationFunctions::showprice($config->priceformat, $to_be_paid, $config->valuta);

        ## getOrderCount() only reflects the previous getWaitingList()/getOrderList() call,
        ## so it must run after getWaitingList() below, not before it.
        $orderlist     = $payment_helper->getWaitingList();
        $total_tickets = $payment_helper->getOrderCount();
        $message       = new eTicketsMessage;

        ## Generate confirmation link using waitinglist validation token (if available)
        $confirmationlink = '';
        if ($user && isset($user->waitinglist_id))
        {
            $confirmationlink = $payment_helper->generateWaitingListConfirmationLink($user->waitinglist_id);
        }
        if (empty($confirmationlink))
        {
            ## Fallback to ordercode-based link for backward compatibility
            $confirmationlink = $payment_helper->generateConfirmationLink($this->eid);
        }

        $variables = [
            'orderlist'        => $orderlist,
            'orderdate'        => $date,
            'ordercode'        => $this->eid,
            'price'            => $price,
            'confirmationlink' => $confirmationlink,
            'tickets'          => $total_tickets,
        ];

        $message->id(4)
            ->user($user->userid)
            ->variables($variables)
            ->send();

        $query      = $db->getQuery(true);
        $fields     = [
            $db->quoteName('sent') . ' = ' . $db->quote('1'),
            $db->quoteName('date_sent') . ' = ' . $db->quote(date('Y-m-d H:i:s')),
        ];
        $conditions = [$db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->eid)];

        $query->update($db->quoteName('#__ticketstation_waitinglist'))
            ->set($fields)
            ->where($conditions);
        $db->setQuery($query);

        if ($db->execute() == true)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

}