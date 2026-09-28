/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * The seat plan editor. It works on a local copy of the chart (the layout from
 * SeatplanLayout::forEditor()) and saves everything in one request; the server answers with
 * the fresh layout. Seats and shapes are drawn in canvas pixels inside a scaled canvas.
 *
 * Sold seats (and seats in a basket) are "locked": they can be moved and resized, but keep
 * their section, row, number and status and cannot be deleted. The server enforces this too.
 *
 * Every user-facing string goes through T(key), which reads COM_TICKETSTATION_SE_<key>; the
 * view registers all keys it finds in this file, so keep the key a literal.
 */
(function () {
    'use strict';

    var root = document.getElementById('ts-seateditor');

    if (!root || !window.Joomla) {
        return;
    }

    var opts = Joomla.getOptions('com_ticketstation.seateditor') || {};
    var PAD = 24;
    var PREFS_KEY = 'ts-seateditor-prefs';

    function T(key) {
        var text = Joomla.Text._('COM_TICKETSTATION_SE_' + key, key);

        for (var i = 1; i < arguments.length; i++) {
            text = text.replace('%s', arguments[i]);
        }

        return text;
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function int(value, fallback) {
        var n = parseInt(value, 10);

        return isNaN(n) ? fallback : n;
    }

    function hex(value, fallback) {
        value = String(value || '').replace('#', '').trim();

        return /^[0-9a-f]{6}$/i.test(value) ? value.toLowerCase() : fallback;
    }

    function fontSize(w, h) {
        return Math.max(7, Math.round(Math.min(w, h) * 0.41));
    }

    /** The next row name: A → B, Z → AA, 9 → 10, B3 → B4. */
    function nextRow(row) {
        row = String(row || '');

        if (/^\d+$/.test(row)) {
            return String(parseInt(row, 10) + 1);
        }

        var digits = row.match(/^(.*?)(\d+)$/);

        if (digits) {
            return (digits[1] + (parseInt(digits[2], 10) + 1)).slice(0, 5);
        }

        if (/^[a-z]+$/i.test(row)) {
            var upper = row === row.toUpperCase();
            var chars = row.toUpperCase().split('');
            var i = chars.length - 1;

            while (i >= 0) {
                if (chars[i] !== 'Z') {
                    chars[i] = String.fromCharCode(chars[i].charCodeAt(0) + 1);
                    break;
                }

                chars[i] = 'A';
                i--;
            }

            var result = (i < 0 ? 'A' : '') + chars.join('');

            return (upper ? result : result.toLowerCase()).slice(0, 5);
        }

        return row;
    }

    // ---------------------------------------------------------------- preferences

    var prefs = (function () {
        var stored = {};

        try {
            stored = JSON.parse(window.localStorage.getItem(PREFS_KEY) || '{}') || {};
        } catch (e) {
            stored = {};
        }

        return Object.assign({
            snap: true,
            grid: true,
            bgOpacity: 0.7,
            tab: 'props',
            row: {count: 10, gap: 4, row: 'A', start: 1, step: 1, dir: 'h', curve: 0},
            block: {rows: 5, cols: 10, gapX: 4, gapY: 8, row: 'A', start: 1, step: 1},
            shape: {label: '', color: 'dddddd', text_color: '000000', font: 14}
        }, stored);
    })();

    function savePrefs() {
        try {
            window.localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
        } catch (e) {
            // Only a convenience; the editor works without it.
        }
    }

    // ---------------------------------------------------------------- state

    var L;              // the layout from the server: owner, kinds, templates, sources
    var S;              // the editable chart: settings, shapes, seats, colours, deleted
    var ownerId;
    var uidSeq = 1;
    var sel = new Set();
    var byKey = new Map();
    var elByKey = new Map();
    var tool = 'select';
    var zoom = 1;
    var dirty = false;
    var busy = false;
    var undoStack = [];
    var redoStack = [];
    var spaceDown = false;
    var cursor = null;
    var lastNudge = 0;
    var kindParam = {row: 0, block: 0};

    function newSeat(values) {
        var uid = uidSeq++;

        return Object.assign({
            id: 0, x: 0, y: 0, w: S.settings.seat_width, h: S.settings.seat_height, row: '', num: 0,
            ticketid: ownerId, blocked: false, type: 1, locked: false, ordercode: '', scanned: false
        }, values, {uid: uid, key: 's' + uid});
    }

    function newShape(values) {
        var uid = uidSeq++;

        return Object.assign({kind: 'rect', x: 0, y: 0, w: 120, h: 40, label: '', color: 'dddddd', text_color: '000000', font: 14}, values, {uid: uid, key: 'h' + uid});
    }

    function load(layout) {
        L = layout;
        ownerId = layout.owner.id;
        S = {
            settings: Object.assign({}, layout.settings),
            shapes: [],
            seats: [],
            colours: {},
            deleted: []
        };

        layout.seats.forEach(function (seat) {
            S.seats.push(newSeat(seat));
        });
        layout.shapes.forEach(function (shape) {
            S.shapes.push(newShape(shape));
        });
        layout.kinds.forEach(function (kind) {
            S.colours[kind.id] = Object.assign({}, kind.colours);
        });

        ['row', 'block'].forEach(function (name) {
            if (!kind(kindParam[name])) {
                kindParam[name] = ownerId;
            }
        });

        sel.clear();
        undoStack = [];
        redoStack = [];
        setDirty(false);
        renderAll();
    }

    function kind(id) {
        return L.kinds.find(function (k) {
            return k.id === id;
        }) || null;
    }

    function kindLabel(k) {
        if (k.free) {
            return T('KIND_FREE');
        }

        var label = T('KIND_SECTION', k.name);

        if (k.category) {
            label += ' – ' + T('KIND_IS_CATEGORY');
        }

        if (!k.published) {
            label += ' (' + T('UNPUBLISHED') + ')';
        }

        return label;
    }

    function snapshot() {
        return JSON.stringify({settings: S.settings, shapes: S.shapes, seats: S.seats, colours: S.colours, deleted: S.deleted});
    }

    function pushUndo(before) {
        undoStack.push(before);

        if (undoStack.length > 200) {
            undoStack.shift();
        }

        redoStack = [];
    }

    /** Runs a change to the chart as one undoable step. */
    function change(fn) {
        var before = snapshot();

        fn();
        pushUndo(before);
        setDirty(true);
        renderAll();
    }

    function restore(json) {
        var data = JSON.parse(json);

        S.settings = data.settings;
        S.shapes = data.shapes;
        S.seats = data.seats;
        S.colours = data.colours;
        S.deleted = data.deleted;
        setDirty(true);
        renderAll();
    }

    function undo() {
        if (undoStack.length) {
            redoStack.push(snapshot());
            restore(undoStack.pop());
        }
    }

    function redo() {
        if (redoStack.length) {
            undoStack.push(snapshot());
            restore(redoStack.pop());
        }
    }

    function setDirty(value) {
        dirty = value;
        root.classList.toggle('is-dirty', value);
    }

    function selected() {
        var list = [];

        sel.forEach(function (key) {
            if (byKey.has(key)) {
                list.push(byKey.get(key));
            }
        });

        return list;
    }

    function selectedSeats() {
        return selected().filter(function (o) {
            return o.key[0] === 's';
        });
    }

    function selectedShapes() {
        return selected().filter(function (o) {
            return o.key[0] === 'h';
        });
    }

    // ---------------------------------------------------------------- geometry

    function canvasSize() {
        var s = S.settings;
        var w = int(s.canvas_width, 0);
        var h = int(s.canvas_height, 0);
        var fixed = w > 0 && h > 0;

        if (!fixed) {
            if (s.background_url) {
                w = int(s.bg_offset_x, 0) + int(s.bg_width, 0);
                h = int(s.bg_offset_y, 0) + int(s.bg_height, 0);
            } else {
                w = 750;
                h = 850;
            }

            S.seats.concat(S.shapes).forEach(function (o) {
                w = Math.max(w, o.x + o.w + 10);
                h = Math.max(h, o.y + o.h + 10);
            });
        }

        return {w: Math.max(50, w), h: Math.max(50, h), fixed: fixed};
    }

    function bounds(items) {
        var box = {x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity};

        items.forEach(function (o) {
            box.x1 = Math.min(box.x1, o.x);
            box.y1 = Math.min(box.y1, o.y);
            box.x2 = Math.max(box.x2, o.x + o.w);
            box.y2 = Math.max(box.y2, o.y + o.h);
        });

        return box;
    }

    function snapOn(event) {
        return prefs.snap && !(event && event.altKey);
    }

    function grid() {
        return Math.max(1, int(S.settings.grid_size, 10));
    }

    function snapValue(value, event) {
        return snapOn(event) ? Math.round(value / grid()) * grid() : Math.round(value);
    }

    function toCanvas(event) {
        var rect = els.canvas.getBoundingClientRect();

        return {x: (event.clientX - rect.left) / zoom, y: (event.clientY - rect.top) / zoom};
    }

    // ---------------------------------------------------------------- colours

    function seatColours(seat) {
        if (seat.locked) {
            return seat.scanned ? {bg: '198d02', bd: '000000', fg: 'ffffff'} : {bg: 'e03131', bd: '000000', fg: 'ffffff'};
        }

        if (seat.blocked) {
            return {bg: '888888', bd: '000000', fg: 'ffffff'};
        }

        var own = S.colours[ownerId] || {};
        var mine = S.colours[seat.ticketid] || {};

        return {
            bg: mine.background_color || own.background_color || 'e1fdda',
            bd: mine.border_color || own.border_color || '198d02',
            fg: mine.font_color || own.font_color || '000000'
        };
    }

    function seatLabel(seat) {
        if (seat.type !== 1) {
            var k = kind(seat.ticketid);

            return k ? k.name : '';
        }

        return seat.row + seat.num;
    }

    // ---------------------------------------------------------------- icons

    var ICONS = {
        select: '<path d="M5 2.5l11 7-5 1.2-2.6 4.8z"/>',
        row: '<rect x="1" y="8" width="3.6" height="4" rx=".7"/><rect x="5.8" y="8" width="3.6" height="4" rx=".7"/><rect x="10.6" y="8" width="3.6" height="4" rx=".7"/><rect x="15.4" y="8" width="3.6" height="4" rx=".7"/>',
        block: '<rect x="2" y="3" width="4" height="3.5" rx=".6"/><rect x="8" y="3" width="4" height="3.5" rx=".6"/><rect x="14" y="3" width="4" height="3.5" rx=".6"/><rect x="2" y="8.3" width="4" height="3.5" rx=".6"/><rect x="8" y="8.3" width="4" height="3.5" rx=".6"/><rect x="14" y="8.3" width="4" height="3.5" rx=".6"/><rect x="2" y="13.6" width="4" height="3.5" rx=".6"/><rect x="8" y="13.6" width="4" height="3.5" rx=".6"/><rect x="14" y="13.6" width="4" height="3.5" rx=".6"/>',
        rect: '<rect x="2.5" y="5" width="15" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        text: '<path d="M4 3.5h12v3.2h-1.7V5.2h-3.4v9.6h2v1.7H7.1v-1.7h2V5.2H5.7v1.5H4z"/>',
        undo: '<path d="M7 4.5L3 8.5l4 4M3.6 8.5H12a4.5 4.5 0 010 9H8.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        redo: '<path d="M13 4.5l4 4-4 4M16.4 8.5H8a4.5 4.5 0 000 9h3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        magnet: '<path d="M4.5 3v7.5a5.5 5.5 0 0011 0V3h-3.4v7.5a2.1 2.1 0 01-4.2 0V3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M4.5 6h3.4M12.1 6h3.4" stroke="currentColor" stroke-width="1.5"/>',
        grid: '<path d="M2 7h16M2 13h16M7 2v16M13 2v16" fill="none" stroke="currentColor" stroke-width="1.4"/>',
        minus: '<path d="M4 10h12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
        plus: '<path d="M4 10h12M10 4v12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
        fit: '<path d="M3 8V3h5M12 3h5v5M17 12v5h-5M8 17H3v-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
        save: '<path d="M3 3h11l3 3v11H3zM6.5 3v4.5h6V3M6 17v-5.5h8V17" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
        trash: '<path d="M3.5 5.5h13M8 5.5V3.5h4v2M5 5.5l1 11.5h8l1-11.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
        copy: '<rect x="6.5" y="6.5" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4 13.5H3.5V3.5h10V4" fill="none" stroke="currentColor" stroke-width="1.5"/>',
        help: '<circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M7.8 7.8a2.3 2.3 0 114 1.6c-.9.7-1.8 1.1-1.8 2.3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14.3" r="1"/>'
    };

    function icon(name) {
        return '<svg class="ts-se-icon" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor">' + ICONS[name] + '</svg>';
    }

    // ---------------------------------------------------------------- shell

    function button(attrs, content, title) {
        return '<button type="button" class="btn btn-sm ts-se-btn" ' + attrs + (title ? ' title="' + esc(title) + '" aria-label="' + esc(title) + '"' : '') + '>' + content + '</button>';
    }

    root.innerHTML =
        '<div class="ts-se__bar">' +
            '<div class="ts-se__group" role="group">' +
                button('data-tool="select"', icon('select'), T('TOOL_SELECT') + ' (V)') +
                button('data-tool="row"', icon('row'), T('TOOL_ROW') + ' (R)') +
                button('data-tool="block"', icon('block'), T('TOOL_BLOCK') + ' (B)') +
                button('data-tool="rect"', icon('rect'), T('TOOL_RECT') + ' (S)') +
                button('data-tool="text"', icon('text'), T('TOOL_TEXT') + ' (T)') +
            '</div>' +
            '<div class="ts-se__group" role="group">' +
                button('data-action="undo"', icon('undo'), T('UNDO') + ' (Ctrl+Z)') +
                button('data-action="redo"', icon('redo'), T('REDO') + ' (Ctrl+Y)') +
            '</div>' +
            '<div class="ts-se__group" role="group">' +
                button('data-action="toggle-snap"', icon('magnet'), T('SNAP') + ' (G)') +
                button('data-action="toggle-grid"', icon('grid'), T('SHOW_GRID')) +
            '</div>' +
            '<div class="ts-se__group" role="group">' +
                button('data-action="zoom-out"', icon('minus'), T('ZOOM_OUT') + ' (−)') +
                '<span class="ts-se__zoom" data-zoom-label>100%</span>' +
                button('data-action="zoom-in"', icon('plus'), T('ZOOM_IN') + ' (+)') +
                button('data-action="zoom-fit"', icon('fit'), T('ZOOM_FIT') + ' (0)') +
            '</div>' +
            '<div class="ts-se__spacer"></div>' +
            button('data-action="help"', icon('help'), T('SHORTCUTS')) +
            '<span class="ts-se__unsaved">' + esc(T('UNSAVED')) + '</span>' +
            '<button type="button" class="btn btn-success btn-sm ts-se__save" data-action="save">' + icon('save') + ' ' + esc(T('SAVE')) + '</button>' +
        '</div>' +
        '<div class="ts-se__main">' +
            '<div class="ts-se__viewport" tabindex="0">' +
                '<div class="ts-se__stage">' +
                    '<div class="ts-se__canvas">' +
                        '<img class="ts-se__bg" alt="" draggable="false" hidden>' +
                        '<div class="ts-se__grid"></div>' +
                        '<div class="ts-se__items"></div>' +
                        '<div class="ts-se__ghost"></div>' +
                        '<div class="ts-se__marquee" hidden></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<aside class="ts-se__panel">' +
                '<div class="ts-se__tabs" role="tablist">' +
                    '<button type="button" role="tab" data-tab="props">' + esc(T('TAB_PROPS')) + '</button>' +
                    '<button type="button" role="tab" data-tab="view">' + esc(T('TAB_VIEW')) + '</button>' +
                    '<button type="button" role="tab" data-tab="templates">' + esc(T('TAB_TEMPLATES')) + '</button>' +
                '</div>' +
                '<div class="ts-se__panelbody"></div>' +
            '</aside>' +
        '</div>' +
        '<div class="ts-se__status"></div>' +
        '<div class="ts-se__toasts" aria-live="polite"></div>' +
        '<dialog class="ts-se__dialog"></dialog>';

    var els = {
        bar: root.querySelector('.ts-se__bar'),
        viewport: root.querySelector('.ts-se__viewport'),
        stage: root.querySelector('.ts-se__stage'),
        canvas: root.querySelector('.ts-se__canvas'),
        bg: root.querySelector('.ts-se__bg'),
        grid: root.querySelector('.ts-se__grid'),
        items: root.querySelector('.ts-se__items'),
        ghost: root.querySelector('.ts-se__ghost'),
        marquee: root.querySelector('.ts-se__marquee'),
        panel: root.querySelector('.ts-se__panelbody'),
        tabs: root.querySelector('.ts-se__tabs'),
        status: root.querySelector('.ts-se__status'),
        toasts: root.querySelector('.ts-se__toasts'),
        dialog: root.querySelector('.ts-se__dialog'),
        zoom: root.querySelector('[data-zoom-label]')
    };

    // ---------------------------------------------------------------- rendering

    function renderAll() {
        renderCanvas();
        renderItems();
        renderPanel();
        renderStatus();
        renderBar();
    }

    function renderCanvas() {
        var size = canvasSize();
        var s = S.settings;
        var extent = {w: size.w, h: size.h};

        S.seats.concat(S.shapes).forEach(function (o) {
            extent.w = Math.max(extent.w, o.x + o.w + 20);
            extent.h = Math.max(extent.h, o.y + o.h + 20);
        });

        els.canvas.style.width = size.w + 'px';
        els.canvas.style.height = size.h + 'px';
        els.canvas.style.transform = 'scale(' + zoom + ')';
        els.stage.style.width = (extent.w * zoom + PAD * 2) + 'px';
        els.stage.style.height = (extent.h * zoom + PAD * 2) + 'px';

        if (s.background_url) {
            els.bg.hidden = false;

            if (els.bg.getAttribute('src') !== s.background_url) {
                els.bg.setAttribute('src', s.background_url);
            }

            els.bg.style.left = int(s.bg_offset_x, 0) + 'px';
            els.bg.style.top = int(s.bg_offset_y, 0) + 'px';
            els.bg.style.width = int(s.bg_width, 0) + 'px';
            els.bg.style.height = int(s.bg_height, 0) + 'px';
            els.bg.style.opacity = prefs.bgOpacity;
        } else {
            els.bg.hidden = true;
            els.bg.removeAttribute('src');
        }

        els.grid.hidden = !prefs.grid;
        els.grid.style.backgroundSize = grid() + 'px ' + grid() + 'px';
        els.zoom.textContent = Math.round(zoom * 100) + '%';
    }

    function renderItems() {
        var html = '';

        byKey = new Map();

        S.shapes.forEach(function (sh) {
            byKey.set(sh.key, sh);
            html += '<div class="ts-se-shape ts-se-shape--' + sh.kind + (sel.has(sh.key) ? ' is-selected' : '') + '" data-key="' + sh.key + '" style="left:' + sh.x + 'px;top:' + sh.y + 'px;width:' + sh.w + 'px;height:' + sh.h + 'px;font-size:' + sh.font + 'px;color:#' + sh.text_color + ';' + (sh.kind === 'rect' ? 'background:#' + sh.color + ';' : '') + '">' + esc(sh.label) + '</div>';
        });

        S.seats.forEach(function (seat) {
            var c = seatColours(seat);

            byKey.set(seat.key, seat);
            html += '<div class="ts-se-seat' + (sel.has(seat.key) ? ' is-selected' : '') + (seat.locked ? ' is-locked' : '') + '" data-key="' + seat.key + '" style="left:' + seat.x + 'px;top:' + seat.y + 'px;width:' + seat.w + 'px;height:' + seat.h + 'px;background:#' + c.bg + ';border-color:#' + c.bd + ';color:#' + c.fg + ';font-size:' + fontSize(seat.w, seat.h) + 'px">' + esc(seatLabel(seat)) + '</div>';
        });

        els.items.innerHTML = html;
        elByKey = new Map();

        els.items.querySelectorAll('[data-key]').forEach(function (el) {
            elByKey.set(el.getAttribute('data-key'), el);
        });

        sel.forEach(function (key) {
            if (!byKey.has(key)) {
                sel.delete(key);
            }
        });
    }

    function renderSelection() {
        elByKey.forEach(function (el, key) {
            el.classList.toggle('is-selected', sel.has(key));
        });
        renderPanel();
        renderStatus();
    }

    function renderBar() {
        els.bar.querySelectorAll('[data-tool]').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-tool') === tool);
            b.setAttribute('aria-pressed', b.getAttribute('data-tool') === tool ? 'true' : 'false');
        });
        els.bar.querySelector('[data-action="toggle-snap"]').classList.toggle('active', prefs.snap);
        els.bar.querySelector('[data-action="toggle-grid"]').classList.toggle('active', prefs.grid);
        els.bar.querySelector('[data-action="undo"]').disabled = !undoStack.length;
        els.bar.querySelector('[data-action="redo"]').disabled = !redoStack.length;
        els.bar.querySelector('[data-action="save"]').disabled = busy;
        els.viewport.setAttribute('data-tool', tool);
    }

    function renderStatus() {
        var free = 0, blocked = 0, sold = 0, used = {};

        S.seats.forEach(function (seat) {
            if (seat.locked) {
                sold++;
            } else if (seat.blocked) {
                blocked++;
            } else {
                free++;
            }

            used[seat.ticketid] = (used[seat.ticketid] || 0) + 1;
        });

        var legend = L.kinds.filter(function (k) {
            return used[k.id];
        }).map(function (k) {
            var c = seatColours({ticketid: k.id, locked: false, blocked: false});

            return '<span class="ts-se-legend"><i style="background:#' + c.bg + ';border-color:#' + c.bd + '"></i>' + esc(k.free ? T('KIND_FREE_SHORT') : k.name) + ' <small>' + used[k.id] + '</small></span>';
        }).join('');

        legend += '<span class="ts-se-legend"><i style="background:#888888"></i>' + esc(T('BLOCKED')) + ' <small>' + blocked + '</small></span>';
        legend += '<span class="ts-se-legend"><i style="background:#e03131"></i>' + esc(T('SOLD')) + ' <small>' + sold + '</small></span>';

        els.status.innerHTML =
            '<span><strong>' + esc(T('STATUS_SEATS', S.seats.length)) + '</strong> · ' + esc(T('STATUS_FREE', free)) + '</span>' +
            '<span class="ts-se-legends">' + legend + '</span>' +
            '<span class="ts-se__spacer"></span>' +
            (sel.size ? '<span>' + esc(T('STATUS_SELECTED', sel.size)) + '</span>' : '') +
            '<span class="ts-se__cursor">' + (cursor ? 'x ' + Math.round(cursor.x) + ' · y ' + Math.round(cursor.y) : '') + '</span>';
    }

    // ---------------------------------------------------------------- panel

    function field(label, control, hint) {
        return '<label class="ts-se-field"><span class="ts-se-field__label">' + esc(label) + '</span>' + control + (hint ? '<small class="ts-se-field__hint">' + hint + '</small>' : '') + '</label>';
    }

    function numberInput(attrs, value, extra) {
        return '<input type="number" class="form-control form-control-sm" ' + attrs + ' value="' + esc(value) + '"' + (extra || '') + '>';
    }

    function textInput(attrs, value, extra) {
        return '<input type="text" class="form-control form-control-sm" ' + attrs + ' value="' + esc(value) + '"' + (extra || '') + '>';
    }

    function selectInput(attrs, options, value, extra) {
        return '<select class="form-select form-select-sm" ' + attrs + (extra || '') + '>' + options.map(function (o) {
            return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(value) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
        }).join('') + '</select>';
    }

    function kindOptions() {
        return L.kinds.map(function (k) {
            return [k.id, kindLabel(k)];
        });
    }

    function categoryNote(id) {
        var k = kind(int(id, 0));

        return k && k.category ? '<p class="ts-se-note ts-se-note--warn">' + esc(T('KIND_CATEGORY_NOTE', k.name)) + '</p>' : '';
    }

    function section(title, body) {
        return '<section class="ts-se-section"><h3 class="ts-se-section__title">' + esc(title) + '</h3>' + body + '</section>';
    }

    function renderPanel() {
        els.tabs.querySelectorAll('[data-tab]').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-tab') === prefs.tab);
            b.setAttribute('aria-selected', b.getAttribute('data-tab') === prefs.tab ? 'true' : 'false');
        });

        var html;

        if (prefs.tab === 'view') {
            html = viewPanel();
        } else if (prefs.tab === 'templates') {
            html = templatesPanel();
        } else {
            html = propsPanel();
        }

        els.panel.innerHTML = html;
    }

    function propsPanel() {
        var html = '';

        if (L.isNew) {
            html += '<p class="ts-se-note">' + esc(T('NEW_CHART')) + '</p>';
        }

        if (tool === 'row') {
            return html + rowForm();
        }

        if (tool === 'block') {
            return html + blockForm();
        }

        if (tool === 'rect' || tool === 'text') {
            return html + shapeForm();
        }

        var seats = selectedSeats();
        var shapes = selectedShapes();

        if (!seats.length && !shapes.length) {
            return html + emptyPanel();
        }

        if (!seats.length && shapes.length === 1) {
            return html + shapePanel(shapes[0]);
        }

        return html + selectionPanel(seats, shapes);
    }

    function emptyPanel() {
        var categories = L.kinds.filter(function (k) {
            return k.category;
        }).map(function (k) {
            return k.name;
        });

        return section(T('GETTING_STARTED'),
            '<p>' + esc(T('HINT_START')) + '</p>' +
            '<ul class="ts-se-list">' +
                '<li>' + esc(T('HINT_ROW')) + '</li>' +
                '<li>' + esc(T('HINT_SELECT')) + '</li>' +
                '<li>' + esc(T('HINT_MOVE')) + '</li>' +
            '</ul>') +
            section(T('KINDS_TITLE'),
                '<p class="ts-se-small">' + esc(T('KINDS_HINT')) + '</p>' +
                (categories.length
                    ? '<p class="ts-se-small"><strong>' + esc(T('PRICE_CATEGORIES')) + ':</strong> ' + esc(categories.join(', ')) + '</p>'
                    : '<p class="ts-se-small">' + esc(T('NO_PRICE_CATEGORIES', L.owner.name)) + '</p>'));
    }

    function rowForm() {
        var p = prefs.row;

        return section(T('TOOL_ROW'),
            '<p class="ts-se-small">' + esc(T('ROW_HINT')) + '</p>' +
            '<div class="ts-se-grid2">' +
                field(T('ROW_NAME'), textInput('data-param="row.row"', p.row, ' maxlength="5"')) +
                field(T('SEAT_COUNT'), numberInput('data-param="row.count" min="1" max="200"', p.count)) +
                field(T('START_NUMBER'), numberInput('data-param="row.start" min="0"', p.start)) +
                field(T('NUMBERING'), selectInput('data-param="row.step"', numberingOptions(), p.step)) +
                field(T('GAP'), numberInput('data-param="row.gap" min="0" max="200"', p.gap)) +
                field(T('DIRECTION'), selectInput('data-param="row.dir"', [['h', T('HORIZONTAL')], ['v', T('VERTICAL')]], p.dir)) +
            '</div>' +
            field(T('CURVE'), numberInput('data-param="row.curve" min="-500" max="500"', p.curve), esc(T('CURVE_HINT'))) +
            field(T('KIND'), selectInput('data-param="kind.row"', kindOptions(), kindParam.row)) +
            categoryNote(kindParam.row) +
            '<p class="ts-se-small">' + esc(T('SEAT_SIZE_FROM_VIEW', S.settings.seat_width, S.settings.seat_height)) + '</p>');
    }

    function blockForm() {
        var p = prefs.block;

        return section(T('TOOL_BLOCK'),
            '<p class="ts-se-small">' + esc(T('BLOCK_HINT')) + '</p>' +
            '<div class="ts-se-grid2">' +
                field(T('ROWS'), numberInput('data-param="block.rows" min="1" max="100"', p.rows)) +
                field(T('SEATS_PER_ROW'), numberInput('data-param="block.cols" min="1" max="200"', p.cols)) +
                field(T('FIRST_ROW'), textInput('data-param="block.row"', p.row, ' maxlength="5"')) +
                field(T('START_NUMBER'), numberInput('data-param="block.start" min="0"', p.start)) +
                field(T('NUMBERING'), selectInput('data-param="block.step"', numberingOptions(), p.step)) +
                field(T('GAP'), numberInput('data-param="block.gapX" min="0" max="200"', p.gapX)) +
                field(T('ROW_GAP'), numberInput('data-param="block.gapY" min="0" max="400"', p.gapY)) +
            '</div>' +
            field(T('KIND'), selectInput('data-param="kind.block"', kindOptions(), kindParam.block)) +
            categoryNote(kindParam.block) +
            '<p class="ts-se-small">' + esc(T('SEAT_SIZE_FROM_VIEW', S.settings.seat_width, S.settings.seat_height)) + '</p>');
    }

    function numberingOptions() {
        return [[1, T('NUMBERING_UP')], [-1, T('NUMBERING_DOWN')], [2, T('NUMBERING_UP_2')], [-2, T('NUMBERING_DOWN_2')]];
    }

    function shapeForm() {
        var p = prefs.shape;

        return section(tool === 'text' ? T('TOOL_TEXT') : T('TOOL_RECT'),
            '<p class="ts-se-small">' + esc(T('SHAPE_HINT')) + '</p>' +
            field(T('LABEL'), textInput('data-param="shape.label"', p.label, ' maxlength="100" placeholder="' + esc(tool === 'text' ? T('TEXT_DEFAULT') : T('STAGE')) + '"')) +
            '<div class="ts-se-grid2">' +
                (tool === 'rect' ? field(T('FILL_COLOUR'), '<input type="color" class="form-control form-control-sm form-control-color" data-param="shape.color" value="#' + p.color + '">') : '') +
                field(T('TEXT_COLOUR'), '<input type="color" class="form-control form-control-sm form-control-color" data-param="shape.text_color" value="#' + p.text_color + '">') +
                field(T('FONT_SIZE'), numberInput('data-param="shape.font" min="6" max="96"', p.font)) +
            '</div>');
    }

    function shapePanel(sh) {
        return section(sh.kind === 'text' ? T('TOOL_TEXT') : T('TOOL_RECT'),
            field(T('LABEL'), textInput('data-shape="label"', sh.label, ' maxlength="100"')) +
            '<div class="ts-se-grid2">' +
                field(T('KIND_SHAPE'), selectInput('data-shape="kind"', [['rect', T('TOOL_RECT')], ['text', T('TOOL_TEXT')]], sh.kind)) +
                field(T('FONT_SIZE'), numberInput('data-shape="font" min="6" max="96"', sh.font)) +
                (sh.kind === 'rect' ? field(T('FILL_COLOUR'), '<input type="color" class="form-control form-control-sm form-control-color" data-shape="color" value="#' + sh.color + '">') : '') +
                field(T('TEXT_COLOUR'), '<input type="color" class="form-control form-control-sm form-control-color" data-shape="text_color" value="#' + sh.text_color + '">') +
                field('X', numberInput('data-shape="x" min="0"', sh.x)) +
                field('Y', numberInput('data-shape="y" min="0"', sh.y)) +
                field(T('WIDTH'), numberInput('data-shape="w" min="4"', sh.w)) +
                field(T('HEIGHT'), numberInput('data-shape="h" min="4"', sh.h)) +
            '</div>') +
            actionsSection(1);
    }

    function selectionPanel(seats, shapes) {
        var locked = seats.filter(function (s) {
            return s.locked;
        });
        var html = '<p class="ts-se-selcount"><strong>' + esc(T('SEL_SEATS', seats.length)) + '</strong>' +
            (shapes.length ? ' · ' + esc(T('SEL_SHAPES', shapes.length)) : '') + '</p>';

        if (locked.length) {
            html += '<p class="ts-se-note ts-se-note--lock">' + esc(seats.length === 1
                ? T('LOCKED_ONE', locked[0].ordercode || '–')
                : T('LOCKED_MANY', locked.length)) + '</p>';
        }

        if (seats.length === 1) {
            var seat = seats[0];
            var dis = seat.locked ? ' disabled' : '';

            html += section(T('SEAT'),
                '<div class="ts-se-grid2">' +
                    field(T('ROW_NAME'), textInput('data-seat="row"', seat.row, ' maxlength="5"' + dis)) +
                    field(T('NUMBER'), numberInput('data-seat="num" min="0"', seat.num, dis)) +
                '</div>' +
                field(T('KIND'), selectInput('data-seat="ticketid"', kindOptions(), seat.ticketid, dis)) +
                (seat.locked ? '' : categoryNote(seat.ticketid)) +
                field(T('STATUS'), selectInput('data-seat="blocked"', [[0, T('FREE')], [1, T('BLOCKED')]], seat.blocked ? 1 : 0, dis)) +
                '<div class="ts-se-grid2">' +
                    field('X', numberInput('data-seat="x" min="0"', seat.x)) +
                    field('Y', numberInput('data-seat="y" min="0"', seat.y)) +
                    field(T('WIDTH'), numberInput('data-seat="w" min="4" max="500"', seat.w)) +
                    field(T('HEIGHT'), numberInput('data-seat="h" min="4" max="500"', seat.h)) +
                '</div>');
        } else if (seats.length > 1) {
            var first = seats[0];
            var sameKind = seats.every(function (s) {
                return s.ticketid === first.ticketid;
            });
            var sameRow = seats.every(function (s) {
                return s.row === first.row;
            });

            html += section(T('SEATS'),
                '<div class="ts-se-inline">' +
                    field(T('KIND'), selectInput('data-bulk="kind"', (sameKind ? [] : [['', T('MIXED')]]).concat(kindOptions()), sameKind ? first.ticketid : '')) +
                '</div>' +
                '<div class="ts-se-field"><span class="ts-se-field__label">' + esc(T('STATUS')) + '</span><div class="btn-group btn-group-sm">' +
                    '<button type="button" class="btn btn-outline-secondary" data-action="set-free">' + esc(T('FREE')) + '</button>' +
                    '<button type="button" class="btn btn-outline-secondary" data-action="set-blocked">' + esc(T('BLOCKED')) + '</button>' +
                '</div></div>' +
                '<div class="ts-se-inline">' +
                    field(T('ROW_NAME'), textInput('data-bulk="row"', sameRow ? first.row : '', ' maxlength="5" placeholder="' + esc(sameRow ? '' : T('MIXED')) + '"')) +
                '</div>' +
                '<div class="ts-se-grid2">' +
                    field(T('WIDTH'), numberInput('data-bulk="w" min="4" max="500"', seats.every(function (s) { return s.w === first.w; }) ? first.w : '')) +
                    field(T('HEIGHT'), numberInput('data-bulk="h" min="4" max="500"', seats.every(function (s) { return s.h === first.h; }) ? first.h : '')) +
                '</div>');

            html += section(T('RENUMBER'),
                '<div class="ts-se-grid2">' +
                    field(T('START_NUMBER'), numberInput('data-renumber="start" min="0"', 1)) +
                    field(T('NUMBERING'), selectInput('data-renumber="step"', numberingOptions(), 1)) +
                '</div>' +
                field(T('ORDER'), selectInput('data-renumber="order"', [['ltr', T('ORDER_LTR')], ['rtl', T('ORDER_RTL')], ['ttb', T('ORDER_TTB')], ['btt', T('ORDER_BTT')]], 'ltr')) +
                '<button type="button" class="btn btn-sm btn-outline-primary" data-action="renumber">' + esc(T('RENUMBER_APPLY')) + '</button>');
        }

        return html + actionsSection(seats.length + shapes.length);
    }

    function actionsSection(count) {
        var html = '';

        if (count > 1) {
            html += section(T('ALIGN'),
                '<div class="btn-group btn-group-sm ts-se-wrap">' +
                    button('data-align="left"', '⇤', T('ALIGN_LEFT')) +
                    button('data-align="hcenter"', '↔', T('ALIGN_HCENTER')) +
                    button('data-align="right"', '⇥', T('ALIGN_RIGHT')) +
                    button('data-align="top"', '⤒', T('ALIGN_TOP')) +
                    button('data-align="vcenter"', '↕', T('ALIGN_VCENTER')) +
                    button('data-align="bottom"', '⤓', T('ALIGN_BOTTOM')) +
                '</div>' +
                (count > 2
                    ? '<div class="btn-group btn-group-sm ts-se-wrap mt-2">' +
                        '<button type="button" class="btn btn-outline-secondary" data-align="dist-h">' + esc(T('DISTRIBUTE_H')) + '</button>' +
                        '<button type="button" class="btn btn-outline-secondary" data-align="dist-v">' + esc(T('DISTRIBUTE_V')) + '</button>' +
                      '</div>'
                    : ''));
        }

        return html + '<div class="ts-se-actions">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="duplicate">' + icon('copy') + ' ' + esc(T('DUPLICATE')) + '</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" data-action="delete">' + icon('trash') + ' ' + esc(T('DELETE')) + '</button>' +
        '</div>';
    }

    function viewPanel() {
        var s = S.settings;
        var size = canvasSize();
        var auto = int(s.canvas_width, 0) <= 0 || int(s.canvas_height, 0) <= 0;
        var html = '';

        html += section(T('BACKGROUND'),
            (s.background_url
                ? '<img class="ts-se-thumb" src="' + esc(s.background_url) + '" alt=""><p class="ts-se-small">' + esc(s.background_image.split('/').pop()) + ' · ' + s.bg_width + '×' + s.bg_height + '</p>'
                : '<p class="ts-se-small">' + esc(T('NO_BACKGROUND')) + '</p>') +
            '<div class="ts-se-actions">' +
                '<label class="btn btn-sm btn-outline-primary mb-0">' + esc(s.background_url ? T('REPLACE_BACKGROUND') : T('UPLOAD_BACKGROUND')) +
                    '<input type="file" accept="image/png,image/jpeg,image/webp" data-upload="background" hidden></label>' +
                (s.background_url ? '<button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-background">' + esc(T('REMOVE_BACKGROUND')) + '</button>' : '') +
            '</div>' +
            (s.background_url
                ? '<div class="ts-se-grid2">' +
                    field(T('BG_OFFSET_X'), numberInput('data-setting="bg_offset_x"', s.bg_offset_x)) +
                    field(T('BG_OFFSET_Y'), numberInput('data-setting="bg_offset_y"', s.bg_offset_y)) +
                  '</div>' +
                  field(T('BG_OPACITY'), '<input type="range" class="form-range" min="0.1" max="1" step="0.05" data-pref="bgOpacity" value="' + prefs.bgOpacity + '">', esc(T('BG_OPACITY_HINT')))
                : ''));

        html += section(T('CANVAS'),
            '<label class="form-check"><input type="checkbox" class="form-check-input" data-action="canvas-auto"' + (auto ? ' checked' : '') + '> <span class="form-check-label">' + esc(T('CANVAS_AUTO')) + '</span></label>' +
            '<div class="ts-se-grid2">' +
                field(T('WIDTH'), numberInput('data-setting="canvas_width" min="50" max="20000"', auto ? size.w : s.canvas_width, auto ? ' disabled' : '')) +
                field(T('HEIGHT'), numberInput('data-setting="canvas_height" min="50" max="20000"', auto ? size.h : s.canvas_height, auto ? ' disabled' : '')) +
            '</div>' +
            '<p class="ts-se-small">' + esc(T('CANVAS_HINT')) + '</p>');

        html += section(T('SEAT_SIZE'),
            '<div class="ts-se-grid2">' +
                field(T('WIDTH'), numberInput('data-setting="seat_width" min="4" max="500"', s.seat_width)) +
                field(T('HEIGHT'), numberInput('data-setting="seat_height" min="4" max="500"', s.seat_height)) +
            '</div>' +
            '<div class="ts-se-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="size-selection"' + (selectedSeats().length ? '' : ' disabled') + '>' + esc(T('APPLY_TO_SELECTION')) + '</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="size-all">' + esc(T('APPLY_TO_ALL')) + '</button>' +
            '</div>');

        html += section(T('GRID'),
            field(T('GRID_SIZE'), numberInput('data-setting="grid_size" min="1" max="200"', s.grid_size)) +
            '<label class="form-check"><input type="checkbox" class="form-check-input" data-pref="snap"' + (prefs.snap ? ' checked' : '') + '> <span class="form-check-label">' + esc(T('SNAP')) + '</span></label>' +
            '<label class="form-check"><input type="checkbox" class="form-check-input" data-pref="grid"' + (prefs.grid ? ' checked' : '') + '> <span class="form-check-label">' + esc(T('SHOW_GRID')) + '</span></label>');

        var rows = L.kinds.map(function (k) {
            var own = S.colours[ownerId];
            var mine = S.colours[k.id] || {};
            var cells = ['background_color', 'border_color', 'font_color'].map(function (f) {
                var value = mine[f] || own[f] || '000000';
                var inherited = !k.free && !mine[f];

                return '<td><span class="ts-se-colour' + (inherited ? ' is-inherited' : '') + '">' +
                    '<input type="color" data-colour="' + k.id + '.' + f + '" value="#' + value + '" title="' + esc(inherited ? T('COLOUR_INHERITED') : '') + '">' +
                    (!k.free && mine[f] ? '<button type="button" class="ts-se-colour__reset" data-reset-colour="' + k.id + '.' + f + '" title="' + esc(T('COLOUR_RESET')) + '">×</button>' : '') +
                    '</span></td>';
            }).join('');

            return '<tr><th scope="row">' + esc(k.free ? T('KIND_FREE_SHORT') : k.name) + '</th>' + cells + '</tr>';
        }).join('');

        html += section(T('COLOURS'),
            '<p class="ts-se-small">' + esc(T('COLOURS_HINT')) + '</p>' +
            '<table class="ts-se-colours"><thead><tr><th></th><th>' + esc(T('COLOUR_FILL')) + '</th><th>' + esc(T('COLOUR_BORDER')) + '</th><th>' + esc(T('COLOUR_TEXT')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>');

        return html;
    }

    function templatesPanel() {
        var html = '';
        var locked = S.seats.some(function (s) {
            return s.locked;
        });

        if (locked) {
            html += '<p class="ts-se-note ts-se-note--lock">' + esc(T('REPLACE_LOCKED')) + '</p>';
        }

        if (!L.owner.venueId) {
            html += section(T('TEMPLATES'), '<p class="ts-se-small">' + esc(T('TPL_NO_VENUE')) + '</p>');
        } else {
            var list = L.templates.length
                ? '<ul class="ts-se-templates">' + L.templates.map(function (t) {
                    return '<li><div><strong>' + esc(t.name) + '</strong><br><small>' + esc(T('TPL_META', t.seats, (t.modified || '').slice(0, 16))) + '</small></div>' +
                        '<div class="ts-se-actions">' +
                            '<button type="button" class="btn btn-sm btn-outline-primary" data-template-load="' + t.id + '"' + (locked ? ' disabled' : '') + '>' + esc(T('TPL_LOAD')) + '</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" data-template-overwrite="' + t.id + '"' + (dirty ? ' disabled' : '') + '>' + esc(T('TPL_OVERWRITE')) + '</button>' +
                            '<a class="btn btn-sm btn-outline-secondary" download href="' + esc(opts.url + '&task=exportTemplate&template=' + t.id) + '">' + esc(T('TPL_EXPORT')) + '</a>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger" data-template-delete="' + t.id + '">' + esc(T('DELETE')) + '</button>' +
                        '</div></li>';
                }).join('') + '</ul>'
                : '<p class="ts-se-small">' + esc(T('TPL_NONE')) + '</p>';

            html += section(T('TPL_TITLE', L.owner.venueName), '<p class="ts-se-small">' + esc(T('TPL_HINT')) + '</p>' + list);

            html += section(T('TPL_SAVE_TITLE'),
                (dirty ? '<p class="ts-se-note">' + esc(T('TPL_SAVE_FIRST')) + '</p>' : '') +
                '<div class="ts-se-inline">' +
                    textInput('data-template-name', '', ' maxlength="100" placeholder="' + esc(T('TPL_NAME')) + '"' + (dirty ? ' disabled' : '')) +
                    '<button type="button" class="btn btn-sm btn-primary" data-action="template-save"' + (dirty ? ' disabled' : '') + '>' + esc(T('TPL_SAVE')) + '</button>' +
                '</div>');

            html += section(T('TPL_IMPORT_TITLE'),
                '<p class="ts-se-small">' + esc(T('TPL_IMPORT_HINT')) + '</p>' +
                '<label class="btn btn-sm btn-outline-primary mb-0">' + esc(T('TPL_IMPORT')) +
                    '<input type="file" accept="application/json,.json" data-upload="template" hidden></label>');
        }

        html += section(T('COPY_TITLE'),
            '<p class="ts-se-small">' + esc(T('COPY_HINT')) + '</p>' +
            (L.sources.length
                ? '<div class="ts-se-inline">' +
                    selectInput('data-copy-source', [['', T('CHOOSE_TICKET')]].concat(L.sources.map(function (s) {
                        return [s.id, s.name];
                    })), '') +
                    '<button type="button" class="btn btn-sm btn-outline-primary" data-action="copy"' + (locked ? ' disabled' : '') + '>' + esc(T('COPY')) + '</button>' +
                  '</div>'
                : '<p class="ts-se-small">' + esc(T('COPY_NONE')) + '</p>'));

        return html;
    }

    // ---------------------------------------------------------------- toasts and dialogs

    function toast(message, type) {
        var el = document.createElement('div');

        el.className = 'ts-se-toast ts-se-toast--' + (type || 'info');
        el.textContent = message;
        els.toasts.appendChild(el);

        setTimeout(function () {
            el.classList.add('is-leaving');
            setTimeout(function () {
                el.remove();
            }, 400);
        }, type === 'error' || type === 'warning' ? 8000 : 3500);
    }

    function showDialog(html, onClick) {
        els.dialog.innerHTML = html;
        els.dialog.onclick = function (event) {
            var target = event.target.closest('[data-dialog]');

            if (target) {
                onClick(target.getAttribute('data-dialog'), target);
            }
        };

        if (typeof els.dialog.showModal === 'function') {
            els.dialog.showModal();
        } else {
            els.dialog.setAttribute('open', '');
        }
    }

    function closeDialog() {
        if (typeof els.dialog.close === 'function') {
            els.dialog.close();
        } else {
            els.dialog.removeAttribute('open');
        }
    }

    function showShortcuts() {
        var rows = [
            ['V / R / B / S / T', T('KEYS_TOOLS')],
            [T('KEY_CLICK'), T('KEYS_CLICK')],
            ['Shift / Ctrl + ' + T('KEY_CLICK'), T('KEYS_ADD')],
            [T('KEY_DRAG_EMPTY'), T('KEYS_MARQUEE')],
            [T('KEY_DBLCLICK'), T('KEYS_ROW')],
            ['Ctrl + A', T('KEYS_ALL')],
            ['← ↑ → ↓', T('KEYS_NUDGE')],
            ['Alt', T('KEYS_NOSNAP')],
            ['Del', T('DELETE')],
            ['Ctrl + D', T('DUPLICATE')],
            ['Ctrl + Z / Ctrl + Y', T('UNDO') + ' / ' + T('REDO')],
            ['Ctrl + S', T('SAVE')],
            [T('KEY_SPACE_DRAG'), T('KEYS_PAN')],
            ['Ctrl + ' + T('KEY_WHEEL') + ' / + / − / 0', T('KEYS_ZOOM')],
            ['Esc', T('KEYS_ESC')]
        ];

        showDialog('<h2 class="ts-se-dialog__title">' + esc(T('SHORTCUTS')) + '</h2><table class="ts-se-keys">' + rows.map(function (r) {
            return '<tr><th scope="row"><kbd>' + esc(r[0]) + '</kbd></th><td>' + esc(r[1]) + '</td></tr>';
        }).join('') + '</table><div class="ts-se-dialog__buttons"><button type="button" class="btn btn-primary" data-dialog="close">' + esc(T('CLOSE')) + '</button></div>', function () {
            closeDialog();
        });
    }

    // ---------------------------------------------------------------- server

    /** One request; resolves with the parsed JSON, or {failed: status, text} when the answer isn't JSON. */
    function request(task, fields) {
        var body = new FormData();

        Object.keys(fields || {}).forEach(function (key) {
            body.append(key, fields[key]);
        });
        body.append(opts.token, '1');

        return fetch(opts.url + '&task=' + task, {method: 'POST', body: body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        return {failed: response.status, text: text};
                    }
                });
            }, function () {
                return {error: T('ERR_NETWORK')};
            });
    }

    /** The form token of the current session, or null when the session is gone. */
    function freshToken() {
        return fetch(opts.url + '&task=token', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                return data && data.token ? data.token : null;
            }, function () {
                return null;
            });
    }

    /** The server's own words for a failed request: the message of a Joomla error page, or its text. */
    function describe(failed) {
        var doc = new DOMParser().parseFromString(failed.text || '', 'text/html');
        var node = doc.querySelector('joomla-alert, .alert-message, .alert, h1, title') || doc.body;
        var text = (node ? node.textContent : '').replace(/\s+/g, ' ').trim().slice(0, 200);

        return T('ERR_RESPONSE') + ' (HTTP ' + failed.failed + (text ? ': ' + text : '') + ')';
    }

    function post(task, fields) {
        busy = true;
        renderBar();

        return request(task, fields)
            .then(function (result) {
                if (!result.failed) {
                    return result;
                }

                // Usually a token that no longer matches the session (e.g. after logging in again
                // in another tab): fetch the current one and try once more.
                return freshToken().then(function (token) {
                    if (!token) {
                        return {error: T('ERR_SESSION')};
                    }

                    if (token === opts.token) {
                        return {error: describe(result)};
                    }

                    opts.token = token;

                    return request(task, fields).then(function (retry) {
                        return retry.failed ? {error: describe(retry)} : retry;
                    });
                });
            })
            .then(function (result) {
                busy = false;
                renderBar();

                if (result.error) {
                    toast(result.error, 'error');
                }

                return result;
            });
    }

    function payload() {
        var own = S.colours[ownerId] || {};
        var colours = {};

        L.kinds.forEach(function (k) {
            if (k.id !== ownerId) {
                colours[k.id] = S.colours[k.id] || {};
            }
        });

        return {
            settings: Object.assign({}, S.settings, {
                background_color: own.background_color,
                border_color: own.border_color,
                font_color: own.font_color
            }),
            shapes: S.shapes.map(function (sh) {
                return {kind: sh.kind, x: sh.x, y: sh.y, w: sh.w, h: sh.h, label: sh.label, color: sh.color, text_color: sh.text_color, font: sh.font};
            }),
            colours: colours,
            seats: S.seats.map(function (s) {
                return {id: s.id, x: s.x, y: s.y, w: s.w, h: s.h, row: s.row, num: s.num, ticketid: s.ticketid, blocked: s.blocked ? 1 : 0};
            }),
            deleted: S.deleted
        };
    }

    function save(force) {
        if (busy) {
            return;
        }

        if (!force) {
            var issues = validate();

            if (issues.length) {
                showIssues(issues);
                return;
            }
        }

        post('saveLayout', {layout: JSON.stringify(payload())}).then(function (result) {
            if (result.layout) {
                var view = {left: els.viewport.scrollLeft, top: els.viewport.scrollTop};

                load(result.layout);
                els.viewport.scrollLeft = view.left;
                els.viewport.scrollTop = view.top;
                toast(T('SAVED'), 'success');

                (result.warnings || []).forEach(function (warning) {
                    toast(warning, 'warning');
                });
            }
        });
    }

    /** Problems worth a second look before saving: duplicate numbers, overlap, off-canvas. */
    function validate() {
        var issues = [];
        var groups = {};
        var seats = S.seats.filter(function (s) {
            return s.type === 1;
        });

        seats.forEach(function (s) {
            var key = s.row + '\u0000' + s.num;

            (groups[key] = groups[key] || []).push(s);
        });

        Object.keys(groups).forEach(function (key) {
            if (groups[key].length > 1) {
                issues.push({text: T('ISSUE_DUPLICATE', seatLabel(groups[key][0]), groups[key].length), keys: groups[key].map(function (s) { return s.key; })});
            }
        });

        var sorted = S.seats.slice().sort(function (a, b) {
            return a.x - b.x;
        });
        var overlaps = 0;

        for (var i = 0; i < sorted.length && overlaps < 50; i++) {
            var a = sorted[i];

            for (var j = i + 1; j < sorted.length && sorted[j].x < a.x + a.w - 1; j++) {
                var b = sorted[j];

                if (b.y < a.y + a.h - 1 && a.y < b.y + b.h - 1) {
                    issues.push({text: T('ISSUE_OVERLAP', seatLabel(a), seatLabel(b)), keys: [a.key, b.key]});
                    overlaps++;
                }
            }
        }

        var size = canvasSize();

        if (size.fixed) {
            var outside = S.seats.concat(S.shapes).filter(function (o) {
                return o.x + o.w > size.w || o.y + o.h > size.h;
            });

            if (outside.length) {
                issues.push({text: T('ISSUE_OUTSIDE', outside.length), keys: outside.map(function (o) { return o.key; })});
            }
        }

        return issues;
    }

    function showIssues(issues) {
        showDialog(
            '<h2 class="ts-se-dialog__title">' + esc(T('ISSUES_TITLE')) + '</h2>' +
            '<p>' + esc(T('ISSUES_INTRO')) + '</p>' +
            '<ul class="ts-se-issues">' + issues.map(function (issue, i) {
                return '<li><span>' + esc(issue.text) + '</span> <button type="button" class="btn btn-link btn-sm" data-dialog="show" data-issue="' + i + '">' + esc(T('SHOW')) + '</button></li>';
            }).join('') + '</ul>' +
            '<div class="ts-se-dialog__buttons">' +
                '<button type="button" class="btn btn-secondary" data-dialog="close">' + esc(T('BACK')) + '</button>' +
                '<button type="button" class="btn btn-success" data-dialog="save">' + esc(T('SAVE_ANYWAY')) + '</button>' +
            '</div>',
            function (action, target) {
                closeDialog();

                if (action === 'save') {
                    save(true);
                } else if (action === 'show') {
                    select(issues[int(target.getAttribute('data-issue'), 0)].keys);
                    reveal(selected());
                }
            });
    }

    // ---------------------------------------------------------------- selection helpers

    function select(keys, additive) {
        if (!additive) {
            sel.clear();
        }

        keys.forEach(function (key) {
            sel.add(key);
        });

        if (tool !== 'select') {
            tool = 'select';
            els.ghost.innerHTML = '';
            renderBar();
        }

        renderSelection();
    }

    function reveal(items) {
        if (!items.length) {
            return;
        }

        var box = bounds(items);

        els.viewport.scrollLeft = ((box.x1 + box.x2) / 2) * zoom + PAD - els.viewport.clientWidth / 2;
        els.viewport.scrollTop = ((box.y1 + box.y2) / 2) * zoom + PAD - els.viewport.clientHeight / 2;
    }

    function setTool(name) {
        tool = name;
        els.ghost.innerHTML = '';

        if (name !== 'select') {
            prefs.tab = 'props';
        }

        renderBar();
        renderPanel();
    }

    // ---------------------------------------------------------------- generators

    function rowSeats(origin) {
        var p = prefs.row;
        var n = clamp(int(p.count, 10), 1, 200);
        var w = int(S.settings.seat_width, 22);
        var h = int(S.settings.seat_height, 22);
        var gap = clamp(int(p.gap, 4), 0, 200);
        var curve = int(p.curve, 0);
        var step = int(p.step, 1);
        var start = int(p.start, 1);
        var seats = [];

        for (var i = 0; i < n; i++) {
            var t = n > 1 ? (2 * i / (n - 1)) - 1 : 0;
            var bend = Math.round(curve * (1 - t * t));

            seats.push({
                x: Math.max(0, p.dir === 'v' ? origin.x + bend : origin.x + i * (w + gap)),
                y: Math.max(0, p.dir === 'v' ? origin.y + i * (h + gap) : origin.y + bend),
                w: w, h: h, row: String(p.row || '').slice(0, 5), num: Math.max(0, start + i * step), ticketid: kindParam.row
            });
        }

        return seats;
    }

    function blockSeats(origin) {
        var p = prefs.block;
        var rows = clamp(int(p.rows, 5), 1, 100);
        var cols = clamp(int(p.cols, 10), 1, 200);
        var w = int(S.settings.seat_width, 22);
        var h = int(S.settings.seat_height, 22);
        var gapX = clamp(int(p.gapX, 4), 0, 200);
        var gapY = clamp(int(p.gapY, 8), 0, 400);
        var step = int(p.step, 1);
        var start = int(p.start, 1);
        var row = String(p.row || 'A').slice(0, 5);
        var seats = [];

        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                seats.push({
                    x: origin.x + c * (w + gapX),
                    y: origin.y + r * (h + gapY),
                    w: w, h: h, row: row, num: Math.max(0, start + c * step), ticketid: kindParam.block
                });
            }

            row = nextRow(row);
        }

        return seats;
    }

    function ghostSeats(event) {
        var p = toCanvas(event);
        var origin = {x: snapValue(p.x, event), y: snapValue(p.y, event)};

        return tool === 'row' ? rowSeats(origin) : blockSeats(origin);
    }

    function renderGhost(seats) {
        els.ghost.innerHTML = seats.map(function (s) {
            return '<div class="ts-se-ghost" style="left:' + s.x + 'px;top:' + s.y + 'px;width:' + s.w + 'px;height:' + s.h + 'px;font-size:' + fontSize(s.w, s.h) + 'px">' + esc(s.row + s.num) + '</div>';
        }).join('');
    }

    // ---------------------------------------------------------------- pointer handling

    function track(onMove, onUp) {
        function move(event) {
            onMove(event);
        }

        function up(event) {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);

            if (onUp) {
                onUp(event);
            }
        }

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    els.viewport.addEventListener('pointerdown', function (event) {
        if (event.button === 1 || (event.button === 0 && spaceDown)) {
            event.preventDefault();
            startPan(event);
            return;
        }

        if (event.button !== 0) {
            return;
        }

        els.viewport.focus({preventScroll: true});

        var p = toCanvas(event);

        if (tool === 'row' || tool === 'block') {
            placeSeats(event);
            return;
        }

        if (tool === 'rect' || tool === 'text') {
            drawShape(event, p);
            return;
        }

        var target = event.target.closest('[data-key]');
        var additive = event.shiftKey || event.ctrlKey || event.metaKey;

        if (target) {
            var key = target.getAttribute('data-key');
            var wasSelected = sel.has(key);

            if (additive) {
                if (wasSelected) {
                    sel.delete(key);
                    renderSelection();
                    return;
                }

                sel.add(key);
                renderSelection();
            } else if (!wasSelected) {
                sel.clear();
                sel.add(key);
                renderSelection();
            }

            startDrag(event, p, key, wasSelected && !additive);
            return;
        }

        startMarquee(p, additive);
    });

    els.viewport.addEventListener('dblclick', function (event) {
        var target = event.target.closest('.ts-se-seat');

        if (!target || tool !== 'select') {
            return;
        }

        var seat = byKey.get(target.getAttribute('data-key'));

        select(S.seats.filter(function (s) {
            return s.row === seat.row && s.ticketid === seat.ticketid;
        }).map(function (s) {
            return s.key;
        }));
    });

    els.viewport.addEventListener('pointermove', function (event) {
        cursor = toCanvas(event);

        var label = els.status.querySelector('.ts-se__cursor');

        if (label) {
            label.textContent = 'x ' + Math.round(cursor.x) + ' · y ' + Math.round(cursor.y);
        }

        if ((tool === 'row' || tool === 'block') && !spaceDown) {
            renderGhost(ghostSeats(event));
        }
    });

    els.viewport.addEventListener('pointerleave', function () {
        els.ghost.innerHTML = '';
    });

    els.viewport.addEventListener('wheel', function (event) {
        if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            setZoom(zoom * (event.deltaY < 0 ? 1.15 : 1 / 1.15), event.clientX, event.clientY);
        }
    }, {passive: false});

    function startPan(event) {
        var sx = event.clientX, sy = event.clientY;
        var left = els.viewport.scrollLeft, top = els.viewport.scrollTop;

        root.classList.add('is-panning');
        track(function (e) {
            els.viewport.scrollLeft = left - (e.clientX - sx);
            els.viewport.scrollTop = top - (e.clientY - sy);
        }, function () {
            root.classList.remove('is-panning');
        });
    }

    function startDrag(event, p, key, collapseOnClick) {
        var items = selected();
        var origin = items.map(function (o) {
            return {o: o, x: o.x, y: o.y};
        });
        var anchor = byKey.get(key);
        var ax = anchor.x, ay = anchor.y;
        var minX = Math.min.apply(null, origin.map(function (r) { return r.x; }));
        var minY = Math.min.apply(null, origin.map(function (r) { return r.y; }));
        var before = snapshot();
        var moved = false;

        track(function (e) {
            var q = toCanvas(e);
            var dx = q.x - p.x, dy = q.y - p.y;

            if (!moved && Math.abs(dx * zoom) < 3 && Math.abs(dy * zoom) < 3) {
                return;
            }

            moved = true;

            if (snapOn(e)) {
                dx = snapValue(ax + dx, e) - ax;
                dy = snapValue(ay + dy, e) - ay;
            } else {
                dx = Math.round(dx);
                dy = Math.round(dy);
            }

            dx = Math.max(dx, -minX);
            dy = Math.max(dy, -minY);

            origin.forEach(function (r) {
                var el = elByKey.get(r.o.key);

                r.o.x = r.x + dx;
                r.o.y = r.y + dy;

                if (el) {
                    el.style.left = r.o.x + 'px';
                    el.style.top = r.o.y + 'px';
                }
            });
        }, function () {
            if (moved) {
                pushUndo(before);
                setDirty(true);
                renderAll();
            } else if (collapseOnClick && sel.size > 1) {
                sel.clear();
                sel.add(key);
                renderSelection();
            }
        });
    }

    function startMarquee(p, additive) {
        var base = additive ? new Set(sel) : new Set();

        if (!additive && sel.size) {
            sel.clear();
            renderSelection();
        }

        track(function (e) {
            var q = toCanvas(e);
            var box = {x1: Math.min(p.x, q.x), y1: Math.min(p.y, q.y), x2: Math.max(p.x, q.x), y2: Math.max(p.y, q.y)};

            els.marquee.hidden = false;
            els.marquee.style.left = box.x1 + 'px';
            els.marquee.style.top = box.y1 + 'px';
            els.marquee.style.width = (box.x2 - box.x1) + 'px';
            els.marquee.style.height = (box.y2 - box.y1) + 'px';

            sel = new Set(base);
            byKey.forEach(function (o, key) {
                if (o.x < box.x2 && o.x + o.w > box.x1 && o.y < box.y2 && o.y + o.h > box.y1) {
                    sel.add(key);
                }
            });
            elByKey.forEach(function (el, key) {
                el.classList.toggle('is-selected', sel.has(key));
            });
        }, function () {
            els.marquee.hidden = true;
            renderSelection();
        });
    }

    function placeSeats(event) {
        var created = ghostSeats(event).map(function (s) {
            return newSeat(s);
        });

        change(function () {
            S.seats = S.seats.concat(created);
        });

        sel = new Set(created.map(function (s) {
            return s.key;
        }));

        if (tool === 'row') {
            prefs.row.row = nextRow(prefs.row.row);
        } else {
            prefs.block.row = nextRow(created[created.length - 1].row);
        }

        savePrefs();
        renderItems();
        renderPanel();
        renderStatus();
    }

    function drawShape(event, p) {
        var start = {x: snapValue(p.x, event), y: snapValue(p.y, event)};
        var kindName = tool;
        var box = {x: start.x, y: start.y, w: 0, h: 0};

        track(function (e) {
            var q = toCanvas(e);
            var x2 = snapValue(q.x, e), y2 = snapValue(q.y, e);

            box = {x: Math.min(start.x, x2), y: Math.min(start.y, y2), w: Math.abs(x2 - start.x), h: Math.abs(y2 - start.y)};
            els.ghost.innerHTML = '<div class="ts-se-ghost ts-se-ghost--shape" style="left:' + box.x + 'px;top:' + box.y + 'px;width:' + box.w + 'px;height:' + box.h + 'px"></div>';
        }, function () {
            var p2 = prefs.shape;

            if (box.w < 8 || box.h < 8) {
                box = {x: start.x, y: start.y, w: kindName === 'text' ? 160 : 120, h: kindName === 'text' ? 30 : 40};
            }

            var shape = newShape({
                kind: kindName, x: Math.max(0, box.x), y: Math.max(0, box.y), w: box.w, h: box.h,
                label: p2.label || (kindName === 'text' ? T('TEXT_DEFAULT') : T('STAGE')),
                color: hex(p2.color, 'dddddd'), text_color: hex(p2.text_color, '000000'), font: clamp(int(p2.font, 14), 6, 96)
            });

            els.ghost.innerHTML = '';
            change(function () {
                S.shapes.push(shape);
            });
            sel = new Set([shape.key]);
            setTool('select');
            renderSelection();
        });
    }

    // ---------------------------------------------------------------- zoom

    function setZoom(value, clientX, clientY) {
        var rect = els.viewport.getBoundingClientRect();
        var px = clientX === undefined ? rect.left + rect.width / 2 : clientX;
        var py = clientY === undefined ? rect.top + rect.height / 2 : clientY;
        var cx = (els.viewport.scrollLeft + px - rect.left - PAD) / zoom;
        var cy = (els.viewport.scrollTop + py - rect.top - PAD) / zoom;

        zoom = clamp(Math.round(value * 100) / 100, 0.2, 4);
        renderCanvas();
        els.viewport.scrollLeft = cx * zoom + PAD - (px - rect.left);
        els.viewport.scrollTop = cy * zoom + PAD - (py - rect.top);
    }

    function zoomFit() {
        var size = canvasSize();
        var w = els.viewport.clientWidth - PAD * 2;
        var h = els.viewport.clientHeight - PAD * 2;

        zoom = clamp(Math.floor(Math.min(w / size.w, h / size.h) * 100) / 100, 0.2, 2);
        renderCanvas();
        els.viewport.scrollLeft = 0;
        els.viewport.scrollTop = 0;
    }

    // ---------------------------------------------------------------- actions

    function lockedSkipped(count) {
        if (count) {
            toast(T('SKIPPED_LOCKED', count), 'warning');
        }
    }

    function deleteSelection() {
        var seats = selectedSeats();
        var shapes = selectedShapes();
        var skipped = seats.filter(function (s) {
            return s.locked;
        }).length;

        if (!seats.length && !shapes.length) {
            return;
        }

        change(function () {
            S.seats = S.seats.filter(function (s) {
                if (sel.has(s.key) && !s.locked) {
                    if (s.id) {
                        S.deleted.push(s.id);
                    }

                    return false;
                }

                return true;
            });
            S.shapes = S.shapes.filter(function (sh) {
                return !sel.has(sh.key);
            });
        });

        lockedSkipped(skipped);
    }

    function duplicateSelection() {
        var seats = selectedSeats();
        var shapes = selectedShapes();

        if (!seats.length && !shapes.length) {
            return;
        }

        var box = bounds(seats.concat(shapes));
        var dy = box.y2 - box.y1 + grid();
        var copies = [];

        change(function () {
            seats.forEach(function (s) {
                var copy = newSeat({x: s.x, y: s.y + dy, w: s.w, h: s.h, row: nextRow(s.row), num: s.num, ticketid: s.ticketid, blocked: s.blocked, type: s.type});

                copies.push(copy);
                S.seats.push(copy);
            });
            shapes.forEach(function (sh) {
                var copy = newShape({kind: sh.kind, x: sh.x, y: sh.y + dy, w: sh.w, h: sh.h, label: sh.label, color: sh.color, text_color: sh.text_color, font: sh.font});

                copies.push(copy);
                S.shapes.push(copy);
            });
        });

        select(copies.map(function (o) {
            return o.key;
        }));
        reveal(copies);
    }

    /** Applies fn to the unlocked seats of the selection; locked seats are counted and skipped. */
    function editUnlocked(fn) {
        var seats = selectedSeats();
        var skipped = 0;

        change(function () {
            seats.forEach(function (s) {
                if (s.locked) {
                    skipped++;
                } else {
                    fn(s);
                }
            });
        });

        lockedSkipped(skipped);
    }

    function renumber() {
        var start = int(els.panel.querySelector('[data-renumber="start"]').value, 1);
        var step = int(els.panel.querySelector('[data-renumber="step"]').value, 1);
        var order = els.panel.querySelector('[data-renumber="order"]').value;
        var seats = selectedSeats().filter(function (s) {
            return !s.locked;
        });
        var skipped = selectedSeats().length - seats.length;

        seats.sort(function (a, b) {
            if (order === 'ltr') return a.x - b.x || a.y - b.y;
            if (order === 'rtl') return b.x - a.x || a.y - b.y;
            if (order === 'ttb') return a.y - b.y || a.x - b.x;
            return b.y - a.y || a.x - b.x;
        });

        change(function () {
            seats.forEach(function (s, i) {
                s.num = Math.max(0, start + i * step);
            });
        });

        lockedSkipped(skipped);
    }

    function align(mode) {
        var items = selected();

        if (items.length < 2) {
            return;
        }

        var box = bounds(items);

        change(function () {
            if (mode === 'dist-h' || mode === 'dist-v') {
                var horizontal = mode === 'dist-h';
                var sorted = items.slice().sort(function (a, b) {
                    return horizontal ? a.x - b.x : a.y - b.y;
                });
                var first = sorted[0], last = sorted[sorted.length - 1];
                var from = horizontal ? first.x + first.w / 2 : first.y + first.h / 2;
                var to = horizontal ? last.x + last.w / 2 : last.y + last.h / 2;

                sorted.forEach(function (o, i) {
                    var centre = from + (to - from) * i / (sorted.length - 1);

                    if (horizontal) {
                        o.x = Math.round(centre - o.w / 2);
                    } else {
                        o.y = Math.round(centre - o.h / 2);
                    }
                });

                return;
            }

            items.forEach(function (o) {
                if (mode === 'left') o.x = box.x1;
                if (mode === 'right') o.x = box.x2 - o.w;
                if (mode === 'hcenter') o.x = Math.round((box.x1 + box.x2) / 2 - o.w / 2);
                if (mode === 'top') o.y = box.y1;
                if (mode === 'bottom') o.y = box.y2 - o.h;
                if (mode === 'vcenter') o.y = Math.round((box.y1 + box.y2) / 2 - o.h / 2);
                o.x = Math.max(0, o.x);
                o.y = Math.max(0, o.y);
            });
        });
    }

    function nudge(dx, dy) {
        var items = selected();

        if (!items.length) {
            return;
        }

        var now = Date.now();
        var before = snapshot();

        items.forEach(function (o) {
            o.x = Math.max(0, o.x + dx);
            o.y = Math.max(0, o.y + dy);
        });

        // A run of arrow presses is one undo step.
        if (now - lastNudge > 800) {
            pushUndo(before);
        }

        lastNudge = now;
        setDirty(true);
        renderAll();
    }

    function uploadBackground(file) {
        post('uploadBackground', {image: file}).then(function (result) {
            if (result.image) {
                change(function () {
                    S.settings.background_image = result.image.path;
                    S.settings.background_url = result.image.url;
                    S.settings.bg_width = result.image.width;
                    S.settings.bg_height = result.image.height;
                    S.settings.bg_offset_x = 0;
                    S.settings.bg_offset_y = 0;
                });
                toast(T('BACKGROUND_UPLOADED'), 'success');
            }
        });
    }

    function confirmReplace() {
        return window.confirm(T('CONFIRM_REPLACE') + (dirty ? '\n\n' + T('CONFIRM_UNSAVED') : ''));
    }

    function afterReplace(result) {
        if (result.layout) {
            load(result.layout);
            zoomFit();
            toast(result.message, 'success');
        }
    }

    // ---------------------------------------------------------------- events

    els.bar.addEventListener('click', function (event) {
        var target = event.target.closest('button');

        if (!target) {
            return;
        }

        if (target.hasAttribute('data-tool')) {
            setTool(target.getAttribute('data-tool'));
            return;
        }

        switch (target.getAttribute('data-action')) {
            case 'undo': undo(); break;
            case 'redo': redo(); break;
            case 'toggle-snap': prefs.snap = !prefs.snap; savePrefs(); renderBar(); renderPanel(); break;
            case 'toggle-grid': prefs.grid = !prefs.grid; savePrefs(); renderBar(); renderCanvas(); renderPanel(); break;
            case 'zoom-in': setZoom(zoom * 1.25); break;
            case 'zoom-out': setZoom(zoom / 1.25); break;
            case 'zoom-fit': zoomFit(); break;
            case 'help': showShortcuts(); break;
            case 'save': save(false); break;
        }
    });

    els.tabs.addEventListener('click', function (event) {
        var target = event.target.closest('[data-tab]');

        if (target) {
            prefs.tab = target.getAttribute('data-tab');
            savePrefs();
            renderPanel();
        }
    });

    els.panel.addEventListener('change', function (event) {
        var t = event.target;
        var value = t.type === 'checkbox' ? t.checked : t.value;

        if (t.hasAttribute('data-param')) {
            var path = t.getAttribute('data-param').split('.');

            if (path[0] === 'kind') {
                kindParam[path[1]] = int(value, ownerId);
            } else if (t.type === 'color') {
                prefs[path[0]][path[1]] = hex(value, '000000');
            } else if (t.type === 'number' || path[1] === 'step') {
                prefs[path[0]][path[1]] = int(value, 0);
            } else {
                prefs[path[0]][path[1]] = value;
            }

            savePrefs();
            renderPanel();
            return;
        }

        if (t.hasAttribute('data-pref')) {
            var pref = t.getAttribute('data-pref');

            prefs[pref] = pref === 'bgOpacity' ? parseFloat(value) : value;
            savePrefs();
            renderBar();
            renderCanvas();
            return;
        }

        if (t.hasAttribute('data-setting')) {
            var setting = t.getAttribute('data-setting');

            change(function () {
                S.settings[setting] = int(value, 0);
            });
            return;
        }

        if (t.hasAttribute('data-colour')) {
            var parts = t.getAttribute('data-colour').split('.');

            change(function () {
                S.colours[parts[0]] = S.colours[parts[0]] || {};
                S.colours[parts[0]][parts[1]] = hex(value, '000000');
            });
            return;
        }

        if (t.hasAttribute('data-seat')) {
            var seat = selectedSeats()[0];
            var prop = t.getAttribute('data-seat');

            if (!seat) {
                return;
            }

            change(function () {
                if (prop === 'row') {
                    seat.row = String(value).slice(0, 5);
                } else if (prop === 'blocked') {
                    seat.blocked = value === '1';
                } else if (prop === 'w' || prop === 'h') {
                    seat[prop] = clamp(int(value, 22), 4, 500);
                } else {
                    seat[prop] = Math.max(0, int(value, 0));
                }
            });
            return;
        }

        if (t.hasAttribute('data-shape')) {
            var shape = selectedShapes()[0];
            var key = t.getAttribute('data-shape');

            if (!shape) {
                return;
            }

            change(function () {
                if (key === 'label' || key === 'kind') {
                    shape[key] = key === 'kind' ? (value === 'text' ? 'text' : 'rect') : String(value).slice(0, 100);
                } else if (key === 'color' || key === 'text_color') {
                    shape[key] = hex(value, '000000');
                } else if (key === 'font') {
                    shape.font = clamp(int(value, 14), 6, 96);
                } else {
                    shape[key] = Math.max(key === 'w' || key === 'h' ? 4 : 0, int(value, 0));
                }
            });
            return;
        }

        if (t.hasAttribute('data-bulk')) {
            var bulk = t.getAttribute('data-bulk');

            if (value === '') {
                return;
            }

            if (bulk === 'w' || bulk === 'h') {
                change(function () {
                    selectedSeats().forEach(function (s) {
                        s[bulk] = clamp(int(value, 22), 4, 500);
                    });
                });
            } else if (bulk === 'kind') {
                editUnlocked(function (s) {
                    s.ticketid = int(value, ownerId);
                });
            } else if (bulk === 'row') {
                editUnlocked(function (s) {
                    s.row = String(value).slice(0, 5);
                });
            }

            return;
        }

        if (t.getAttribute('data-action') === 'canvas-auto') {
            var size = canvasSize();

            change(function () {
                S.settings.canvas_width = value ? 0 : size.w;
                S.settings.canvas_height = value ? 0 : size.h;
            });
            return;
        }

        if (t.getAttribute('data-upload') === 'background' && t.files.length) {
            uploadBackground(t.files[0]);
            return;
        }

        if (t.getAttribute('data-upload') === 'template' && t.files.length) {
            post('importTemplate', {file: t.files[0]}).then(function (result) {
                if (result.templates) {
                    L.templates = result.templates;
                    renderPanel();
                    toast(result.message, 'success');
                }
            });
        }
    });

    els.panel.addEventListener('click', function (event) {
        var t = event.target.closest('button');

        if (!t) {
            return;
        }

        if (t.hasAttribute('data-align')) {
            align(t.getAttribute('data-align'));
            return;
        }

        if (t.hasAttribute('data-reset-colour')) {
            var parts = t.getAttribute('data-reset-colour').split('.');

            change(function () {
                S.colours[parts[0]][parts[1]] = '';
            });
            return;
        }

        if (t.hasAttribute('data-template-load')) {
            if (confirmReplace()) {
                post('loadTemplate', {template: t.getAttribute('data-template-load')}).then(afterReplace);
            }

            return;
        }

        if (t.hasAttribute('data-template-overwrite')) {
            var template = L.templates.find(function (x) {
                return String(x.id) === t.getAttribute('data-template-overwrite');
            });

            if (template && window.confirm(T('CONFIRM_OVERWRITE', template.name))) {
                post('saveTemplate', {template: template.id, name: template.name}).then(function (result) {
                    if (result.templates) {
                        L.templates = result.templates;
                        renderPanel();
                        toast(result.message, 'success');
                    }
                });
            }

            return;
        }

        if (t.hasAttribute('data-template-delete')) {
            if (window.confirm(T('CONFIRM_DELETE_TEMPLATE'))) {
                post('deleteTemplate', {template: t.getAttribute('data-template-delete')}).then(function (result) {
                    if (result.templates) {
                        L.templates = result.templates;
                        renderPanel();
                        toast(result.message, 'success');
                    }
                });
            }

            return;
        }

        switch (t.getAttribute('data-action')) {
            case 'set-free':
                editUnlocked(function (s) {
                    s.blocked = false;
                });
                break;
            case 'set-blocked':
                editUnlocked(function (s) {
                    s.blocked = true;
                });
                break;
            case 'renumber':
                renumber();
                break;
            case 'duplicate':
                duplicateSelection();
                break;
            case 'delete':
                deleteSelection();
                break;
            case 'remove-background':
                change(function () {
                    S.settings.background_image = '';
                    S.settings.background_url = '';
                    S.settings.bg_width = 0;
                    S.settings.bg_height = 0;
                });
                break;
            case 'size-selection':
            case 'size-all':
                var all = t.getAttribute('data-action') === 'size-all';

                change(function () {
                    (all ? S.seats : selectedSeats()).forEach(function (s) {
                        s.w = int(S.settings.seat_width, 22);
                        s.h = int(S.settings.seat_height, 22);
                    });
                });
                break;
            case 'template-save':
                var name = els.panel.querySelector('[data-template-name]').value.trim();

                if (!name) {
                    toast(T('TPL_NAME_REQUIRED'), 'warning');
                    break;
                }

                post('saveTemplate', {name: name}).then(function (result) {
                    if (result.templates) {
                        L.templates = result.templates;
                        renderPanel();
                        toast(result.message, 'success');
                    }
                });
                break;
            case 'copy':
                var source = els.panel.querySelector('[data-copy-source]').value;

                if (source && confirmReplace()) {
                    post('copyFromTicket', {source: source}).then(afterReplace);
                }

                break;
        }
    });

    document.addEventListener('keydown', function (event) {
        var target = event.target;
        var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName) || target.isContentEditable;
        var mod = event.ctrlKey || event.metaKey;
        var key = event.key.toLowerCase();

        if (els.dialog.open) {
            return;
        }

        if (mod && key === 's') {
            event.preventDefault();

            if (typing) {
                target.blur();
            }

            save(false);
            return;
        }

        if (typing || (target !== document.body && !root.contains(target))) {
            return;
        }

        if (mod && (key === 'z' && !event.shiftKey)) {
            event.preventDefault();
            undo();
        } else if (mod && (key === 'y' || (key === 'z' && event.shiftKey))) {
            event.preventDefault();
            redo();
        } else if (mod && key === 'a') {
            event.preventDefault();
            select(Array.from(byKey.keys()));
        } else if (mod && key === 'd') {
            event.preventDefault();
            duplicateSelection();
        } else if (key === 'delete' || key === 'backspace') {
            event.preventDefault();
            deleteSelection();
        } else if (key === 'escape') {
            if (tool !== 'select') {
                setTool('select');
            } else if (sel.size) {
                sel.clear();
                renderSelection();
            }
        } else if (key.indexOf('arrow') === 0 && sel.size) {
            event.preventDefault();

            var step = event.shiftKey ? grid() : 1;

            nudge(key === 'arrowleft' ? -step : key === 'arrowright' ? step : 0, key === 'arrowup' ? -step : key === 'arrowdown' ? step : 0);
        } else if (key === ' ') {
            event.preventDefault();
            spaceDown = true;
            root.classList.add('is-space');
            els.ghost.innerHTML = '';
        } else if (!mod && !event.altKey) {
            var tools = {v: 'select', r: 'row', b: 'block', s: 'rect', t: 'text'};

            if (tools[key]) {
                setTool(tools[key]);
            } else if (key === 'g') {
                prefs.snap = !prefs.snap;
                savePrefs();
                renderBar();
                toast(prefs.snap ? T('SNAP_ON') : T('SNAP_OFF'), 'info');
            } else if (key === '+' || key === '=') {
                setZoom(zoom * 1.25);
            } else if (key === '-') {
                setZoom(zoom / 1.25);
            } else if (key === '0') {
                zoomFit();
            }
        }
    });

    document.addEventListener('keyup', function (event) {
        if (event.key === ' ') {
            spaceDown = false;
            root.classList.remove('is-space');
        }
    });

    window.addEventListener('beforeunload', function (event) {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // ---------------------------------------------------------------- start

    kindParam.row = kindParam.block = (opts.layout && opts.layout.owner) ? opts.layout.owner.id : 0;
    load(opts.layout);

    if (canvasSize().w > els.viewport.clientWidth - PAD * 2) {
        zoomFit();
    }
})();
