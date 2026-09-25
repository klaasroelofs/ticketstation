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
