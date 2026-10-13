<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use setasign\Fpdi\TcpdfFpdi;

defined('_JEXEC') or die;

// TCPDF reads its settings from constants, so these come before the library is loaded: no search
// for a configuration file in /etc, an exception instead of die() on an error, Joomla's own tmp
// folder for its temporary files, and no deprecation notice on every ticket (see below).
foreach ([
    'K_TCPDF_EXTERNAL_CONFIG'       => true,
    'K_TCPDF_THROW_EXCEPTION_ERROR' => true,
    'K_PATH_CACHE'                  => JPATH_SITE . '/tmp/',
    'TCPDF_SILENCE_DEPRECATION'     => true,
] as $constant => $value)
{
    if (!defined($constant))
    {
        define($constant, $value);
    }
}

// TCPDF, FPDI and the QR code library come with the component through Composer, in the site part
// (see site/composer.json). Loaded here, before the class below needs TcpdfFpdi.
require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

/**
 * The PDF of tickets and invoices: TCPDF with FPDI's import of pages from existing PDFs (a ticket
 * design, or the separate tickets combined into one file).
 *
 * TCPDF is what makes Arabic and Persian possible: it shapes the letters and sets right-to-left text
 * the right way round, and works with UTF-8 text and TrueType fonts (the tickets used FPDF, which
 * knows neither). This class keeps the FPDF way of placing text that the ticket layout editor
 * and every ticket layout in use were made for, so no ticket moves:
 *
 * - Write() and Cell() start the text 1 mm right of X, with the baseline at Y plus 0.3 x the font
 *   size (plus half the cell height for Cell()). MultiCell() keeps its FPDF meaning: the height is
 *   the height of a line.
 * - Output() takes the FPDF order of its arguments: destination, then name.
 * - An image without size is placed at 96 dpi.
 *
 * Added to that:
 *
 * - Raleway and Open Sans (TicketFont), in admin/assets/fonts/pdf, loaded as soon as SetFont()
 *   asks for them. They hold the Windows-1252 characters. Text with any other character (Arabic,
 *   Cyrillic, Greek, a Polish or Turkish letter) is printed in DejaVu Sans, which TCPDF brings along:
 *   the whole text, not only the letter, so a line does not change font halfway.
 * - Text that runs in another direction or at an angle (TextWithDirection(), TextWithRotation()).
 *
 * TCPDF is in maintenance mode: its author moved on to tc-lib-pdf, and a new minor version of
 * TCPDF only brings fixes. That is why site/composer.json keeps it at ^6.11. docs/tech-debt.md
 * has what a move to tc-lib-pdf would take.
 */
class Pdf extends TcpdfFpdi
{
    /**
     * The font families in admin/assets/fonts/pdf, each with a regular and a bold definition file.
     */
    public const OWN_FONTS = ['raleway', 'opensans'];

    /**
     * The font for text the chosen font has no glyphs for. Part of TCPDF's own fonts.
     */
    public const FALLBACK_FONT = 'dejavusans';

    /**
     * Characters that print nothing: the zero-width and direction marks. Persian text has them
     * between words (the half space), and they must not decide on the font.
     */
    private const INVISIBLE = [0x200B, 0x200C, 0x200D, 0x200E, 0x200F, 0x202A, 0x202B, 0x202C, 0x202D, 0x202E, 0x2066, 0x2067, 0x2068, 0x2069, 0xFEFF];

    /**
     * The own fonts that AddFont() has been called for, as "raleway" or "ralewayb".
     *
     * @var bool[]
     */
    private array $loadedFonts = [];

    /**
     * The definition file (PHP) of a font file name such as "ralewayb" or "helvetica": ours in
     * admin/assets/fonts/pdf, the standard PDF fonts from TCPDF.
     */
    public static function fontFile(string $file): string
    {
        $family = preg_replace('/(bi|b|i)$/', '', $file);

        if (in_array($family, self::OWN_FONTS, true))
        {
            return self::fontDir() . $file . '.php';
        }

        return JPATH_SITE . '/components/com_ticketstation/vendor/tecnickcom/tcpdf/fonts/' . $file . '.php';
    }

    private static function fontDir(): string
    {
        return JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/fonts/pdf/';
    }

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size, true, 'UTF-8', false);

        $this->SetCreator('Ticketstation');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        // What FPDF starts with: a margin of 1 cm, a page break 2 cm from the bottom, and a
        // cell margin of 1 mm left and right of the text.
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 20);
        $this->setCellPaddings(1, 0, 1, 0);
        $this->setCellMargins(0, 0, 0, 0);
        $this->setImageScale(96 / 72);

        $this->SetFont('helvetica', '', 12);
    }

    /**
     * AddPage() with FPDF's meaning of a size in an array: the size of a portrait page, which
     * orientation L turns round. (TCPDF decides on the orientation from the numbers.)
     */
    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false)
    {
        if (is_array($format) && count($format) === 2)
        {
            $width  = (float) $format[0];
            $height = (float) $format[1];

            if (strtoupper(substr((string) $orientation, 0, 1)) === 'L')
            {
                [$width, $height] = [$height, $width];
            }

            $orientation = $width > $height ? 'L' : 'P';
            $format      = [$width, $height];
        }

        parent::AddPage($orientation, $format, $keepmargins, $tocpage);
    }

    /**
     * SetFont() that also knows our own fonts. The size may come in as a string from the ticket
     * settings; an empty one keeps the current size.
     */
    public function SetFont($family, $style = '', $size = null, $fontfile = '', $subset = 'default', $out = true)
    {
        $name  = strtolower((string) $family);
        $style = strtoupper((string) $style);
        $size  = (float) $size > 0 ? (float) $size : null;

        if (in_array($name, self::OWN_FONTS, true))
        {
            // Raleway and Open Sans come regular and bold
            $style = str_contains($style, 'B') ? 'B' : '';
            $key   = $name . ($style === 'B' ? 'b' : '');

            if (!isset($this->loadedFonts[$key]))
            {
                $this->AddFont($name, $style, self::fontDir() . $key . '.php');
                $this->loadedFonts[$key] = true;
            }
        }

        return parent::SetFont($name, $style, $size, $fontfile, $subset, $out);
    }

    /**
     * The text without what has no place on a line: a tab becomes a space (FPDF printed nothing,
     * and a TrueType font draws a box) and the other control characters go. New lines stay.
     */
    private function plain(string $text): string
    {
        $text = str_replace(["\r", "\t"], ['', ' '], $text);

        return preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $text) ?? $text;
    }

    /**
     * Whether the font now selected has a glyph for every character of the text.
     */
    private function covers(string $text): bool
    {
        $font = $this->CurrentFont;

        if (($font['type'] ?? '') === 'core')
        {
            // The core fonts print the Windows-1252 characters
            return mb_convert_encoding(mb_convert_encoding($text, 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252') === $text;
        }

        $widths = $font['cw'] ?? [];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char)
        {
            $code = mb_ord($char, 'UTF-8');

            if ($code < 32 || in_array($code, self::INVISIBLE, true))
            {
                continue;
            }

            if (!isset($widths[$code]))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Switches to the fallback font when the font now selected can't print the text. Returns what
     * restoreFont() needs, or null when nothing changed.
     */
    private function fallbackFor(string $text): ?array
    {
        if ($text === '' || $this->covers($text))
        {
            return null;
        }

        $saved = [$this->FontFamily, $this->FontStyle, $this->FontSizePt];

        parent::SetFont(self::FALLBACK_FONT, str_contains((string) $this->FontStyle, 'B') ? 'B' : '', $this->FontSizePt);

        return $saved;
    }

    private function restoreFont(?array $saved): void
    {
        if ($saved !== null)
        {
            $this->SetFont($saved[0], $saved[1], $saved[2]);
        }
    }

    /**
     * The width of a text in the current font, in the unit of the document. A text the font
     * can't print is measured in the fallback font, the one it will be printed in.
     */
    public function GetStringWidth($s, $fontname = '', $fontstyle = '', $fontsize = 0, $getarray = false)
    {
        if ($fontname !== '' || $getarray || !is_string($s))
        {
            return parent::GetStringWidth($s, $fontname, $fontstyle, $fontsize, $getarray);
        }

        $s     = $this->plain($s);
        $saved = $this->fallbackFor($s);
        $width = parent::GetStringWidth($s);
        $this->restoreFont($saved);

        return $width;
    }

    /**
     * One line of text in a cell, like FPDF: the baseline at half the height of the cell plus
     * 0.3 x the font size. TCPDF takes the font's own centre line, so the top padding of the cell
     * is set to what puts the baseline in the same place (the border and fill stay as they were).
     */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $stretch = 0, $ignore_min_height = false, $calign = 'T', $valign = 'M')
    {
        $txt   = $this->plain((string) $txt);
        $saved = $this->fallbackFor($txt);

        $padding                 = $this->cell_padding;
        $this->cell_padding['T'] = $h / 2 + 0.3 * $this->FontSize - $this->FontAscent;

        $result = parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch, true, 'T', 'T');

        $this->cell_padding = $padding;
        $this->restoreFont($saved);

        return $result;
    }

    /**
     * Text from the current position, like FPDF's Write(): at the right of the text before it,
     * and at the left margin when it doesn't fit. The height is that of a line, as in FPDF: with 0
     * the rest of a text that does not fit lands on the same height, which the layout editor warns
     * about (field in red). A new line character starts a new line.
     */
    public function Write($h, $txt, $link = '', $fill = false, $align = '', $ln = false, $stretch = 0, $firstline = false, $firstblock = false, $maxh = 0, $wadj = 0, $margin = null)
    {
        $txt = $this->plain((string) $txt);

        if ($txt === '')
        {
            return 0;
        }

        $saved      = $this->fallbackFor($txt);
        $lineHeight = (float) $h;
        $padding    = $this->cell_padding['L'] + $this->cell_padding['R'];

        foreach (explode("\n", $txt) as $index => $line)
        {
            if ($index > 0)
            {
                $this->y += $lineHeight;
                $this->x  = $this->lMargin;
            }

            $current = '';

            foreach (explode(' ', $line) as $wordIndex => $word)
            {
                $candidate = $wordIndex === 0 ? $word : $current . ' ' . $word;

                if ($current !== '' && $this->GetStringWidth($candidate) + $padding > $this->w - $this->rMargin - $this->x)
                {
                    $this->writeLine($h, $current, $link, $fill);
                    $this->y += $lineHeight;
                    $this->x  = $this->lMargin;
                    $current  = $word;
                }
                else
                {
                    $current = $candidate;
                }
            }

            $this->writeLine($h, $current, $link, $fill);
        }

        $this->restoreFont($saved);

        return 0;
    }

    /**
     * Prints one piece of Write() and moves the position to the end of its text.
     */
    private function writeLine($h, string $text, $link, $fill): void
    {
        if ($text === '')
        {
            return;
        }

        $width = $this->GetStringWidth($text);

        $this->Cell($width, (float) $h, $text, 0, 0, 'L', $fill, $link);
    }

    /**
     * Text in a block of the width $w, with lines of the height $h, like FPDF's MultiCell(): lines
     * break at "\n" and at a space where the text doesn't fit. A width of 0 runs to the right margin.
     * Afterwards the position is at the left margin under the block. Align is L, R or C, a
     * border is not drawn.
     */
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false, $ln = 1, $x = null, $y = null, $reseth = true, $stretch = 0, $ishtml = false, $autopadding = true, $maxh = 0, $valign = 'T', $fitcell = false)
    {
        $txt = $this->plain((string) $txt);

        if ($txt === '')
        {
            return 0;
        }

        $saved   = $this->fallbackFor($txt);
        $width   = (float) $w > 0 ? (float) $w : $this->w - $this->rMargin - $this->x;
        $inner   = $width - $this->cell_padding['L'] - $this->cell_padding['R'];
        $align   = $align === 'J' ? 'L' : $align;
        $left    = $this->x;
        $lines   = 0;

        foreach (explode("\n", $txt) as $line)
        {
            $current = '';

            foreach (explode(' ', $line) as $wordIndex => $word)
            {
                $candidate = $wordIndex === 0 ? $word : $current . ' ' . $word;

                if ($current !== '' && $this->GetStringWidth($candidate) > $inner)
                {
                    $this->x = $left;
                    $this->Cell($width, $h, $current, 0, 2, $align, $fill);
                    $lines++;
                    $current = $word;
                }
                else
                {
                    $current = $candidate;
                }
            }

            $this->x = $left;
            $this->Cell($width, $h, $current, 0, 2, $align, $fill);
            $lines++;
        }

        $this->x = $this->lMargin;
        $this->restoreFont($saved);

        return $lines;
    }

    /**
     * Text at X/Y in a direction: R (right, as normal), L (left, upside down), U (up) or D
     * (down). Y is the baseline and the text starts exactly at X.
     */
    public function TextWithDirection($x, $y, $txt, $direction = 'R')
    {
        $angles = ['R' => 0, 'U' => 90, 'L' => 180, 'D' => 270];

        $this->textAtAngle((float) $x, (float) $y, (string) $txt, $angles[$direction] ?? 0);
    }

    /**
     * Text at X/Y at an angle (degrees, counterclockwise). The letters stand upright on the
     * baseline; the $font_angle that FPDF's version could skew them with was never used.
     */
    public function TextWithRotation($x, $y, $txt, $txt_angle, $font_angle = 0)
    {
        $this->textAtAngle((float) $x, (float) $y, (string) $txt, (float) $txt_angle);
    }

    private function textAtAngle(float $x, float $y, string $txt, float $angle): void
    {
        $txt   = $this->plain($txt);
        $saved = $this->fallbackFor($txt);
        $fromX = $this->x;
        $fromY = $this->y;
        $width = $this->GetStringWidth($txt) + $this->cell_padding['L'] + $this->cell_padding['R'];

        $this->StartTransform();
        $this->Rotate($angle, $x, $y);

        // A cell of no height puts the baseline 0.3 x the font size under its top; the cell margin
        // is taken back so the text starts at X.
        $this->SetXY($x - $this->cell_padding['L'], $y - 0.3 * $this->FontSize);
        $this->Cell($width, 0, $txt, 0, 0, 'L');

        $this->StopTransform();

        $this->x = $fromX;
        $this->y = $fromY;
        $this->restoreFont($saved);
    }

    /**
     * The document, with the order of arguments FPDF has: the destination (I, D, F or S), then
     * the name. The other order, as TCPDF has it, is recognised like FPDF does.
     */
    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
        if (strlen((string) $dest) !== 1)
        {
            [$dest, $name] = [$name, $dest];
        }

        return parent::Output($name !== '' ? $name : 'doc.pdf', $dest !== '' ? $dest : 'I');
    }
}
