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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * The seat plan editor's data: the whole chart of one owner (a parent ticket) as one layout,
 * saved back in one go, plus the seat plan templates of a venue.
 *
 * A seat is "locked" once it belongs to an order (orderid > 0): sold, or lying in a shopping
 * basket, which also has an order row. A locked seat may be moved and resized, but keeps its
 * section, row, number and status, and cannot be deleted (Klaas's rule). Every write that
 * changes more than the position checks this in its own WHERE clause, so a seat sold while the
 * editor was open is never changed.
 *
 * booked = 1 without an order is not a sale: it is a blocked seat from before 2.5.0 (converted
 * by 2.5.0.1.sql and again by 2.6.1.sql) or a claim whose order was never written. Such seats
 * stay editable; saving their status sets booked = blocked again.
 */
class SeatplanLayout
{
    /** SQL condition for a seat that doesn't belong to an order. */
    private const UNLOCKED = 'orderid = 0';

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * The parent ticket that owns a chart, with its event and venue, or null.
     */
    public static function owner(int $ownerId): ?object
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['t.ticketid', 't.ticketname', 't.ticketcode', 't.parent', 't.venue', 'e.eventname', 'e.eventcode', 'v.venue AS venuename'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON e.eventid = t.eventid')
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON v.id = t.venue')
            ->where($db->quoteName('t.ticketid') . ' = ' . $ownerId);

        $owner = $db->setQuery($query)->loadObject();

        return $owner && (int) $owner->parent === 0 ? $owner : null;
    }

    /**
     * Everything the editor needs for one chart.
     */
    public static function forEditor(int $ownerId): array
    {
        $owner    = self::owner($ownerId);
        $stored   = SeatChart::settings($ownerId);
        $settings = $stored ?? SeatChart::defaults($ownerId);
        $seats    = self::seats($ownerId);
        $shapes   = SeatChart::shapes($settings);
        $bg       = SeatChart::background($settings, $ownerId);

        ## A chart still using an image from before 2.6.0 shows it; saving stores its path.
        $settings->background_image = $bg ? $bg['path'] : '';

        $rows = array_map(fn ($seat) => (object) ['x_pos' => $seat['x'], 'y_pos' => $seat['y'], 'width' => $seat['w'], 'height' => $seat['h']], $seats);

        return [
            'owner' => [
                'id'        => (int) $owner->ticketid,
                'name'      => $owner->ticketname,
                'code'      => $owner->ticketcode,
                'event'     => trim($owner->eventcode . ' ' . $owner->eventname),
                'venueId'   => (int) $owner->venue,
                'venueName' => (string) $owner->venuename,
            ],
            'isNew'     => $stored === null,
            'settings'  => self::editorSettings($settings, $bg),
            'autoSize'  => SeatChart::canvas((object) ['canvas_width' => 0, 'canvas_height' => 0, 'bg_offset_x' => $settings->bg_offset_x, 'bg_offset_y' => $settings->bg_offset_y], $rows, $bg, $shapes),
            'shapes'    => $shapes,
            'kinds'     => self::kinds($ownerId, $settings, $seats),
            'seats'     => $seats,
            'templates' => self::templates((int) $owner->venue),
            'sources'   => self::sources($ownerId),
        ];
    }

    /**
     * The chart settings as the editor uses them.
     */
    private static function editorSettings(object $settings, ?array $bg): array
    {
        return [
            'seat_width'       => max(4, (int) $settings->seat_width ?: 22),
            'seat_height'      => max(4, (int) $settings->seat_height ?: 22),
            'background_color' => SeatChart::hex($settings->background_color, 'ffffff'),
            'border_color'     => SeatChart::hex($settings->border_color, '000000'),
            'font_color'       => SeatChart::hex($settings->font_color, '000000'),
            'background_image' => $bg ? $bg['path'] : '',
            'background_url'   => $bg ? $bg['url'] : '',
            'bg_width'         => $bg ? $bg['width'] : 0,
            'bg_height'        => $bg ? $bg['height'] : 0,
            'bg_offset_x'      => (int) $settings->bg_offset_x,
            'bg_offset_y'      => (int) $settings->bg_offset_y,
            'canvas_width'     => (int) $settings->canvas_width,
            'canvas_height'    => (int) $settings->canvas_height,
            'grid_size'        => max(1, (int) $settings->grid_size ?: 10),
        ];
    }

    /**
     * The seats of a chart in the editor's format.
     */
    public static function seats(int $ownerId): array
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['c.*', 'o.ordercode', 'o.scanned'])
            ->from($db->quoteName('#__ticketstation_seatplancoords', 'c'))
            ->join('LEFT', $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = c.orderid AND c.orderid > 0')
            ->where('(c.ticketid = ' . $ownerId . ' OR c.parent = ' . $ownerId . ')')
            ->order('c.id ASC');

        $seats = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $locked = (int) $row->orderid > 0;

            $seats[] = [
                'id'        => (int) $row->id,
                'x'         => (int) $row->x_pos,
                'y'         => (int) $row->y_pos,
                'w'         => (int) $row->width,
                'h'         => (int) $row->height,
                'row'       => (string) $row->row_name,
                'num'       => (int) $row->seatid,
                'ticketid'  => (int) $row->ticketid,
                ## Taken without an order counts as blocked, as on the site (see the class comment).
                'blocked'   => (int) $row->orderid === 0 && ((int) $row->blocked === 1 || (int) $row->booked === 1),
                'type'      => (int) $row->type,
                'locked'    => $locked,
                'ordercode' => $locked ? (string) $row->ordercode : '',
                'scanned'   => (int) $row->scanned === 1,
            ];
        }

        return $seats;
    }

    /**
     * What a seat can be: a free seat of the owner, or a section seat of one of its child
     * tickets, with the colours each is drawn in. A child with seats is a section; a published
     * child without seats is a price category, which a free seat sells.
     */
    private static function kinds(int $ownerId, object $settings, array $seats): array
    {
        $used = array_count_values(array_column($seats, 'ticketid'));

        $kinds = [[
            'id'        => $ownerId,
            'name'      => Text::_('COM_TICKETSTATION_SEAT_KIND_FREE'),
            'free'      => true,
            'published' => true,
            'category'  => false,
            'price'     => null,
            'colours'   => [
                'background_color' => SeatChart::hex($settings->background_color, 'ffffff'),
                'border_color'     => SeatChart::hex($settings->border_color, '000000'),
                'font_color'       => SeatChart::hex($settings->font_color, '000000'),
            ],
        ]];

        foreach (SeatplanSettings::getChildColours($ownerId) as $child) {
            $kinds[] = [
                'id'        => (int) $child->ticketid,
                'name'      => $child->ticketname,
                'free'      => false,
                'published' => (int) $child->published === 1,
                'category'  => (int) $child->published === 1 && empty($used[(int) $child->ticketid]),
                'price'     => (float) $child->ticketprice,
                'colours'   => [
                    'background_color' => SeatChart::hex($child->background_color),
                    'border_color'     => SeatChart::hex($child->border_color),
                    'font_color'       => SeatChart::hex($child->font_color),
                ],
            ];
        }

        return $kinds;
    }

    /**
     * Other charts to copy seats from: parent tickets with a seat chart, newest event first.
     */
    private static function sources(int $ownerId): array
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['t.ticketid AS id', "CONCAT(IFNULL(e.eventcode, ''), ' | ', t.ticketname) AS name"])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON e.eventid = t.eventid')
            ->where('t.ticketid != ' . $ownerId)
            ->where('t.show_seatplans = 1')
            ->where('t.parent = 0')
            ->where('EXISTS (SELECT 1 FROM #__ticketstation_seatplancoords AS c WHERE c.ticketid = t.ticketid OR c.parent = t.ticketid)')
            ->order('t.ticketid DESC');

        return array_map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name], $db->setQuery($query)->loadObjectList());
    }

    /**
     * The seat plan templates of a venue, newest first, without their layouts.
     */
    public static function templates(int $venueId): array
    {
        if ($venueId <= 0) {
            return [];
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select(['id', 'name', 'modified', 'layout'])
            ->from($db->quoteName('#__ticketstation_seatplantemplates'))
            ->where($db->quoteName('venue_id') . ' = ' . $venueId)
            ->order($db->quoteName('name') . ' ASC');

        $templates = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $layout      = json_decode($row->layout, true);
            $templates[] = [
                'id'       => (int) $row->id,
                'name'     => $row->name,
                'modified' => HTMLHelper::_('date', $row->modified, 'Y-m-d H:i'),
                'seats'    => is_array($layout['seats'] ?? null) ? count($layout['seats']) : 0,
            ];
        }

        return $templates;
    }

    /**
     * Whether any seat of a chart is sold or in a basket.
     */
    public static function hasLockedSeats(int $ownerId): bool
    {
        $db = self::db();
        $db->setQuery('SELECT COUNT(*) FROM #__ticketstation_seatplancoords WHERE (ticketid = ' . $ownerId . ' OR parent = ' . $ownerId . ') AND NOT (' . self::UNLOCKED . ')');

        return (int) $db->loadResult() > 0;
    }

    /**
     * Saves the editor's layout. Returns the warnings about changes that were refused.
     *
     * @param   array  $data  settings, shapes, colours [childId => colours], seats, deleted (ids)
     */
    public static function save(int $ownerId, array $data): array
    {
        $db       = self::db();
        $warnings = [];
        $old      = SeatChart::settings($ownerId);

        $db->transactionStart();

        try {
            $settings = self::storeSettings($ownerId, (array) ($data['settings'] ?? []), (array) ($data['shapes'] ?? []), $old);
            SeatplanSettings::saveChildColours($ownerId, (array) ($data['colours'] ?? []));

            $kinds    = self::kindIds($ownerId);
            $existing = [];

            foreach (self::seats($ownerId) as $seat) {
                $existing[$seat['id']] = $seat;
            }

            foreach ((array) ($data['deleted'] ?? []) as $id) {
                $id = (int) $id;

                if (!isset($existing[$id])) {
                    continue;
                }

                $db->setQuery('DELETE FROM #__ticketstation_seatplancoords WHERE id = ' . $id . ' AND ' . self::UNLOCKED)->execute();

                if ($db->getAffectedRows() === 0) {
                    $warnings[] = Text::sprintf('COM_TICKETSTATION_SEATEDITOR_WARN_NOT_DELETED', self::label($existing[$id]));
                }

                unset($existing[$id]);
            }

            foreach ((array) ($data['seats'] ?? []) as $input) {
                if (!is_array($input)) {
                    continue;
                }

                $seat = self::cleanSeat($input, $ownerId, $kinds, $settings);
                $id   = (int) ($input['id'] ?? 0);

                if ($id > 0 && !isset($existing[$id])) {
                    ## Deleted above, or not part of this chart.
                    continue;
                }

                if ($id === 0) {
                    $record = (object) [
                        'orderid'  => 0,
                        'x_pos'    => $seat['x'],
                        'y_pos'    => $seat['y'],
                        'ticketid' => $seat['ticketid'],
                        'row_name' => $seat['row'],
                        'seatid'   => $seat['num'],
                        'booked'   => $seat['blocked'],
                        'blocked'  => $seat['blocked'],
                        'parent'   => $seat['ticketid'] === $ownerId ? 0 : $ownerId,
                        'type'     => max(1, (int) $settings->type),
                        'width'    => $seat['w'],
                        'height'   => $seat['h'],
                    ];

                    $db->insertObject('#__ticketstation_seatplancoords', $record);
                    continue;
                }

                $current = $existing[$id];

                if ([$current['x'], $current['y'], $current['w'], $current['h']] !== [$seat['x'], $seat['y'], $seat['w'], $seat['h']]) {
                    $db->setQuery('UPDATE #__ticketstation_seatplancoords SET x_pos = ' . $seat['x'] . ', y_pos = ' . $seat['y']
                        . ', width = ' . $seat['w'] . ', height = ' . $seat['h'] . ' WHERE id = ' . $id)->execute();
                }

                $changed = $current['row'] !== $seat['row'] || $current['num'] !== $seat['num']
                    || $current['ticketid'] !== $seat['ticketid'] || $current['blocked'] !== (bool) $seat['blocked'];

                if (!$changed) {
                    continue;
                }

                $db->setQuery('UPDATE #__ticketstation_seatplancoords SET row_name = ' . $db->quote($seat['row'])
                    . ', seatid = ' . $seat['num']
                    . ', ticketid = ' . $seat['ticketid']
                    . ', parent = ' . ($seat['ticketid'] === $ownerId ? 0 : $ownerId)
                    . ', blocked = ' . $seat['blocked'] . ', booked = ' . $seat['blocked']
                    . ' WHERE id = ' . $id . ' AND ' . self::UNLOCKED)->execute();

                if ($db->getAffectedRows() === 0) {
                    $warnings[] = Text::sprintf('COM_TICKETSTATION_SEATEDITOR_WARN_NOT_CHANGED', self::label($current));
                }
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }

        if ($old && $old->background_image !== $settings->background_image) {
            self::deleteImageIfUnused((string) $old->background_image);
        }

        return $warnings;
    }

    /**
     * Stores the chart settings row of the owner (creating it when missing) and returns it.
     */
    private static function storeSettings(int $ownerId, array $input, array $shapes, ?object $old): object
    {
        $db    = self::db();
        $image = trim((string) ($input['background_image'] ?? ''));

        if ($image !== '' && (!SeatChart::isImagePath($image) || !is_file(JPATH_ROOT . '/' . $image))) {
            $image = $old ? (string) $old->background_image : '';
        }

        $row = (object) [
            'ticketid'         => $ownerId,
            'background_color' => SeatChart::hex($input['background_color'] ?? '', 'ffffff'),
            'border_color'     => SeatChart::hex($input['border_color'] ?? '', '000000'),
            'font_color'       => SeatChart::hex($input['font_color'] ?? '', '000000'),
            'seat_width'       => (string) min(500, max(4, (int) ($input['seat_width'] ?? 22))),
            'seat_height'      => (string) min(500, max(4, (int) ($input['seat_height'] ?? 22))),
            'background_image' => $image,
            'bg_offset_x'      => min(20000, max(-20000, (int) ($input['bg_offset_x'] ?? 0))),
            'bg_offset_y'      => min(20000, max(-20000, (int) ($input['bg_offset_y'] ?? 0))),
            'canvas_width'     => min(20000, max(0, (int) ($input['canvas_width'] ?? 0))),
            'canvas_height'    => min(20000, max(0, (int) ($input['canvas_height'] ?? 0))),
            'grid_size'        => min(200, max(1, (int) ($input['grid_size'] ?? 10))),
            'shapes'           => json_encode(SeatChart::cleanShapes($shapes)),
        ];

        if ($old) {
            $row->id   = (int) $old->id;
            $row->type = (int) $old->type;
            $db->updateObject('#__ticketstation_seatplansettings', $row, 'id');
        } else {
            $row->type = 1;
            $db->insertObject('#__ticketstation_seatplansettings', $row, 'id');
        }

        return $row;
    }

    /**
     * The ticket ids a seat of this chart may belong to: the owner and its child tickets.
     */
    private static function kindIds(int $ownerId): array
    {
        $db = self::db();
        $db->setQuery('SELECT ticketid FROM #__ticketstation_tickets WHERE parent = ' . $ownerId);

        return array_merge([$ownerId], array_map('intval', $db->loadColumn()));
    }

    /**
     * A seat from the editor, with every value in range.
     */
    private static function cleanSeat(array $input, int $ownerId, array $kinds, object $settings): array
    {
        $ticketid = (int) ($input['ticketid'] ?? $ownerId);

        return [
            'x'        => min(20000, max(0, (int) round((float) ($input['x'] ?? 0)))),
            'y'        => min(20000, max(0, (int) round((float) ($input['y'] ?? 0)))),
            'w'        => min(500, max(4, (int) ($input['w'] ?? $settings->seat_width))),
            'h'        => min(500, max(4, (int) ($input['h'] ?? $settings->seat_height))),
            'row'      => mb_substr(trim((string) ($input['row'] ?? '')), 0, 5),
            'num'      => min(99999, max(0, (int) ($input['num'] ?? 0))),
            'ticketid' => in_array($ticketid, $kinds, true) ? $ticketid : $ownerId,
            'blocked'  => empty($input['blocked']) ? 0 : 1,
        ];
    }

    /**
     * The seat as the customer sees it, e.g. "B12".
     */
    private static function label(array $seat): string
    {
        return $seat['row'] . $seat['num'];
    }

    /**
     * Stores an uploaded background image in the seat plan image folder.
     *
     * @return  array  [path, url, width, height]
     *
     * @throws  \RuntimeException  with a translated message
     */
    public static function storeImage(int $ownerId, array $file): array
    {
        if (empty($file['tmp_name']) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED'));
        }

        if ((int) $file['size'] > 15 * 1024 * 1024) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_TOO_LARGE'));
        }

        $size       = @getimagesize($file['tmp_name']);
        $extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if (!$size || !isset($extensions[$size[2]])) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_TYPE'));
        }

        $dir = JPATH_ROOT . '/' . SeatChart::IMAGE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED'));
        }

        $relative = SeatChart::IMAGE_DIR . '/seatchart-' . $ownerId . '-' . date('YmdHis') . '.' . $extensions[$size[2]];

        if (!File::upload($file['tmp_name'], JPATH_ROOT . '/' . $relative)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED'));
        }

        return [
            'path'   => $relative,
            'url'    => Uri::root(true) . '/' . $relative,
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * Deletes a background image from the seat plan image folder once no chart and no template
     * uses it any more. Images from before 2.6.0 are left alone.
     */
    public static function deleteImageIfUnused(string $relative): void
    {
        if ($relative === '' || strpos($relative, SeatChart::IMAGE_DIR . '/') !== 0 || !SeatChart::isImagePath($relative)) {
            return;
        }

        $db = self::db();
        $db->setQuery('SELECT COUNT(*) FROM #__ticketstation_seatplansettings WHERE background_image = ' . $db->quote($relative));

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $db->setQuery('SELECT COUNT(*) FROM #__ticketstation_seatplantemplates WHERE layout LIKE ' . $db->quote('%' . $db->escape($relative, true) . '%'));

        if ((int) $db->loadResult() > 0) {
            return;
        }

        if (is_file(JPATH_ROOT . '/' . $relative)) {
            File::delete(JPATH_ROOT . '/' . $relative);
        }
    }

    /**
     * The saved chart of an owner as a template layout. Section seats carry the name of their
     * section, so they can be linked to a section of the same name in another event.
     */
    private static function layoutOf(int $ownerId): array
    {
        $settings = SeatChart::settings($ownerId) ?? SeatChart::defaults($ownerId);
        $bg       = SeatChart::background($settings, $ownerId);
        $names    = [$ownerId => ''];
        $colours  = [];

        foreach (SeatplanSettings::getChildColours($ownerId) as $child) {
            $names[(int) $child->ticketid] = $child->ticketname;

            if ($child->background_color . $child->border_color . $child->font_color !== '') {
                $colours[$child->ticketname] = [
                    'background_color' => $child->background_color,
                    'border_color'     => $child->border_color,
                    'font_color'       => $child->font_color,
                ];
            }
        }

        $seats = [];

        foreach (self::seats($ownerId) as $seat) {
            $seats[] = [
                'x'       => $seat['x'],
                'y'       => $seat['y'],
                'w'       => $seat['w'],
                'h'       => $seat['h'],
                'row'     => $seat['row'],
                'num'     => $seat['num'],
                'blocked' => $seat['blocked'],
                'section' => $names[$seat['ticketid']] ?? '',
            ];
        }

        $editor = self::editorSettings($settings, $bg);
        unset($editor['background_url'], $editor['bg_width'], $editor['bg_height']);

        return [
            'settings' => $editor,
            'shapes'   => SeatChart::shapes($settings),
            'colours'  => $colours,
            'seats'    => $seats,
        ];
    }

    /**
     * Stores the saved chart of an owner as a template of its venue: a new one, or over
     * $templateId when that is a template of the same venue. Returns the template id.
     */
    public static function saveTemplate(int $ownerId, string $name, int $templateId = 0): int
    {
        $owner = self::owner($ownerId);
        $name  = mb_substr(trim($name), 0, 100);

        if (!$owner || (int) $owner->venue <= 0 || $name === '') {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_SAVE_FAILED'));
        }

        $db     = self::db();
        $now    = Factory::getDate()->toSql();
        $record = (object) [
            'venue_id' => (int) $owner->venue,
            'name'     => $name,
            'layout'   => json_encode(self::layoutOf($ownerId), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'modified' => $now,
        ];

        if ($templateId > 0 && self::template($templateId, (int) $owner->venue)) {
            $record->id = $templateId;
            $db->updateObject('#__ticketstation_seatplantemplates', $record, 'id');

            return $templateId;
        }

        $record->created = $now;
        $db->insertObject('#__ticketstation_seatplantemplates', $record, 'id');

        return (int) $record->id;
    }

    /**
     * A template row, when it exists (and belongs to $venueId, when given).
     */
    public static function template(int $templateId, int $venueId = 0): ?object
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_seatplantemplates'))
            ->where($db->quoteName('id') . ' = ' . $templateId);

        if ($venueId > 0) {
            $query->where($db->quoteName('venue_id') . ' = ' . $venueId);
        }

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Deletes a template, and its background image when nothing else uses it.
     */
    public static function deleteTemplate(int $templateId): void
    {
        $template = self::template($templateId);

        if (!$template) {
            return;
        }

        $db = self::db();
        $db->setQuery('DELETE FROM #__ticketstation_seatplantemplates WHERE id = ' . $templateId)->execute();

        $layout = json_decode($template->layout, true);
        self::deleteImageIfUnused((string) ($layout['settings']['background_image'] ?? ''));
    }

    /** Identifies an exported template file. */
    private const EXPORT_FORMAT = 'ticketstation-seatplan-template';

    /**
     * A template as a self-contained file for another site: its layout plus the background
     * image, base64-encoded.
     */
    public static function exportTemplate(int $templateId): ?array
    {
        $template = self::template($templateId);
        $layout   = $template ? json_decode($template->layout, true) : null;

        if (!is_array($layout)) {
            return null;
        }

        $image    = null;
        $relative = (string) ($layout['settings']['background_image'] ?? '');

        if ($relative !== '' && SeatChart::isImagePath($relative) && is_file(JPATH_ROOT . '/' . $relative)) {
            $image = [
                'name' => basename($relative),
                'data' => base64_encode(file_get_contents(JPATH_ROOT . '/' . $relative)),
            ];
        }

        unset($layout['settings']['background_image']);

        return [
            'format'   => self::EXPORT_FORMAT,
            'version'  => 1,
            'name'     => $template->name,
            'exported' => Factory::getDate()->toISO8601(),
            'layout'   => $layout,
            'image'    => $image,
        ];
    }

    /**
     * Sends a template as a JSON download and ends the request. Returns false when there is
     * no such template.
     */
    public static function download(int $templateId): bool
    {
        $template = self::template($templateId);
        $export   = $template ? self::exportTemplate($templateId) : null;

        if (!$export) {
            return false;
        }

        $app      = Factory::getApplication();
        $filename = 'seatplan-' . (trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $template->name), '-') ?: 'template') . '.json';

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->sendHeaders();
        echo json_encode($export, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $app->close();

        return true;
    }

    /**
     * Deletes every template of the given venues (when a venue is deleted).
     */
    public static function deleteVenueTemplates(array $venueIds): void
    {
        $venueIds = array_filter(array_map('intval', $venueIds));

        if (!$venueIds) {
            return;
        }

        $db = self::db();
        $db->setQuery('SELECT id FROM #__ticketstation_seatplantemplates WHERE venue_id IN (' . implode(',', $venueIds) . ')');

        foreach ($db->loadColumn() as $id) {
            self::deleteTemplate((int) $id);
        }
    }

    /**
     * Adds a template from an exported file to a venue. Returns the new template id.
     *
     * @throws  \RuntimeException  with a translated message when the file isn't a valid export
     */
    public static function importTemplate(int $venueId, string $json): int
    {
        $data = json_decode($json, true);

        if ($venueId <= 0 || !is_array($data) || ($data['format'] ?? '') !== self::EXPORT_FORMAT || !is_array($data['layout'] ?? null)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_IMPORT_INVALID'));
        }

        $in     = $data['layout'];
        $layout = [
            'settings' => [],
            'shapes'   => SeatChart::cleanShapes((array) ($in['shapes'] ?? [])),
            'colours'  => [],
            'seats'    => [],
        ];

        foreach (['seat_width', 'seat_height', 'bg_offset_x', 'bg_offset_y', 'canvas_width', 'canvas_height', 'grid_size'] as $key) {
            $layout['settings'][$key] = (int) ($in['settings'][$key] ?? 0);
        }

        foreach (['background_color', 'border_color', 'font_color'] as $key) {
            $layout['settings'][$key] = SeatChart::hex($in['settings'][$key] ?? '', '');
        }

        foreach ((array) ($in['colours'] ?? []) as $name => $colours) {
            $layout['colours'][mb_substr((string) $name, 0, 255)] = [
                'background_color' => SeatChart::hex($colours['background_color'] ?? ''),
                'border_color'     => SeatChart::hex($colours['border_color'] ?? ''),
                'font_color'       => SeatChart::hex($colours['font_color'] ?? ''),
            ];
        }

        foreach ((array) ($in['seats'] ?? []) as $seat) {
            if (!is_array($seat)) {
                continue;
            }

            $layout['seats'][] = [
                'x'       => max(0, (int) ($seat['x'] ?? 0)),
                'y'       => max(0, (int) ($seat['y'] ?? 0)),
                'w'       => min(500, max(4, (int) ($seat['w'] ?? 22))),
                'h'       => min(500, max(4, (int) ($seat['h'] ?? 22))),
                'row'     => mb_substr(trim((string) ($seat['row'] ?? '')), 0, 5),
                'num'     => min(99999, max(0, (int) ($seat['num'] ?? 0))),
                'blocked' => !empty($seat['blocked']),
                'section' => mb_substr((string) ($seat['section'] ?? ''), 0, 255),
            ];
        }

        $layout['settings']['background_image'] = '';

        if (!empty($data['image']['data'])) {
            $layout['settings']['background_image'] = self::importImage((string) $data['image']['data'], $venueId);
        }

        $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 100) ?: Text::_('COM_TICKETSTATION_SEATEDITOR_IMPORTED');
        $now  = Factory::getDate()->toSql();

        $record = (object) [
            'venue_id' => $venueId,
            'name'     => $name,
            'layout'   => json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created'  => $now,
            'modified' => $now,
        ];

        self::db()->insertObject('#__ticketstation_seatplantemplates', $record, 'id');

        return (int) $record->id;
    }

    /**
     * Stores the background image of an imported template, after checking that it really is
     * a PNG, JPEG or WebP image. Returns its path relative to the site root.
     */
    private static function importImage(string $base64, int $venueId): string
    {
        $bytes = base64_decode($base64, true);

        if ($bytes === false || strlen($bytes) > 15 * 1024 * 1024) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_IMPORT_INVALID'));
        }

        $size       = @getimagesizefromstring($bytes);
        $extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if (!$size || !isset($extensions[$size[2]])) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_TYPE'));
        }

        $dir = JPATH_ROOT . '/' . SeatChart::IMAGE_DIR;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED'));
        }

        $relative = SeatChart::IMAGE_DIR . '/template-' . $venueId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extensions[$size[2]];

        if (file_put_contents(JPATH_ROOT . '/' . $relative, $bytes) === false) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED'));
        }

        return $relative;
    }

    /**
     * Replaces the chart of an owner with a template of its venue. Section seats are linked to
     * the owner's child ticket of the same name; without one they become free seats.
     *
     * @throws  \RuntimeException  when a seat is sold, or the template isn't of this venue
     */
    public static function applyTemplate(int $ownerId, int $templateId): void
    {
        $owner    = self::owner($ownerId);
        $template = $owner ? self::template($templateId, (int) $owner->venue) : null;
        $layout   = $template ? json_decode($template->layout, true) : null;

        if (!is_array($layout)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_NOT_FOUND'));
        }

        $sections = [];

        foreach (SeatplanSettings::getChildColours($ownerId) as $child) {
            $sections[mb_strtolower($child->ticketname)] = (int) $child->ticketid;
        }

        $colours = [];

        foreach ((array) ($layout['colours'] ?? []) as $name => $values) {
            if (isset($sections[mb_strtolower((string) $name)])) {
                $colours[$sections[mb_strtolower((string) $name)]] = (array) $values;
            }
        }

        $seats = [];

        foreach ((array) ($layout['seats'] ?? []) as $seat) {
            $section         = mb_strtolower((string) ($seat['section'] ?? ''));
            $seat['ticketid'] = $sections[$section] ?? $ownerId;
            $seat['id']       = 0;
            $seats[]          = $seat;
        }

        self::replace($ownerId, (array) ($layout['settings'] ?? []), (array) ($layout['shapes'] ?? []), $seats, $colours);
    }

    /**
     * Replaces the chart of an owner with the chart of another ticket: its seats (section seats
     * become free seats, as the sections belong to another event), shapes, background and canvas.
     * The owner's seat size and colours stay.
     *
     * @throws  \RuntimeException  when a seat is sold, or the source has no chart
     */
    public static function copyFrom(int $ownerId, int $sourceId): void
    {
        if ($sourceId === $ownerId || !self::owner($sourceId)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_FAILED_COPY_FROM_SOURCE'));
        }

        $source   = self::layoutOf($sourceId);
        $settings = self::editorSettings(SeatChart::settings($ownerId) ?? SeatChart::defaults($ownerId), null);

        foreach (['background_image', 'bg_offset_x', 'bg_offset_y', 'canvas_width', 'canvas_height'] as $key) {
            $settings[$key] = $source['settings'][$key];
        }

        $seats = array_map(fn ($seat) => ['id' => 0, 'ticketid' => $ownerId] + $seat, $source['seats']);

        self::replace($ownerId, $settings, $source['shapes'], $seats, []);
    }

    /**
     * Deletes every seat of a chart and stores a new layout, as long as no seat is sold.
     */
    private static function replace(int $ownerId, array $settings, array $shapes, array $seats, array $colours): void
    {
        if (self::hasLockedSeats($ownerId)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATS_BOOKED_NO_DELETE'));
        }

        $db = self::db();
        $db->setQuery('SELECT id FROM #__ticketstation_seatplancoords WHERE (ticketid = ' . $ownerId . ' OR parent = ' . $ownerId . ') AND ' . self::UNLOCKED);
        $deleted = $db->loadColumn();

        ## Colours of sections the layout doesn't mention stay as they are.
        foreach (SeatplanSettings::getChildColours($ownerId) as $child) {
            $colours[(int) $child->ticketid] ??= [
                'background_color' => $child->background_color,
                'border_color'     => $child->border_color,
                'font_color'       => $child->font_color,
            ];
        }

        $warnings = self::save($ownerId, [
            'settings' => $settings,
            'shapes'   => $shapes,
            'colours'  => $colours,
            'seats'    => $seats,
            'deleted'  => $deleted,
        ]);

        if ($warnings) {
            throw new \RuntimeException(implode(' ', $warnings));
        }
    }
}
