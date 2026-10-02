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
 * An uploaded ticket design (eTicket-<ticketid>.jpg or .pdf) as the background of a ticket page.
 *
 * The design is stretched over the whole page, so it follows the ticket's size and orientation
 * (A5/A4, portrait/landscape, override_ticketsize) instead of being placed at fixed A4/A5
 * dimensions. Shared by ticketcreator::doPDF() (the real tickets) and TicketPreviewCreator (the
 * preview in the ticket form), so both place a design the same way. Tickets without an uploaded
 * design use DefaultTicketLayout instead.
 */
class TicketDesign
{
    /**
     * Resolution a design gets reduced to, in dots per inch. The fields are drawn as text on top
     * of the design, so only the background itself is a bitmap; 200 dpi is sharp on paper and
     * on a phone screen, and a larger picture only adds weight to every ticket PDF.
     */
    public const MAX_DPI = 200;

    /** JPEG quality of a reduced design. */
    public const QUALITY = 85;

    /** A design that is smaller than this (bytes) and has a fitting resolution is left alone. */
    public const LEAVE_BELOW = 150000;

    /**
     * The page size in mm of a ticket, from its settings (ticket_size, ticket_orientation and
     * override_ticketsize), by the rules of ticketcreator::doPDF().
     *
     * @return  float[]  [width, height]
     */
    public static function pageSize($ticket): array
    {
        $ticket = (array) $ticket;
        $size   = ($ticket['ticket_size'] ?? '') === 'A4' ? [210.0, 297.0] : [148.0, 210.0];
        $parts  = array_map('trim', explode(',', (string) ($ticket['override_ticketsize'] ?? '')));

        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[0] > 0 && $parts[1] > 0)
        {
            $size = [(float) $parts[0], (float) $parts[1]];
        }

        return ($ticket['ticket_orientation'] ?? '') === 'L' ? [max($size), min($size)] : [min($size), max($size)];
    }

    /**
     * Makes an uploaded JPG design lighter: scaled down to MAX_DPI at the size of the ticket and
     * saved again with a moderate quality. A design from a camera or a design program is often
     * several MB, and every ticket in an order would carry it.
     *
     * The file is only replaced when the result is smaller, and left alone when it is small
     * already. A picture that can't be handled safely (CMYK, no GD) stays as it is.
     *
     * @param   string   $file    path of the JPG, replaced in place
     * @param   float[]  $size    the ticket's page size in mm, see pageSize()
     *
     * @return  array|null  ['from' => bytes, 'to' => bytes, 'width' => px, 'height' => px] when
     *                      the file was replaced, null when it was left as it was
     */
    public static function shrinkJpg(string $file, array $size): ?array
    {
        if (!function_exists('imagecreatefromjpeg') || !is_file($file))
        {
            return null;
        }

        $info = @getimagesize($file);

        if (!$info || $info[2] !== IMAGETYPE_JPEG || (int) ($info['channels'] ?? 3) === 4)
        {
            return null;
        }

        [$width, $height] = $info;

        ## The longest side the ticket needs; the design is stretched over the whole page
        $needed = (int) ceil(max($size) / 25.4 * self::MAX_DPI);
        $scale  = min(1, $needed / max($width, $height));
        $bytes  = filesize($file);

        if ($scale >= 1 && $bytes < self::LEAVE_BELOW)
        {
            return null;
        }

        $source = @imagecreatefromjpeg($file);

        if (!$source)
        {
            return null;
        }

        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $target    = $source;

        if ($scale < 1)
        {
            $target = imagescale($source, $newWidth, $newHeight, IMG_BICUBIC);
            imagedestroy($source);
        }

        if (!$target)
        {
            return null;
        }

        $temp = $file . '.tmp';
        $ok   = imagejpeg($target, $temp, self::QUALITY);
        imagedestroy($target);

        ## Saving the same pixels again only pays when it gains something clear; each pass costs quality
        $limit = $scale < 1 ? $bytes : $bytes * 0.85;

        if (!$ok || !is_file($temp) || filesize($temp) >= $limit)
        {
            if (is_file($temp))
            {
                unlink($temp);
            }

            return null;
        }

        $newBytes = filesize($temp);

        if (!rename($temp, $file))
        {
            unlink($temp);

            return null;
        }

        return ['from' => $bytes, 'to' => $newBytes, 'width' => $newWidth, 'height' => $newHeight];
    }

    /**
     * Draws the ticket's uploaded design on the current page. A JPG takes precedence over a PDF;
     * of a PDF only the first page is used.
     */
    public static function draw($pdf, $ticketid)
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . (int) $ticketid;
        $w    = $pdf->GetPageWidth();
        $h    = $pdf->GetPageHeight();

        if (file_exists($path . '.jpg')) {
            $pdf->Image($path . '.jpg', 0, 0, $w, $h);
        } elseif (file_exists($path . '.pdf')) {
            $pdf->setSourceFile($path . '.pdf');
            $pdf->useTemplate($pdf->importPage(1), 0, 0, $w, $h);
        }
    }
}
