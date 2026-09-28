<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

## no direct access

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use setasign\Fpdi\FPDF;
use stdClass;

defined('_JEXEC') or die('Restricted access');

/**
 * Generates, stores and sends order invoices. Built on the same FPDF stack already used for
 * ticket PDFs (setasign/fpdi, bundled with the component) - deliberately not the mPDF-based
 * approach this used to depend on, which required a separate Joomla Library install that was
 * never actually present.
 *
 * @since 2.0.11
 */
class Invoice
{
    /**
     * Creates (if not already created) and optionally sends the invoice for an order. Safe to
     * call more than once for the same order - reuses the existing invoice row and PDF instead
     * of creating duplicates.
     *
     * @param   int   $ordercode
     * @param   bool  $send
     *
     * @return  bool
     *
     * @since 2.0.11
     */
    public function create($ordercode, $send = true)
    {
        $ordercode = (int) $ordercode;

        // Order completion (and so invoice creation) can be triggered from the site side
        // (e.g. a Mollie payment) or from an admin whose own backend language differs from the
        // shop's language. Either way, the invoice itself should always read in the site's
        // globally configured frontend language (Global Configuration > Site > Language),
        // regardless of which side or which admin user triggered it. $reload=true forces this
        // to take effect even if com_ticketstation's admin language was already loaded for a
        // different tag earlier in the same request.
        $siteLanguageTag = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        Factory::getApplication()->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR, $siteLanguageTag, true);

        $userid = (new Order)->getUserByOrderCode($ordercode);

        if ( ! $userid)
        {
            return false;
        }

        $invoice = $this->getInvoiceByOrderode($ordercode);

        if (empty($invoice))
        {
            $invoiceid = $this->addToDatabase($ordercode, $userid);

            if ( ! $invoiceid)
            {
                return false;
            }

            $invoice = $this->getInvoiceById($invoiceid);
        }

        if ( ! $this->generatePdf($invoice))
        {
            return false;
        }

        if ($send)
        {
            return $this->send($invoice);
        }

        return true;
    }

    /**
     * Getting all information for a specific invoice.
     *
     * @param      $id
     * @param null $userid
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getInvoiceById($id, $userid = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_invoices'))
            ->where($db->quoteName('invoiceid') . ' = ' . (int) $id);

        if ($userid)
        {
            $query->where($db->quoteName('userid') . ' = ' . (int) $userid);
        }

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting all information for a specific invoice.
     *
     * @param      $ordercode
     * @param null $userid
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getInvoiceByOrderode($ordercode, $userid = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_invoices'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        if ($userid)
        {
            $query->where($db->quoteName('userid') . ' = ' . (int) $userid);
        }

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Deletes an order's invoice (row, line items and PDF), if it has one. Used when an order
     * is manually removed from Box Office - an invoice for a deleted order shouldn't survive it.
     *
     * @param   int  $ordercode
     *
     * @return  bool
     *
     * @since 2.0.11
     */
    public function remove($ordercode)
    {
        $invoice = $this->getInvoiceByOrderode((int) $ordercode);

        if (empty($invoice))
        {
            return true;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_invoice_items'))
            ->where($db->quoteName('invoiceid') . ' = ' . (int) $invoice->invoiceid);
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_invoices'))
            ->where($db->quoteName('invoiceid') . ' = ' . (int) $invoice->invoiceid);
        $db->setQuery($query);
        $db->execute();

        $pdfPath = $this->getPdfPath($invoice->invoiceid);

        if (file_exists($pdfPath))
        {
            File::delete($pdfPath);
        }

        return true;
    }

    /**
     * Inserts the invoice header and its per-ticket-type line items.
     *
     * @param   int  $ordercode
     * @param   int  $userid
     *
     * @return  int|false  The new invoiceid, or false on failure.
     *
     * @since 2.0.11
     */
    private function addToDatabase($ordercode, $userid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // The invoice adds up the order rows: make sure their discount matches the coupon of
        // the order, also for tickets added after the coupon was applied.
        Coupon::refresh((int) $ordercode);

        $amount      = OrderTotals::get($ordercode);
        $transaction = (new Transaction)->getTransactionDetails($ordercode);

        $invoice                   = new stdClass;
        $invoice->ordercode        = $ordercode;
        $invoice->userid           = $userid;
        // Set explicitly via PHP rather than relying on the column's DEFAULT CURRENT_TIMESTAMP,
        // which uses the database server's own timezone and can end up hours off from the
        // site's configured timezone. Matches how every other date field in this component is
        // set (e.g. OrderController::buyticket()'s orderdate) - stored in PHP's own default
        // timezone (UTC in this Joomla environment) and converted to the site's configured
        // display offset via Date::_() wherever it's shown, never plain date()/strtotime().
        $invoice->invoicedate      = date('Y-m-d H:i:s', time());
        // All amounts include VAT: netto is what the tickets cost after the discount, the
        // service fee comes on top of it. The VAT is filled in below, from the invoice lines.
        $invoice->netto            = $amount->subtotal;
        $invoice->bruto            = $amount->tickets;
        $invoice->vat              = 0;
        $invoice->discount         = $amount->discount;
        $invoice->fees             = $amount->fees;
        $invoice->coupon_code      = $amount->coupon !== '' ? $amount->coupon : null;
        $invoice->payment_provider = ! empty($transaction->type) ? $transaction->type : null;

        if ( ! $db->insertObject('#__ticketstation_invoices', $invoice))
        {
            return false;
        }

        $invoiceid = $db->insertid();

        $query = $db->getQuery(true)
            ->select([
                'o.ticketid',
                'o.eventid',
                't.ticketname',
                'COUNT(o.orderid) AS quantity',
                'o.price AS ticketprice',
                'o.vat_percentage',
                'SUM(COALESCE(o.discount, 0)) AS discount',
                'SUM(o.price) AS netto_ticketprice',
                'o.coupon AS couponcode',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . (int) $ordercode)
            ->group('o.ticketid');

        $db->setQuery($query);
        $items = $db->loadObjectList();

        foreach ($items as $item)
        {
            $row                     = new stdClass;
            $row->invoiceid          = $invoiceid;
            $row->ordercode          = $ordercode;
            $row->ticketid           = $item->ticketid;
            $row->eventid            = $item->eventid;
            $row->ticketname         = $item->ticketname;
            $row->quantity           = $item->quantity;
            $row->ticketprice        = $item->ticketprice;
            $row->vat_percentage     = $item->vat_percentage;
            $row->discount           = $item->discount;
            $row->netto_ticketprice  = $item->netto_ticketprice;
            $row->couponcode         = $item->couponcode ?: null;

            $db->insertObject('#__ticketstation_invoice_items', $row);
        }

        // The service fee as its own line (ticketid 0) per VAT rate of the tickets, as it
        // carries the VAT of the tickets it belongs to (see OrderTotals::vatByRate()).
        foreach ($amount->vat_rates as $rate)
        {
            if ($rate->fees <= 0)
            {
                continue;
            }

            $row                    = new stdClass;
            $row->invoiceid         = $invoiceid;
            $row->ordercode         = $ordercode;
            $row->ticketid          = 0;
            $row->eventid           = 0;
            $row->ticketname        = '';
            $row->quantity          = 1;
            $row->ticketprice       = $rate->fees;
            $row->vat_percentage    = $rate->rate;
            $row->discount          = 0;
            $row->netto_ticketprice = $rate->fees;
            $row->couponcode        = null;

            $db->insertObject('#__ticketstation_invoice_items', $row);
        }

        $vat = 0.0;

        foreach ($this->getInvoiceItems($invoiceid) as $item)
        {
            $vat += $this->lineAmounts($item)->vat;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_invoices'))
            ->set($db->quoteName('vat') . ' = ' . round($vat, 2))
            ->where($db->quoteName('invoiceid') . ' = ' . (int) $invoiceid);

        $db->setQuery($query)->execute();

        $prefix = (new Config)->getPartialConfig(['invoice_prefix'])->invoice_prefix;
        History::log($ordercode, 'invoice_created', 'Invoice ' . $this->getInvoiceNumber($invoiceid, $prefix) . ' created');

        return $invoiceid;
    }

    /**
     * Draws the invoice PDF with FPDF and saves it to disk.
     *
     * @param   object  $invoice
     *
     * @return  bool
     *
     * @since 2.0.11
     */
    private function generatePdf($invoice)
    {
        $config = (new Config)->get();
        $client = $this->getClient($invoice->userid);

        if (empty($client))
        {
            return false;
        }

        $font_name = 'helvetica';
        $font_size = 9;

        // Every other consumer of the vendored setasign/fpdi classes in this component
        // require_once's the file directly (see SendTicketCopy.php, ticketcreator.php,
        // SendonPayment.php) rather than relying on Composer's autoloader alone - it isn't
        // reliably registered for setasign\Fpdi\* in every controller/task dispatch context.
        require_once __DIR__ . '/PDF/FPDF.php';

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont($font_name, '', $font_size);
        $pdf->SetTextColor(0, 0, 0);

        $salutation = $this->getSalutation($client->gender ?? null);

        // Configurable via "Company Logo" in Configuration (a native Joomla media picker,
        // storing a path relative to the site root). company_logo already existed as a column
        // in the schema - never wired up to anything - rather than adding a new one.
        // Falls back to the component's own bundled logo if left empty - not "logo.jpg" (what
        // the old, dead CreateInvoice.php assumed): that file has never actually existed here.
        $logoPath = $config->company_logo;

        if ( ! empty($logoPath))
        {
            // Joomla's media field stores "path#joomlaImage://adapter/path?width=..&height=.."
            // - everything from the "#" on is picker metadata (preview dimensions etc.), not
            // part of the actual file path.
            $logoPath = strtok($logoPath, '#');
        }

        $logo = ! empty($logoPath)
            ? JPATH_ROOT . '/' . ltrim($logoPath, '/')
            : JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/logo_ticketstation_for_joomla.png';

        if (file_exists($logo))
        {
            // Right-aligned against the same right margin the rest of the invoice uses (the
            // totals/lines end at x=200), fitted into a 70 x 25 mm box with the image's own
            // aspect ratio so it isn't stretched regardless of which logo is chosen: a tall
            // logo gets the full height, a wide one (like the default wordmark) the full width.
            $maxWidth     = 70;
            $maxHeight    = 25;
            $rightMargin  = 200;
            $imageSize    = @getimagesize($logo);
            $ratio        = ($imageSize && $imageSize[1] > 0) ? $imageSize[0] / $imageSize[1] : 1;
            $logoHeight   = min($maxHeight, $maxWidth / $ratio);
            $logoWidth    = $logoHeight * $ratio;

            $pdf->Image($logo, $rightMargin - $logoWidth, 10, $logoWidth, $logoHeight);
        }

        $pdf->SetXY(7, 14);
        $pdf->MultiCell(0, 5, PdfEncoding::toLatin1($this->buildCompanyAddress($config)), 0, 'L', 0);

        $pdf->SetXY(10, 65);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_INVOICE_ID')));
        $pdf->SetXY(10, 70);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_INVOICE_DATE')));
        $pdf->SetXY(10, 75);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_ORDER_ID')));

        $pdf->SetXY(34, 65);
        $pdf->Write(0, ': ' . $this->getInvoiceNumber($invoice->invoiceid, $config->invoice_prefix));
        $pdf->SetXY(34, 70);
        // Date::_() (not plain date()/strtotime()) - this component stores dates in UTC (PHP's
        // own default timezone in this Joomla environment) and relies on Date::_() to convert
        // to the site's configured display offset, exactly like every other date field
        // (Confirmation.php, ticketcreator.php, ...). Using strtotime() directly here skipped
        // that conversion and showed the raw UTC time instead of local time.
        $pdf->Write(0, ': ' . Date::_($invoice->invoicedate, $config->dateformat));
        $pdf->SetXY(34, 75);
        $pdf->Write(0, ': ' . $invoice->ordercode);

        $nameParts = array_filter([$salutation, $client->firstname, $client->name], function ($part) {
            return trim((string) $part) !== '';
        });

        $pdf->SetFont($font_name, 'B', 11);
        $pdf->SetXY(10, 100);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_INVOICE_FOR') . ' ' . implode(' ', $nameParts)));

        $pdf->SetFont($font_name, '', $font_size);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(135, 51);
        $pdf->MultiCell(0, 5, PdfEncoding::toLatin1($this->buildClientAddress($config->address_format_client, $client, $salutation)), 0, 'L', 0);

        $pdf->SetFont($font_name, '', $font_size);

        // Amounts are right-aligned in their column, ending at the right margin (x = 200).
        $amountCell = function ($x, $width, $y, $text) use ($pdf) {
            $pdf->SetXY($x, $y - 2.5);
            $pdf->Cell($width, 5, PdfEncoding::toLatin1($text), 0, 0, 'R');
        };
        $price = fn ($amount) => TicketstationFunctions::showprice($config->priceformat, $amount, $config->valuta);

        // Every line and the subtotal exclude VAT; the VAT follows as its own line(s), per
        // rate, and the total is what the customer paid, VAT included.
        $pdf->SetXY(10, 115);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_INVOICE_QUANTITY')));
        $pdf->SetXY(25, 115);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_DESCRIPTION')));
        $amountCell(110, 16, 115, Text::_('COM_TICKETSTATION_VAT_PERCENTAGE'));
        $amountCell(126, 26, 115, Text::_('COM_TICKETSTATION_INVOICE_PRICE_EXCL_VAT'));
        $amountCell(152, 22, 115, Text::_('COM_TICKETSTATION_DISCOUNT'));
        $amountCell(174, 26, 115, Text::_('COM_TICKETSTATION_INVOICE_TOTAL_EXCL_VAT'));

        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, 117, 200, 117);
        $pdf->Line(10, 117.5, 200, 117.5);

        $height   = 123;
        $subtotal = 0.0;
        $total    = 0.0;
        $vatRates = [];
        $lines    = $this->getInvoiceItems($invoice->invoiceid);

        // Invoices from before 2.5.2 have no line for the service fee, which then carried no VAT.
        $hasFeeLine = (bool) array_filter($lines, fn ($line) => (int) $line->ticketid === 0);

        if ( ! $hasFeeLine && $invoice->fees > 0)
        {
            $lines[] = (object) [
                'ticketid' => 0, 'quantity' => 1, 'ticketprice' => $invoice->fees, 'netto_ticketprice' => $invoice->fees,
                'discount' => 0, 'vat_percentage' => 0, 'couponcode' => null, 'eventname' => '', 'ticketname' => '',
            ];
        }

        foreach ($lines as $row)
        {
            $amounts = $this->lineAmounts($row);
            $rate    = (float) $row->vat_percentage;

            $subtotal += $amounts->excl;
            $total    += $amounts->incl;
            $vatRates[(string) $rate] = ($vatRates[(string) $rate] ?? 0) + $amounts->vat;

            if ((int) $row->ticketid === 0)
            {
                $description = Text::_('COM_TICKETSTATION_INVOICE_SERVICE_FEE');
            }
            else
            {
                $description = ! empty($row->eventname) ? $row->eventname . ' - ' . $row->ticketname : $row->ticketname;
            }

            // FPDF's Write() doesn't clip or wrap: shrink the description to fit before the
            // VAT column.
            $description = $this->truncateToWidth($pdf, $description, 85);

            $pdf->SetXY(10, $height);
            $pdf->Write(0, $row->quantity);
            $pdf->SetXY(25, $height);
            $pdf->Write(0, PdfEncoding::toLatin1($description));

            $amountCell(110, 16, $height, rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%');
            $amountCell(126, 26, $height, $price($this->excludingVat((float) $row->ticketprice, $rate)));
            $amountCell(152, 22, $height, (float) $row->discount > 0 ? $price($this->excludingVat((float) $row->discount, $rate)) : '');
            $amountCell(174, 26, $height, $price($amounts->excl));

            if ( ! empty($row->couponcode))
            {
                $pdf->SetXY(25, $height + 5);
                $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_COUPON_CODE') . ': ' . $row->couponcode));
            }

            $pdf->Line(10, $height + 10, 200, $height + 10);

            $height += 15;
        }

        $pdf->SetTextColor(0, 0, 0);

        $height += 10;

        $pdf->SetXY(120, $height);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_INVOICE_SUBTOTAL_EXCL_VAT')));
        $amountCell(174, 26, $height, $price($subtotal));

        ksort($vatRates, SORT_NUMERIC);

        foreach ($vatRates as $rate => $vat)
        {
            // A rate without VAT (0%) needs no line of its own.
            if ((float) $rate <= 0)
            {
                continue;
            }

            $height += 5;
            $pdf->SetXY(120, $height);
            $pdf->Write(0, PdfEncoding::toLatin1(Text::sprintf('COM_TICKETSTATION_INVOICE_VAT_RATE', rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.'))));
            $amountCell(174, 26, $height, $price($vat));
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(120, $height + 4, 200, $height + 4);
        $pdf->Line(120, $height + 4.5, 200, $height + 4.5);

        $height += 7;
        $pdf->SetFont($font_name, 'B', $font_size);
        $pdf->SetXY(120, $height);
        $pdf->Write(0, PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_GRAND_TOTAL')));
        $amountCell(174, 26, $height, $price($total));

        $dir = JPATH_ADMINISTRATOR . '/components/com_ticketstation/invoices';

        if ( ! is_dir($dir))
        {
            Folder::create($dir);
        }

        $pdf->Output($this->getPdfPath($invoice->invoiceid), 'F');

        return true;
    }

    /**
     * Sends the invoice PDF to the client and marks it as sent.
     *
     * @param   object  $invoice
     *
     * @return  bool
     *
     * @since 2.0.11
     */
    private function send($invoice)
    {
        $config = (new Config)->getPartialConfig(['priceformat', 'valuta', 'invoice_prefix']);

        $message = new eTicketsMessage;

        // The order placeholders (see eTicketsMessage::TEMPLATE_FIELDS), with {price} as the
        // invoice total.
        $variables = array_merge(eTicketsMessage::orderVariables((int) $invoice->ordercode), [
            'invoice_id' => $this->getInvoiceNumber($invoice->invoiceid, $config->invoice_prefix),
            'price'      => TicketstationFunctions::showprice($config->priceformat, $invoice->netto + $invoice->fees, $config->valuta),
        ]);

        $sent = $message->id(5)
            ->user($invoice->userid)
            ->attachment($this->getPdfPath($invoice->invoiceid))
            ->variables($variables)
            ->send();

        // Matches how PaymentAPI::sendTickets() logs "Tickets sent to <email>".
        $client = $this->getClient($invoice->userid);
        $email  = is_object($client) ? $client->emailaddress : null;
        $number = $this->getInvoiceNumber($invoice->invoiceid, $config->invoice_prefix);

        // A mail that didn't go out leaves the invoice "Not Sent", so it can be sent again.
        if ( ! $sent)
        {
            History::log($invoice->ordercode, 'invoice_send_failed', 'Sending invoice ' . $number . ' to ' . ($email ?: 'customer') . ' failed', ['email' => $email]);

            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $fields = [
            $db->quoteName('sent') . ' = 1',
            $db->quoteName('date_sent') . ' = ' . $db->quote(date('Y-m-d H:i:s')),
        ];
        $conditions = [$db->quoteName('invoiceid') . ' = ' . (int) $invoice->invoiceid];

        $query = $db->getQuery(true)->update($db->quoteName('#__ticketstation_invoices'))->set($fields)->where($conditions);
        $db->setQuery($query);
        $db->execute();

        History::log($invoice->ordercode, 'invoice_sent', 'Invoice ' . $number . ' sent to ' . ($email ?: 'customer'), ['email' => $email]);

        return true;
    }

    /**
     * @return string
     *
     * @since 2.0.11
     */
    public function getPdfPath($invoiceid)
    {
        return JPATH_ADMINISTRATOR . '/components/com_ticketstation/invoices/' . $this->getPdfFilename($invoiceid);
    }

    /**
     * The filename (not full path) an invoice PDF is stored/downloaded under, including the
     * configured invoice prefix so it matches the number printed on the invoice itself.
     *
     * @since 2.0.11
     */
    public function getPdfFilename($invoiceid)
    {
        $config = (new Config)->getPartialConfig(['invoice_prefix']);

        return $this->getInvoiceNumber($invoiceid, $config->invoice_prefix) . '.pdf';
    }

    /**
     * The invoice number as printed on the invoice/used in the filename: the configured prefix
     * followed by the raw invoiceid zero-padded to 5 digits, so the very first invoice reads
     * "00001" rather than "1". The underlying invoiceid (the real auto-increment primary key)
     * is never changed - this is purely a display/filename format.
     *
     * @since 2.0.12
     */
    public function getInvoiceNumber($invoiceid, $prefix = '')
    {
        return $prefix . str_pad((string) $invoiceid, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Shrinks $text (appending "...") until it fits within $maxWidth mm in the PDF's current
     * font/size, using FPDF's own GetStringWidth() so it stays accurate regardless of font.
     *
     * @since 2.0.12
     */
    private function truncateToWidth($pdf, $text, $maxWidth)
    {
        if ($pdf->GetStringWidth($text) <= $maxWidth)
        {
            return $text;
        }

        while ($text !== '' && $pdf->GetStringWidth($text . '...') > $maxWidth)
        {
            $text = mb_substr($text, 0, -1);
        }

        return $text . '...';
    }

    /**
     * An invoice line's amount after its discount, including VAT, excluding VAT and the VAT
     * itself. The VAT is the difference of the two, so the lines always add up to the total.
     */
    private function lineAmounts($line): object
    {
        $incl = round((float) $line->netto_ticketprice - (float) $line->discount, 2);
        $excl = $this->excludingVat($incl, (float) $line->vat_percentage);

        return (object) ['incl' => $incl, 'excl' => $excl, 'vat' => round($incl - $excl, 2)];
    }

    private function excludingVat(float $amount, float $rate): float
    {
        return round($amount / (100 + $rate) * 100, 2);
    }

    private function getInvoiceItems($invoiceid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['i.*', 'e.eventname'])
            ->from($db->quoteName('#__ticketstation_invoice_items', 'i'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('i.eventid'))
            ->where($db->quoteName('i.invoiceid') . ' = ' . (int) $invoiceid);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    private function getClient($userid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_clients', 'c'))
            ->join('LEFT OUTER', $db->quoteName('#__ticketstation_country', 'b') . ' ON ' . $db->quoteName('c.country_id') . ' = ' . $db->quoteName('b.country_id'))
            ->where($db->quoteName('c.clientid') . ' = ' . (int) $userid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    private function getSalutation($gender)
    {
        if ($gender == 1)
        {
            return PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_MR'));
        }

        if ($gender == 2)
        {
            return PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_MRS'));
        }

        if ($gender == 3)
        {
            return PdfEncoding::toLatin1(Text::_('COM_TICKETSTATION_MISS'));
        }

        // Unknown/unset gender: say nothing rather than guessing with a "Family" salutation.
        return '';
    }

    /**
     * The venue's own return-address block, from the token-based "Company Address Format"
     * config field - same mechanism as the client address below, just with company tokens
     * sourced from the existing company fields (companyname/address1/.../phone/email/website).
     *
     * @since 2.0.12
     */
    private function buildCompanyAddress($config)
    {
        $template = trim((string) $config->address_format_company);

        if ($template === '')
        {
            $template = "%%COMPANY_NAME%%\n%%ADDRESS1%%\n%%ADDRESS2%%\n%%ZIPCODE%% %%CITY%%";
        }

        return $this->renderAddressTemplate($template, [
            '%%COMPANY_NAME%%' => $config->companyname,
            '%%ADDRESS1%%'     => $config->address1,
            '%%ADDRESS2%%'     => $config->address2,
            '%%ZIPCODE%%'      => $config->zipcode,
            '%%CITY%%'         => $config->city,
            '%%PHONE%%'        => $config->phone,
            '%%EMAIL%%'        => $config->email,
            '%%WEBSITE%%'      => $config->website,
        ]);
    }

    private function buildClientAddress($template, $client, $salutation)
    {
        // address_format_client has no seeded default and, until now, no admin UI to set one
        // either - fall back to a sensible layout rather than printing an empty address block.
        if (trim((string) $template) === '')
        {
            $template = "%%SALUTATION%% %%FIRSTNAME%% %%LASTNAME%%\n%%ADDRESS1%%\n%%ADDRESS2%%\n%%ZIPCODE%% %%CITY%%\n%%COUNTRY_FULL%%";
        }

        // country_id 1 is the seeded "Unknown" placeholder row (code "UN") every client
        // defaults to until they actually pick a country - treat it the same as no country
        // set at all, rather than printing "Unknown (UN)" on the invoice.
        $hasRealCountry = ! empty($client->country_id) && (int) $client->country_id !== 1 && $client->country != '';

        return $this->renderAddressTemplate($template, [
            '%%SALUTATION%%'   => $salutation,
            '%%FIRSTNAME%%'    => $client->firstname,
            '%%LASTNAME%%'     => $client->name,
            '%%ADDRESS1%%'     => $client->address,
            '%%ADDRESS2%%'     => $client->address2,
            '%%ZIPCODE%%'      => $client->zipcode,
            '%%CITY%%'         => $client->city,
            '%%EMAIL%%'        => $client->emailaddress,
            '%%PHONENUMBER%%'  => $client->phonenumber,
            '%%COUNTRY_FULL%%' => $hasRealCountry ? $client->country : '',
            '%%COUNTRY_2D%%'   => $hasRealCountry ? $client->country_2_code : '',
            '%%COUNTRY_3D%%'   => $hasRealCountry ? $client->country_3_code : '',
        ]);
    }

    /**
     * Replaces %%TOKEN%% placeholders in an address template and drops any resulting line that
     * ends up empty (e.g. a %%SALUTATION%% or %%ADDRESS2%% token with no value), instead of
     * printing blank lines/gaps. Shared by the company and client address builders.
     *
     * @since 2.0.12
     */
    private function renderAddressTemplate($template, array $tokens)
    {
        $address = str_replace(array_keys($tokens), array_values($tokens), $template);

        // A template like "%%COUNTRY_FULL%% (%%COUNTRY_2D%%)" leaves a bare "()" once both
        // tokens are empty - clean that up before the emptiness check below, so it doesn't
        // survive as a stray line/fragment.
        $address = str_replace('()', '', $address);

        $lines = array_filter(array_map('trim', explode("\n", $address)), function ($line) {
            return $line !== '';
        });

        return implode("\n", $lines);
    }
}
