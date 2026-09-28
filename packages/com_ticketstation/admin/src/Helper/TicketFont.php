<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

/**
 * The fonts a ticket's text can be printed in (the "Font" setting on the Ticket Layout tab).
 *
 * Each key is also the FPDF family name, with its definition in PDF/font/ (<key>.php and
 * <key>b.php for bold). Helvetica, Times and Courier are the PDF standard fonts that every
 * PDF reader has built in, so nothing is embedded; Raleway and Open Sans are embedded.
 *
 * @since  2.6.6
 */
class TicketFont
{
    public const DEFAULT = 'raleway';

    public const FONTS = ['raleway', 'opensans', 'helvetica', 'times', 'courier'];

    /**
     * The FPDF family for a ticket's stored font setting, Raleway when empty or unknown.
     *
     * @param   string|null  $font  The value of the ticket's ticket_font column.
     *
     * @return  string
     */
    public static function family(?string $font): string
    {
        $font = strtolower(trim((string) $font));

        return in_array($font, self::FONTS, true) ? $font : self::DEFAULT;
    }
}
