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

class PdfEncoding
{
    /**
     * A text for the PDF. Pdf prints UTF-8, so nothing is converted; what can't be UTF-8 (a
     * stray byte in a name that came in from somewhere) is replaced, so it can't stop a ticket.
     *
     * Before 2.34.0 the PDF was made with FPDF, which only knows Windows-1252: this was
     * toLatin1(), and every character outside that turned into a question mark.
     *
     * @param   string|null  $string  The UTF-8 encoded string.
     *
     * @return  string
     *
     * @since   2.34.0
     */
    public static function text(?string $string): string
    {
        return mb_convert_encoding((string) $string, 'UTF-8', 'UTF-8');
    }
}
