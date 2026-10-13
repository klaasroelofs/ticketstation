<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * The built-in ticket layout, used for every ticket that has no uploaded design
 * (eTicket-<ticketid>.jpg/.pdf) of its own.
 *
 * The background is drawn with FPDF primitives rather than imported from a fixed PDF, so it
 * fits every ticket size and orientation (A4/A5, portrait/landscape, override_ticketsize)
 * instead of always being placed at 210 mm wide. If the ticket also has no field positions
 * filled in under "Ticket Layout", defaultFields() supplies a matching set, so such a ticket
 * still carries the event, date, customer, price and QR code instead of printing blank.
 *
 * Shared by ticketcreator::doPDF() (the real tickets) and TicketPreviewCreator (the preview in
 * the ticket form), so both always render the same layout.
 */
class DefaultTicketLayout
{
    const BLUE  = '1350DB';
    const NAVY  = '0E1F3D';
    const GREY  = '5B6478';
    const LIGHT = '8A93A6';

    /**
     * Every "Ticket Layout" field that is printed at a position of its own. A ticket with none
     * of these filled in gets the default set from defaultFields().
     */
    const POSITION_FIELDS = [
        'eventname', 'ticketname', 'freetext_1', 'ticketdate', 'doors_open', 'venue', 'ticketprice', 'orderdate',
        'client', 'orderticketindex', 'ordernumber', 'seatnumber', 'orderreference', 'qrcode',
    ];

    /**
     * A colour from the Configuration as #rrggbb (lower case), or the default blue when it isn't one.
     *
     * @param   mixed  $value
     */
    public static function color($value): string
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : '#' . strtolower(self::BLUE);
    }

    /**
     * The accent colour of the layout as RRGGBB: the company colour (Configuration > Company), else
     * the Ticketstation blue. Read through the full row, so a site whose database isn't updated yet
     * still makes its tickets.
     */
    public static function accent(): string
    {
        return ltrim(self::color((new Config)->get()->company_color ?? ''), '#');
    }

    /**
     * The accent as the colour of text on the white ticket: the accent itself, unless it is so light
     * that it can't be read, then NAVY.
     */
    private static function accentText(): string
    {
        $hex = self::accent();

        $brightness = (0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2))) / 255;

        return $brightness > 0.6 ? self::NAVY : $hex;
    }

    /**
     * Draws the logo from Configuration > Company at the top left, fitted into a box with its own
     * aspect ratio. Returns false when there is no usable logo, so the caller draws its own.
     */
    private static function drawCompanyLogo($pdf, $s): bool
    {
        $file = Config::companyLogoFile();
        $size = $file !== '' ? @getimagesize($file) : false;

        ## FPDF reads JPEG, PNG and GIF only.
        if (!$size || $size[1] < 1 || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            return false;
        }

        $maxHeight = 12 * $s;
        $maxWidth  = min(60 * $s, $pdf->GetPageWidth() - (self::isLandscape($pdf) ? 88 : 20) * $s);
        $ratio     = $size[0] / $size[1];
        $height    = min($maxHeight, $maxWidth / $ratio);
        $width     = $height * $ratio;

        try {
            ## Centred in the free band between the top bar and the rule at 23 mm.
            $pdf->Image($file, 10 * $s, 7 * $s + ($maxHeight - $height) / 2, $width, $height);
        } catch (\Throwable $e) {
            ## A file FPDF can't read (an interlaced PNG, say) must not stop the ticket.
            return false;
        }

        return true;
    }

    /**
     * Whether the ticket has no uploaded design of its own, so the default layout applies.
     */
    public static function applies($ticketid)
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . (int) $ticketid;

        return !file_exists($path . '.jpg') && !file_exists($path . '.pdf');
    }

    /**
     * Whether at least one field has a position ("X-Y") filled in.
     *
     * @param   array|object  $ticket  A ticket row, or the posted jform values.
     */
    public static function hasFields($ticket)
    {
        $ticket = (array) $ticket;

        foreach (self::POSITION_FIELDS as $field) {
            if (strpos((string) ($ticket[$field . '_position'] ?? ''), '-') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Draws the default background on the current page.
     */
    public static function drawBackground($pdf)
    {
        $w = $pdf->GetPageWidth();
        $h = $pdf->GetPageHeight();
        $s = self::scale($pdf);

        ## Fields near the bottom of the page must not push FPDF onto a second page.
        $pdf->SetAutoPageBreak(false);

        ## Accent bars along the top and bottom edge, in the company colour.
        self::fill($pdf, self::accent());
        $pdf->Rect(0, 0, $w, 3 * $s, 'F');
        $pdf->Rect(0, $h - 3 * $s, $w, 3 * $s, 'F');

        ## The company logo, or Ticketstation's own when there is none (or FPDF can't use the file).
        if (!self::drawCompanyLogo($pdf, $s)) {
            $logo = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/logo_ticketstation_for_joomla.png';

            if (file_exists($logo)) {
                $pdf->Image($logo, 10 * $s, 9 * $s, 0, 9 * $s);
            }
        }

        $pdf->SetLineWidth(0.3);
        self::draw($pdf, 'E1E6EF');

        if (self::isLandscape($pdf)) {
            ## Info on the left, a tear-off stub with the QR code on the right.
            $stub = $w - 68 * $s;
            $pdf->Line(10 * $s, 23 * $s, $stub - 10 * $s, 23 * $s);

            self::draw($pdf, 'C5CDDB');
            self::dashedLine($pdf, $stub, 3 * $s, $stub, $h - 3 * $s, 2 * $s);
        } else {
            ## Info at the top, a tear-off stub with the QR code at the bottom.
            $pdf->Line(10 * $s, 23 * $s, $w - 10 * $s, 23 * $s);

            self::draw($pdf, 'C5CDDB');
            self::dashedLine($pdf, 0, 104 * $s, $w, 104 * $s, 2 * $s);
        }
    }

    /**
     * The default field set matching drawBackground(), as the same column => value pairs a
     * ticket row holds, so both creators can simply merge it over the ticket's own values.
     */
    public static function defaultFields($pdf)
    {
        $w = $pdf->GetPageWidth();
        $h = $pdf->GetPageHeight();
        $s = self::scale($pdf);
        $x = 10 * $s;

        if (self::isLandscape($pdf)) {
            $stub   = $w - 68 * $s;
            $qrSize = 50 * $s;
            $qrX    = $stub + 9 * $s;
            $qrY    = 20 * $s;
            $indexY = $qrY + $qrSize + 10 * $s;
            $y      = ['eventname' => 35, 'ticketname' => 46, 'ticketdate' => 56, 'venue' => 64, 'seatnumber' => 72, 'client' => 84, 'ticketprice' => 98, 'orderreference' => 107];
        } else {
            $qrSize = 62 * $s;
            $qrX    = ($w - $qrSize) / 2;
            $qrY    = 114 * $s;
            $indexY = $qrY + $qrSize + 8 * $s;
            $y      = ['eventname' => 33, 'ticketname' => 42, 'ticketdate' => 50, 'venue' => 57, 'seatnumber' => 64, 'client' => 75, 'ticketprice' => 88, 'orderreference' => 96];
        }

        $fields = [
            'eventname'      => [16, self::NAVY],
            'ticketname'     => [12, self::accentText()],
            'ticketdate'     => [10, self::NAVY],
            'venue'          => [10, self::NAVY],
            'seatnumber'     => [10, self::NAVY],
            'client'         => [10, self::GREY],
            'ticketprice'    => [10, self::NAVY],
            'orderreference' => [10, self::GREY],
        ];

        $values = [];

        foreach ($fields as $field => [$size, $color]) {
            $values[$field . '_position']  = self::position($x, $y[$field] * $s);
            $values[$field . '_fontsize']  = (string) round($size * $s);
            $values[$field . '_fontcolor'] = $color;
        }

        $values['orderreference_centered'] = 0;

        $values['qrcode_position'] = self::position($qrX, $qrY);
        ## qrcode_width is in pixels; the QR image is placed at 96 dpi (0.2646 mm per pixel).
        $values['qrcode_width']    = (string) round($qrSize / 0.2646);

        $values['orderticketindex_position']          = self::position($qrX, $indexY);
        $values['orderticketindex_fontsize']          = (string) round(11 * $s);
        $values['orderticketindex_fontcolor']         = self::NAVY;
        $values['orderticketindex_prependtext_print'] = '1';
        $values['orderticketindex_prependtext']       = 'Ticket';

        ## Printed upwards along the right edge.
        $values['ordernumber_position']  = self::position($w - 4 * $s, $h - 8 * $s);
        $values['ordernumber_fontsize']  = (string) round(7 * $s);
        $values['ordernumber_fontcolor'] = self::LIGHT;

        return $values;
    }

    /**
     * Scale factor relative to A5 (148 mm on the short side), so the layout grows with A4.
     */
    private static function scale($pdf)
    {
        return min($pdf->GetPageWidth(), $pdf->GetPageHeight()) / 148;
    }

    private static function isLandscape($pdf)
    {
        return $pdf->GetPageWidth() > $pdf->GetPageHeight();
    }

    private static function position($x, $y)
    {
        return round($x, 1) . '-' . round($y, 1);
    }

    private static function fill($pdf, $hex)
    {
        $pdf->SetFillColor(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private static function draw($pdf, $hex)
    {
        $pdf->SetDrawColor(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    /**
     * FPDF has no dash pattern, so the perforation line is drawn as separate segments.
     */
    private static function dashedLine($pdf, $x1, $y1, $x2, $y2, $dash)
    {
        $length = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);

        if ($length <= 0) {
            return;
        }

        $dx = ($x2 - $x1) / $length;
        $dy = ($y2 - $y1) / $length;

        for ($d = 0; $d < $length; $d += 2 * $dash) {
            $end = min($d + $dash, $length);
            $pdf->Line($x1 + $dx * $d, $y1 + $dy * $d, $x1 + $dx * $end, $y1 + $dy * $end);
        }
    }
}
