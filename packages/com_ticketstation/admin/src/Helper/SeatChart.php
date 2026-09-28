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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * The geometry of a seat chart, shared by the editor and the three pages that show a chart
 * (seat selection on the site, the backend reservation tool and the scan chart).
 *
 * Seat positions are stored in pixels on a canvas. The canvas has a fixed design size; the
 * pages draw the seats, the background image and the shapes as percentages of it, so the chart
 * scales with the screen. The background image sits at bg_offset_x/bg_offset_y on the canvas:
 * charts made before 2.6.0 drew it 30px lower, and keep that offset.
 */
class SeatChart
{
    /** Folder of the background images, relative to the site root. */
    public const IMAGE_DIR = 'images/ticketstation/seatplans';

    /** Canvas size of a chart without background image or explicit size. */
    public const DEFAULT_WIDTH  = 750;
    public const DEFAULT_HEIGHT = 850;

    /** Space kept free to the right of and below the outermost seat or shape. */
    private const MARGIN = 10;

    /** The smallest a seat may be drawn on a narrow screen before the chart scrolls instead. */
    private const MIN_SEAT_PX = 16;

    /**
     * The chart settings row of a chart owner (the parent ticket), or null when the chart has
     * none yet. A ticket should have one row; older data can hold more, so the first one counts.
     */
    public static function settings(int $ownerId): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_seatplansettings'))
            ->where($db->quoteName('ticketid') . ' = ' . $ownerId)
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    /**
     * The settings of a chart that has no settings row yet.
     */
    public static function defaults(int $ownerId): object
    {
        return (object) [
            'id'               => 0,
            'ticketid'         => $ownerId,
            'background_color' => 'ffffff',
            'border_color'     => '000000',
            'font_color'       => '000000',
            'seat_width'       => '22',
            'seat_height'      => '22',
            'type'             => 1,
            'background_image' => '',
            'bg_offset_x'      => 0,
            'bg_offset_y'      => 0,
            'canvas_width'     => 0,
            'canvas_height'    => 0,
            'grid_size'        => 10,
            'shapes'           => '[]',
        ];
    }

    /**
     * The background image of a chart as [url, path, width, height], or null without one.
     * Falls back to the image location used before 2.6.0, for a chart whose image wasn't moved.
     */
    public static function background(?object $settings, int $ownerId): ?array
    {
        $relative = $settings ? trim((string) ($settings->background_image ?? '')) : '';

        if ($relative === '' || !self::isImagePath($relative) || !is_file(JPATH_ROOT . '/' . $relative)) {
            $relative = self::legacyImage($ownerId) ?? '';
        }

        if ($relative === '') {
            return null;
        }

        $size = @getimagesize(JPATH_ROOT . '/' . $relative);

        if (!$size) {
            return null;
        }

        return [
            'url'    => Uri::root(true) . '/' . $relative,
            'path'   => $relative,
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * The image a chart used before 2.6.0, relative to the site root, or null.
     */
    public static function legacyImage(int $ownerId): ?string
    {
        foreach (['png', 'jpg'] as $extension) {
            $relative = 'administrator/components/com_ticketstation/assets/seatcharts/seatchart' . $ownerId . '.' . $extension;

            if (is_file(JPATH_ROOT . '/' . $relative)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * Whether a stored image path points into the seat plan image folder (or the old one).
     */
    public static function isImagePath(string $relative): bool
    {
        return (bool) preg_match('#^(' . preg_quote(self::IMAGE_DIR, '#') . '|administrator/components/com_ticketstation/assets/seatcharts)/[A-Za-z0-9._-]+\.(png|jpe?g|webp)$#', $relative);
    }

    /**
     * The canvas size in pixels: the stored size, or else the background image (with its
     * offset) or the default; always large enough for every seat and shape.
     *
     * @param   object[]  $seats  Rows with x_pos, y_pos, width, height.
     */
    public static function canvas(?object $settings, array $seats, ?array $background, array $shapes = []): array
    {
        $width  = (int) ($settings->canvas_width ?? 0);
        $height = (int) ($settings->canvas_height ?? 0);

        if ($width <= 0 || $height <= 0) {
            if ($background) {
                $width  = max(1, (int) ($settings->bg_offset_x ?? 0) + $background['width']);
                $height = max(1, (int) ($settings->bg_offset_y ?? 0) + $background['height']);
            } else {
                $width  = self::DEFAULT_WIDTH;
                $height = self::DEFAULT_HEIGHT;
            }
        }

        foreach ($seats as $seat) {
            $width  = max($width, (int) $seat->x_pos + (int) $seat->width + self::MARGIN);
            $height = max($height, (int) $seat->y_pos + (int) $seat->height + self::MARGIN);
        }

        foreach ($shapes as $shape) {
            $width  = max($width, (int) $shape['x'] + (int) $shape['w'] + self::MARGIN);
            $height = max($height, (int) $shape['y'] + (int) $shape['h'] + self::MARGIN);
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * The non-sellable shapes of a chart (stage, aisles, labels), cleaned.
     */
    public static function shapes(?object $settings): array
    {
        $decoded = json_decode((string) ($settings->shapes ?? ''), true);

        return self::cleanShapes(is_array($decoded) ? $decoded : []);
    }

    /**
     * Keeps only well-formed shapes: kind rect or text, integer geometry, a short label and
     * six-digit hex colours.
     */
    public static function cleanShapes(array $shapes): array
    {
        $clean = [];

        foreach ($shapes as $shape) {
            if (!is_array($shape) || !in_array($shape['kind'] ?? '', ['rect', 'text'], true)) {
                continue;
            }

            $clean[] = [
                'kind'       => $shape['kind'],
                'x'          => max(0, (int) ($shape['x'] ?? 0)),
                'y'          => max(0, (int) ($shape['y'] ?? 0)),
                'w'          => min(5000, max(4, (int) ($shape['w'] ?? 100))),
                'h'          => min(5000, max(4, (int) ($shape['h'] ?? 30))),
                'label'      => mb_substr(trim((string) ($shape['label'] ?? '')), 0, 100),
                'color'      => self::hex($shape['color'] ?? '', 'dddddd'),
                'text_color' => self::hex($shape['text_color'] ?? '', '000000'),
                'font'       => min(96, max(6, (int) ($shape['font'] ?? 14))),
            ];
        }

        return $clean;
    }

    /**
     * A six-digit lower-case hex colour without "#", or $fallback.
     */
    public static function hex($value, string $fallback = ''): string
    {
        $value = ltrim(trim((string) $value), '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    /**
     * The position and size of a box on the canvas as CSS percentages.
     */
    public static function boxStyle(int $x, int $y, int $w, int $h, array $canvas): string
    {
        $pct = fn (float $value, int $total) => round($value / $total * 100, 4) . '%';

        return 'left:' . $pct($x, $canvas['width']) . ';top:' . $pct($y, $canvas['height'])
            . ';width:' . $pct($w, $canvas['width']) . ';height:' . $pct($h, $canvas['height']) . ';';
    }

    /**
     * The position, size and font size of a seat, as inline CSS for the chart.
     */
    public static function seatStyle(object $seat, array $canvas): string
    {
        $width  = max(1, (int) $seat->width);
        $height = max(1, (int) $seat->height);

        return self::boxStyle((int) $seat->x_pos, (int) $seat->y_pos, $width, $height, $canvas)
            . '--ts-fs:' . self::fontSize($width, $height) . ';';
    }

    /**
     * The font size of a seat label in canvas pixels: about 40% of the seat, 9px for the usual
     * 22px seat.
     */
    public static function fontSize(int $width, int $height): int
    {
        return max(7, (int) round(min($width, $height) * 0.41));
    }

    /**
     * The opening markup of a chart: the zoom buttons, the scroll area, the canvas with its
     * background image, and the shapes. Seats go inside; close() ends it.
     *
     * @param   object[]  $seats  Used for the smallest seat, which sets how far the chart may shrink.
     * @param   string    $id     Id of the canvas element (pages address the seats through it).
     */
    public static function open(array $canvas, ?array $background, ?object $settings, array $shapes, array $seats, string $id = 'glassbox'): string
    {
        $smallest = 0;

        foreach ($seats as $seat) {
            $side     = min((int) $seat->width, (int) $seat->height);
            $smallest = $side > 0 && ($smallest === 0 || $side < $smallest) ? $side : $smallest;
        }

        ## On a narrow screen the chart shrinks until its smallest seat is MIN_SEAT_PX wide, then scrolls.
        $minWidth = $smallest > 0 ? min($canvas['width'], (int) ceil($canvas['width'] * self::MIN_SEAT_PX / $smallest)) : $canvas['width'];

        $esc  = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $vars = '--ts-chart-w:' . $canvas['width'] . ';--ts-chart-h:' . $canvas['height'] . ';--ts-chart-min:' . $minWidth . ';';

        $html  = '<div class="ts-chart-frame">';
        $html .= '<div class="ts-chart-zoom" role="group">'
            . '<button type="button" class="ts-chart-zoom__btn" data-ts-zoom="out" aria-label="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_OUT')) . '" title="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_OUT')) . '">&minus;</button>'
            . '<button type="button" class="ts-chart-zoom__btn" data-ts-zoom="fit" aria-label="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_FIT')) . '" title="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_FIT')) . '">&#x2922;</button>'
            . '<button type="button" class="ts-chart-zoom__btn" data-ts-zoom="in" aria-label="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_IN')) . '" title="' . $esc(Text::_('COM_TICKETSTATION_SEATMAP_ZOOM_IN')) . '">+</button>'
            . '</div>';
        $html .= '<div class="ts-chart-scroll">';
        $html .= '<div class="ts-chart" id="' . $esc($id) . '" style="' . $vars . '">';

        if ($background) {
            $html .= '<img class="ts-chart__bg" src="' . $esc($background['url']) . '" alt="" draggable="false" style="'
                . self::boxStyle((int) ($settings->bg_offset_x ?? 0), (int) ($settings->bg_offset_y ?? 0), $background['width'], $background['height'], $canvas) . '">';
        }

        foreach ($shapes as $shape) {
            $html .= '<div class="ts-chart__shape ts-chart__shape--' . $shape['kind'] . '" aria-hidden="true" style="'
                . self::boxStyle($shape['x'], $shape['y'], $shape['w'], $shape['h'], $canvas)
                . '--ts-fs:' . $shape['font'] . ';color:#' . $shape['text_color'] . ';'
                . ($shape['kind'] === 'rect' ? 'background-color:#' . $shape['color'] . ';' : '') . '">'
                . $esc($shape['label']) . '</div>';
        }

        return $html;
    }

    /**
     * The closing markup for open().
     */
    public static function close(): string
    {
        return '</div></div></div>';
    }

    /**
     * Loads the chart stylesheet and zoom script on a page of either application.
     */
    public static function loadAssets(): void
    {
        $wa   = Factory::getApplication()->getDocument()->getWebAssetManager();
        $base = Uri::root() . 'components/com_ticketstation/assets/';

        if (!$wa->assetExists('style', 'com_ticketstation.seatmap')) {
            $wa->registerStyle('com_ticketstation.seatmap', $base . 'css/seatmap.css');
            $wa->registerScript('com_ticketstation.seatmap', $base . 'javascripts/seatmap.js', [], ['defer' => true]);
        }

        $wa->useStyle('com_ticketstation.seatmap')->useScript('com_ticketstation.seatmap');
    }
}
