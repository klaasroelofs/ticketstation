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
     * Converts a UTF-8 string to the encoding FPDF's core fonts actually use, for the legacy
     * FPDF/FPDI PDF output. Despite the name, FPDF's built-in fonts (helvetica, etc.) are
     * Windows-1252, not true ISO-8859-1 - the practical difference is the 0x80-0x9F range,
     * where cp1252 defines real characters (e.g. 0x80 = "€") that ISO-8859-1 leaves undefined.
     * Converting to true ISO-8859-1 silently turns "€" into "?" since it has no representation
     * there at all. Replacement for the PHP 8.2-deprecated utf8_decode().
     *
     * @param   string|null  $string  The UTF-8 encoded string.
     *
     * @return  string
     *
     * @since   1.7.0
     */
    public static function toLatin1(?string $string): string
    {
        return mb_convert_encoding((string) $string, 'Windows-1252', 'UTF-8');
    }
}
