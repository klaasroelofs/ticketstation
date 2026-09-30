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

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

/**
 * What the layout editor on the "Ticket Layout" tab (assets/js/ticketlayouteditor.js) needs to
 * know about the printed fields, so it can place them exactly where ticketcreator::doPDF() and
 * TicketPreviewCreator print them.
 *
 * The editor mirrors FPDF's text placement: Write() starts the text 1 mm (the cell margin) right
 * of X, with the baseline at Y + 0.3 x the font size, and TextWithDirection('U') starts at X/Y on
 * the baseline and runs upwards. Text widths come from the same FPDF font files the PDF uses.
 */
class TicketLayoutFields
{
    /**
     * The printed fields in the order the editor lists them.
     *
     * bold:      printed in bold (the index is bold only with its prepend text)
     * kind:      text, rotated (upwards) or qr
     * condition: when the field is left out of a real ticket, shown as a hint in the editor
     */
    const FIELDS = [
        'eventname'        => ['label' => 'COM_TICKETSTATION_EVENTNAME', 'bold' => true, 'kind' => 'text'],
        'ticketname'       => ['label' => 'COM_TICKETSTATION_TICKETNAME', 'bold' => true, 'kind' => 'text'],
        'freetext_1'       => ['label' => 'COM_TICKETSTATION_FREETEXT_1', 'bold' => true, 'kind' => 'text'],
        'ticketdate'       => ['label' => 'COM_TICKETSTATION_TICKETDATE', 'bold' => true, 'kind' => 'text'],
        'venue'            => ['label' => 'COM_TICKETSTATION_VENUE', 'bold' => true, 'kind' => 'text'],
        'ticketprice'      => ['label' => 'COM_TICKETSTATION_TICKETPRICE', 'bold' => true, 'kind' => 'text'],
        'orderdate'        => ['label' => 'COM_TICKETSTATION_ORDERDATE', 'bold' => true, 'kind' => 'text'],
        'client'           => ['label' => 'COM_TICKETSTATION_CLIENT', 'bold' => true, 'kind' => 'text'],
        'seatnumber'       => ['label' => 'COM_TICKETSTATION_SEATNUMBER', 'bold' => true, 'kind' => 'text', 'condition' => 'COM_TICKETSTATION_TLE_ONLY_SEATED'],
        'orderreference'   => ['label' => 'COM_TICKETSTATION_ORDERREFERENCE', 'bold' => true, 'kind' => 'text', 'condition' => 'COM_TICKETSTATION_TLE_ONLY_REFERENCE'],
        'orderticketindex' => ['label' => 'COM_TICKETSTATION_ORDERTICKETINDEX', 'bold' => true, 'kind' => 'text', 'condition' => 'COM_TICKETSTATION_TLE_ONLY_MULTIPLE'],
        'ordernumber'      => ['label' => 'COM_TICKETSTATION_ORDERNUMBER', 'bold' => false, 'kind' => 'rotated'],
        'qrcode'           => ['label' => 'COM_TICKETSTATION_QRCODE', 'bold' => false, 'kind' => 'qr'],
    ];

    /**
     * FPDF's font files per family (see TicketFont): regular and bold.
     */
    const FONT_FILES = [
        'raleway'   => ['raleway', 'ralewayb'],
        'opensans'  => ['opensans', 'opensansb'],
        'helvetica' => ['helvetica', 'helveticab'],
        'times'     => ['times', 'timesb'],
        'courier'   => ['courier', 'courierb'],
    ];

    /**
     * The character widths (1/1000 of the font size, indexed by the Windows-1252 byte) of every
     * family, as [family => [regular widths, bold widths]].
     */
    public static function metrics(): array
    {
        $metrics = [];

        foreach (self::FONT_FILES as $family => $files) {
            foreach ($files as $style => $file) {
                $metrics[$family][$style] = self::widths(Pdf::fontFile($file));
            }
        }

        return $metrics;
    }

    /**
     * The modules of a QR code like the one on a real ticket, as one string of 0/1 per row.
     *
     * A ticket's code is 32 random hex characters (ticketcreator::doPDF()), encoded like
     * get_qr_image_with_logo() does (UTF-8, error correction Low), which always gives the same
     * number of modules. A fixed sample of that length keeps the editor's picture stable.
     */
    public static function sampleQr(): array
    {
        require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

        $qrCode = new QrCode(
            data: md5('Ticketstation'),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low
        );

        $matrix = (new MatrixFactory())->create($qrCode);
        $count  = $matrix->getBlockCount();
        $rows   = [];

        for ($row = 0; $row < $count; $row++) {
            $line = '';

            for ($column = 0; $column < $count; $column++) {
                $line .= $matrix->getBlockValue($row, $column) ? '1' : '0';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    private static function widths(string $path): array
    {
        $widths = array_fill(0, 256, 500);

        if (!is_file($path)) {
            return $widths;
        }

        ## FPDF's JSON font definition: cw holds the width of every byte, 0 to 255.
        $font = json_decode((string) file_get_contents($path), true);

        foreach ($font['cw'] ?? [] as $byte => $width) {
            $widths[(int) $byte] = (int) $width;
        }

        return $widths;
    }
}
