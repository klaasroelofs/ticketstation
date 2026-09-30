<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use setasign\Fpdi\Fpdi;

defined('_JEXEC') or die;

// FPDF, FPDI and the QR code library come with the component through Composer, in the site part
// (see site/composer.json). Loaded here, before the class below needs Fpdi.
require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

/**
 * The PDF of tickets and invoices: FPDF with FPDI's import of pages from existing PDFs (a ticket
 * design, or the separate tickets combined into one file), plus:
 *
 * - the fonts of TicketFont that FPDF doesn't have itself (Raleway and Open Sans, in
 *   admin/assets/fonts/pdf), loaded as soon as SetFont() asks for them;
 * - text that runs in another direction or at an angle (TextWithDirection(),
 *   TextWithRotation()).
 */
class Pdf extends Fpdi
{
    /**
     * The font families in admin/assets/fonts/pdf, each with a regular and a bold definition file.
     */
    public const OWN_FONTS = ['raleway', 'opensans'];

    /**
     * The definition file (JSON) of an FPDF font file name such as "ralewayb" or "helvetica":
     * ours in admin/assets/fonts/pdf, the standard PDF fonts from the FPDF library.
     */
    public static function fontFile(string $file): string
    {
        $family = preg_replace('/(bi|b|i)$/', '', $file);

        if (in_array($family, self::OWN_FONTS, true))
        {
            return self::fontDir() . $file . '.json';
        }

        return JPATH_SITE . '/components/com_ticketstation/vendor/setasign/fpdf/font/' . $file . '.json';
    }

    private static function fontDir(): string
    {
        return JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/fonts/pdf/';
    }

    /**
     * FPDF's SetFont(), which also knows our own fonts. The size may come in as a string from
     * the ticket settings; an empty one keeps the current size.
     */
    public function SetFont($family, $style = '', $size = 0)
    {
        $name = strtolower((string) $family);

        if (in_array($name, self::OWN_FONTS, true))
        {
            $this->AddFont($name, str_replace('U', '', strtoupper((string) $style)), '', self::fontDir());
        }

        parent::SetFont($family, $style, (float) $size);
    }

    /**
     * Text at X/Y in a direction: R (right, as normal), L (left, upside down), U (up) or D
     * (down).
     */
    public function TextWithDirection($x, $y, $txt, $direction = 'R')
    {
        $matrix = [
            'R' => [1, 0, 0, 1],
            'L' => [-1, 0, 0, -1],
            'U' => [0, 1, -1, 0],
            'D' => [0, -1, 1, 0],
        ];

        $position = sprintf('%.2F %.2F', $x * $this->k, ($this->h - $y) * $this->k);

        if (isset($matrix[$direction]))
        {
            $s = sprintf('BT %.2F %.2F %.2F %.2F %s Tm (%s) Tj ET', ...[...$matrix[$direction], $position, $this->_escape($txt)]);
        }
        else
        {
            $s = sprintf('BT %s Td (%s) Tj ET', $position, $this->_escape($txt));
        }

        if ($this->ColorFlag)
        {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }

        $this->_out($s);
    }

    /**
     * Text at X/Y at an angle (degrees, counterclockwise), with the letters at $font_angle to
     * the baseline (0 = upright).
     */
    public function TextWithRotation($x, $y, $txt, $txt_angle, $font_angle = 0)
    {
        $font_angle = ($font_angle + 90 + $txt_angle) * M_PI / 180;
        $txt_angle  = $txt_angle * M_PI / 180;

        $s = sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',
            cos($txt_angle), sin($txt_angle), cos($font_angle), sin($font_angle),
            $x * $this->k, ($this->h - $y) * $this->k, $this->_escape($txt));

        if ($this->ColorFlag)
        {
            $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        }

        $this->_out($s);
    }
}
