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
 * A minimal Excel (.xlsx) writer: one sheet of text and number cells, a bold header row. Text
 * cells stay text, so phone numbers keep their leading zero and nothing is read as a formula.
 * Needs the zip extension.
 */
class Xlsx
{
    /**
     * @param   array    $header       column titles
     * @param   array    $rows         rows of cells; an int or float is a number, anything else text
     * @param   boolean  $rightToLeft  show the sheet right to left
     * @param   string   $title        worksheet name (at most 31 characters)
     *
     * @return  string  the file, or '' when it can't be made
     */
    public static function build(array $header, array $rows, bool $rightToLeft = false, string $title = 'Tickets'): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }

        $title = trim(mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $title), 0, 31)) ?: 'Sheet1';
        $file  = tempnam(sys_get_temp_dir(), 'tsx');
        $zip   = new \ZipArchive();

        if ($file === false || $zip->open($file, \ZipArchive::OVERWRITE) !== true) {
            return '';
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($title) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        // Style 0: normal; 1: bold header; 2: number with two decimals
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs></styleSheet>');

        $sheet = '';
        $count = 0;

        foreach (array_merge([$header], $rows) as $index => $row) {
            $count++;
            $sheet .= '<row r="' . $count . '">';
            $column = 0;

            foreach ($row as $value) {
                $ref = self::column($column++) . $count;

                if ($index > 0 && (is_int($value) || is_float($value))) {
                    $sheet .= '<c r="' . $ref . '" s="2"><v>' . $value . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $ref . '" t="inlineStr"' . ($index === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">'
                        . self::escape((string) $value) . '</t></is></c>';
                }
            }

            $sheet .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"' . ($rightToLeft ? ' rightToLeft="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols><col min="1" max="' . max(1, count($header)) . '" width="18" customWidth="1"/></cols>'
            . '<sheetData>' . $sheet . '</sheetData></worksheet>');

        $zip->close();

        $contents = (string) file_get_contents($file);
        @unlink($file);

        return $contents;
    }

    /** A column number (0 based) as letters: 0 = A, 26 = AA. */
    private static function column(int $number): string
    {
        $letters = '';

        for ($number++; $number > 0; $number = intdiv($number - 1, 26)) {
            $letters = chr(65 + ($number - 1) % 26) . $letters;
        }

        return $letters;
    }

    /** Text for XML, without the control characters XML doesn't allow. */
    private static function escape(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
