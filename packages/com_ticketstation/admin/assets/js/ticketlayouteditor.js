/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * The layout editor on the "Ticket Layout" tab of the ticket form. It shows the ticket's
 * background (TicketController::TicketLayoutEditor(), rendered with pdf.js) with every printed
 * field on top, and lets the admin drag the fields into place.
 *
 * The editor keeps no data of its own: the form fields under "Advanced" (<field>_position,
 * _fontsize, _fontcolor, qrcode_width, ...) are the only source, and they are saved with the
 * ticket form as before. Dragging writes those fields; typing in them moves the field here.
 *
 * Fields are placed like FPDF prints them (see TicketLayoutFields): Write() starts the text 1 mm
 * right of X with the baseline at Y + 0.3 x the font size, TextWithDirection('U') runs upwards
 * from X/Y, and the QR image is placed at 96 dpi. Text widths come from FPDF's own font metrics,
 * and the SVG text is stretched to exactly that width, so the frames match the PDF.
 *
 * Every user-facing string goes through T(key), which reads COM_TICKETSTATION_TLE_<key>; the
 * view registers all keys it finds in this file, so keep the key a literal.
 */
(function () {
    'use strict';

    var root = document.getElementById('ts-tle');
    var form = document.getElementById('adminForm');

    if (!root || !form || !window.Joomla) {
        return;
    }

    var opts = Joomla.getOptions('com_ticketstation.ticketlayouteditor') || {};
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var PT = 25.4 / 72;          // mm per point
    var QR_MM_PER_PX = 25.4 / 96; // the QR image is placed at 96 dpi
    var CELL_MARGIN = 1;          // FPDF's cell margin in mm (Write() starts the text here)
    var PAGE_MARGIN = 10;         // FPDF's right margin: Write() wraps text that runs past it
    var PREFS_KEY = 'ts-tle-prefs';

    var FAMILIES = {
        raleway: '"TS Raleway", sans-serif',
        opensans: '"TS Open Sans", sans-serif',
        helvetica: 'Helvetica, Arial, sans-serif',
        times: '"Times New Roman", Times, serif',
        courier: '"Courier New", Courier, monospace'
    };

    // Windows-1252 bytes 0x80-0x9F, which FPDF's widths are indexed by.
    var CP1252 = {
        0x20AC: 0x80, 0x201A: 0x82, 0x0192: 0x83, 0x201E: 0x84, 0x2026: 0x85, 0x2020: 0x86, 0x2021: 0x87,
        0x02C6: 0x88, 0x2030: 0x89, 0x0160: 0x8A, 0x2039: 0x8B, 0x0152: 0x8C, 0x017D: 0x8E, 0x2018: 0x91,
        0x2019: 0x92, 0x201C: 0x93, 0x201D: 0x94, 0x2022: 0x95, 0x2013: 0x96, 0x2014: 0x97, 0x02DC: 0x98,
        0x2122: 0x99, 0x0161: 0x9A, 0x203A: 0x9B, 0x0153: 0x9C, 0x017E: 0x9E, 0x0178: 0x9F
    };

    function T(key) {
        var text = Joomla.Text._('COM_TICKETSTATION_TLE_' + key, key);

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

    function num(value, fallback) {
        var n = parseFloat(String(value === undefined || value === null ? '' : value).replace(',', '.'));

        return isNaN(n) ? fallback : n;
    }

    function round1(value) {
        return Math.round(value * 10) / 10;
    }

    function hex(value, fallback) {
        value = String(value || '').replace('#', '').trim();

        if (/^[0-9a-f]{3}$/i.test(value)) {
            value = value.replace(/(.)/g, '$1$1');
        }

        return /^[0-9a-f]{6}$/i.test(value) ? value.toLowerCase() : fallback;
    }

    function svg(name, attrs) {
        var el = document.createElementNS(SVG_NS, name);

        Object.keys(attrs || {}).forEach(function (key) {
            el.setAttribute(key, attrs[key]);
        });

        return el;
    }

    function loadPrefs() {
        try {
            return JSON.parse(window.localStorage.getItem(PREFS_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    function savePrefs() {
        try {
            window.localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
        } catch (e) {
            // Private window or blocked storage: the grid simply isn't remembered.
        }
    }

    // ---------------------------------------------------------------- form fields

    function input(name) {
        return document.getElementById('jform_' + name);
    }

    function value(name) {
        var el = input(name);

        return el ? el.value : '';
    }

    function radio(name) {
        var checked = form.querySelector('input[name="jform[' + name + ']"]:checked');

        return checked ? checked.value : '0';
    }

    var writing = false;

    function write(name, newValue, fire) {
        var el = input(name);

        if (!el || el.value === String(newValue)) {
            return;
        }

        el.value = newValue;

        if (fire) {
            writing = true;
            el.dispatchEvent(new Event('change', {bubbles: true}));
            writing = false;
        }
    }

    function writeRadio(name, newValue) {
        var el = document.getElementById('jform_' + name + (String(newValue) === '1' ? '1' : '0'));

        if (el && !el.checked) {
            el.checked = true;
            writing = true;
            el.dispatchEvent(new Event('change', {bubbles: true}));
            writing = false;
        }
    }

    function parsePosition(text) {
        text = String(text || '');

        if (text.indexOf('-') === -1) {
            return null;
        }

        var parts = text.split('-');

        return {x: num(parts[0], 0), y: num(parts[1], 0)};
    }

    function formatPosition(x, y) {
        return round1(x) + '-' + round1(y);
    }

    // ---------------------------------------------------------------- state

    var fields = opts.fields || [];
    var prefs = loadPrefs();
    var server = null;        // the last answer of TicketLayoutEditor()
    var pdfPage = null;       // the background page, for re-rendering at another scale
    var pdfjs = null;
    var selected = null;
    var remembered = {};      // the last position of a field that was switched off
    var scale = 1;            // px per mm
    var drag = null;
    var loadTimer = null;
    var loadSeq = 0;

    if (!prefs.grid && prefs.grid !== 0) {
        prefs.grid = 1;
    }

    // With no uploaded design and no position filled in, the ticket prints the built-in field
    // set (DefaultTicketLayout::defaultFields()). The editor shows that set, and writes all of
    // it to the form on the first change, so moving one field never makes the others vanish.
    function usingDefaults() {
        if (!server || !server.defaults) {
            return false;
        }

        return !fields.some(function (field) {
            return parsePosition(value(field.key + '_position')) !== null;
        });
    }

    function get(name) {
        if (usingDefaults() && Object.prototype.hasOwnProperty.call(server.defaults, name)) {
            return server.defaults[name];
        }

        if (name === 'orderticketindex_prependtext_print' || name === 'orderreference_centered') {
            return radio(name);
        }

        return value(name);
    }

    function materializeDefaults() {
        if (!usingDefaults()) {
            return;
        }

        var defaults = server.defaults;

        Object.keys(defaults).forEach(function (name) {
            if (name === 'orderticketindex_prependtext_print' || name === 'orderreference_centered') {
                writeRadio(name, defaults[name]);
            } else {
                write(name, defaults[name], true);
            }
        });
    }

    // ---------------------------------------------------------------- metrics

    function family() {
        var font = value('ticket_font');

        return FAMILIES[font] ? font : 'raleway';
    }

    // The width of a text in mm, the way FPDF's GetStringWidth() measures it.
    function textWidth(text, sizePt, bold) {
        var metrics = (opts.metrics || {})[family()];
        var widths = metrics ? metrics[bold ? 1 : 0] : null;
        var total = 0;

        text = String(text);

        for (var i = 0; i < text.length; i++) {
            var code = text.charCodeAt(i);
            var byte = code < 256 ? code : (CP1252[code] || 63);

            total += widths ? widths[byte] : 500;
        }

        return total * sizePt / 1000 * PT;
    }

    // ---------------------------------------------------------------- geometry

    function fontSize(key) {
        var size = num(get(key + '_fontsize'), 0);

        return size > 0 ? size : 10;
    }

    function color(key) {
        return '#' + hex(get(key + '_fontcolor'), '000000');
    }

    // The printed lines of a text field, each with its own offset, size and weight.
    function lines(field) {
        var texts = server.texts || {};
        var size = fontSize(field.key);

        if (field.key === 'client') {
            if (value('ticket_size') === 'A5') {
                return [
                    {text: texts.client_orderedby || '', size: size + 1, bold: true, dy: 0},
                    {text: String(texts.client_name || '').substring(0, 35), size: size, bold: false, dy: 5}
                ];
            }

            return [{text: (texts.client_orderedby || '') + ' ' + (texts.client_name || ''), size: size, bold: true, dy: 0}];
        }

        if (field.key === 'orderticketindex') {
            if (String(get('orderticketindex_prependtext_print')) === '1') {
                return [{text: get('orderticketindex_prependtext') + ' ' + (texts.orderticketindex || ''), size: size, bold: true, dy: 0}];
            }

            return [{text: texts.orderticketindex || '', size: size, bold: false, dy: 0}];
        }

        return [{text: texts[field.key] || '', size: size, bold: field.bold, dy: 0}];
    }

    // Where a field is, and the frame the editor draws around it, in mm.
    function geometry(field) {
        var pos = parsePosition(get(field.key + '_position'));

        if (!pos) {
            return null;
        }

        if (field.kind === 'qr') {
            var px = num(get('qrcode_width'), 0) || 30;
            var side = px * QR_MM_PER_PX;

            return {pos: pos, box: {x: pos.x, y: pos.y, w: side, h: side}, side: side};
        }

        var ls = lines(field);
        var x = pos.x + CELL_MARGIN;
        var centered = field.key === 'orderreference' && String(get('orderreference_centered')) === '1';

        if (centered) {
            // ticketcreator::doPDF(): the page centre moved 4 % to the left, minus half the text.
            x = (server.width / 2) - (server.width * 0.04) - textWidth(ls[0].text, ls[0].size, ls[0].bold) / 2 + CELL_MARGIN;
        }

        var boxes = ls.map(function (line) {
            var fs = line.size * PT;
            var baseline = field.kind === 'rotated' ? pos.y : pos.y + line.dy + 0.3 * fs;

            line.x = field.kind === 'rotated' ? pos.x : x;
            line.baseline = baseline;
            line.width = textWidth(line.text, line.size, line.bold);

            return {x: line.x, y: baseline - 0.78 * fs, w: line.width, h: fs};
        });

        var box = boxes.reduce(function (all, b) {
            var x1 = Math.min(all.x, b.x);
            var y1 = Math.min(all.y, b.y);

            return {
                x: x1, y: y1,
                w: Math.max(all.x + all.w, b.x + b.w) - x1,
                h: Math.max(all.y + all.h, b.y + b.h) - y1
            };
        });

        return {
            pos: pos,
            lines: ls,
            box: box,
            centered: centered,
            // Write() only fits a text in the page width minus the right margin and twice the cell
            // margin; the rest continues at the left margin, over the other text at that height.
            overflow: field.kind === 'text' && box.x + box.w > server.width - PAGE_MARGIN - CELL_MARGIN + 0.01
        };
    }

    // ---------------------------------------------------------------- markup

    root.innerHTML =
        '<div class="ts-tle__toolbar">' +
            '<label class="ts-tle__tool">' + esc(T('GRID')) + ' ' +
                '<select class="form-select form-select-sm" data-tle="grid">' +
                    '<option value="0">' + esc(T('GRID_OFF')) + '</option>' +
                    '<option value="0.5">0,5 mm</option>' +
                    '<option value="1">1 mm</option>' +
                    '<option value="5">5 mm</option>' +
                '</select>' +
            '</label>' +
            '<button type="button" class="btn btn-sm btn-secondary" data-tle="preview"><span class="icon-search" aria-hidden="true"></span> ' + esc(T('PREVIEW')) + '</button>' +
            '<span class="ts-tle__status" data-tle="status" aria-live="polite"></span>' +
        '</div>' +
        '<div class="ts-tle__notes" data-tle="notes"></div>' +
        '<div class="ts-tle__body">' +
            '<div class="ts-tle__stage" data-tle="stage">' +
                '<div class="ts-tle__paper" data-tle="paper" tabindex="0" aria-label="' + esc(T('CANVAS')) + '">' +
                    '<canvas class="ts-tle__bg" data-tle="bg"></canvas>' +
                '</div>' +
            '</div>' +
            '<aside class="ts-tle__side">' +
                '<h3 class="ts-tle__heading">' + esc(T('ELEMENTS')) + '</h3>' +
                '<ul class="ts-tle__list" data-tle="list"></ul>' +
                '<div class="ts-tle__props" data-tle="props"></div>' +
            '</aside>' +
        '</div>';

    var els = {};

    root.querySelectorAll('[data-tle]').forEach(function (el) {
        els[el.getAttribute('data-tle')] = el;
    });

    var layer = svg('svg', {'class': 'ts-tle__layer'});
    els.paper.appendChild(layer);
    els.grid.value = String(prefs.grid);

    function status(text, kind) {
        els.status.textContent = text || '';
        els.status.className = 'ts-tle__status' + (kind ? ' is-' + kind : '');
    }

    // ---------------------------------------------------------------- drawing

    // keepProps: leave the properties panel alone, so a value being typed there is not reformatted.
    function draw(keepProps) {
        if (!server) {
            return;
        }

        var fam = FAMILIES[family()];

        while (layer.firstChild) {
            layer.removeChild(layer.firstChild);
        }

        layer.setAttribute('viewBox', '0 0 ' + server.width + ' ' + server.height);

        fields.forEach(function (field) {
            var geo = geometry(field);

            if (!geo) {
                return;
            }

            var g = svg('g', {
                'class': 'ts-tle__field' + (selected === field.key ? ' is-selected' : '') + (geo.overflow ? ' is-overflow' : '') +
                    (field.condition ? ' is-conditional' : ''),
                'data-key': field.key
            });
            var title = svg('title');
            title.textContent = field.label + (field.condition ? ' (' + field.condition + ')' : '') + (geo.overflow ? ' - ' + T('OVERFLOW') : '');
            g.appendChild(title);

            if (field.kind === 'qr') {
                g.appendChild(svg('rect', {'class': 'ts-tle__frame', x: geo.box.x, y: geo.box.y, width: geo.box.w, height: geo.box.h}));
                drawQr(g, geo.box.x, geo.box.y, geo.side);
            } else {
                var holder = g;

                if (field.kind === 'rotated') {
                    holder = svg('g', {transform: 'rotate(-90 ' + geo.pos.x + ' ' + geo.pos.y + ')'});
                    g.appendChild(holder);
                }

                holder.appendChild(svg('rect', {'class': 'ts-tle__frame', x: geo.box.x, y: geo.box.y, width: Math.max(geo.box.w, 2), height: geo.box.h}));

                geo.lines.forEach(function (line) {
                    if (line.text === '') {
                        return;
                    }

                    var text = svg('text', {
                        x: line.x,
                        y: line.baseline,
                        'font-size': line.size * PT,
                        'font-family': fam,
                        'font-weight': line.bold ? 700 : 400,
                        fill: color(field.key),
                        // Stretch the browser's glyphs to the width FPDF gives the text.
                        textLength: line.width,
                        lengthAdjust: 'spacingAndGlyphs'
                    });
                    text.textContent = line.text;
                    holder.appendChild(text);
                });
            }

            layer.appendChild(g);
        });

        drawList();
        drawNotes();

        if (!keepProps) {
            drawProps();
        }
    }

    // A QR-like placeholder: the three finder squares and a few modules.
    function drawQr(g, x, y, side) {
        var m = side / 21;

        g.appendChild(svg('rect', {x: x, y: y, width: side, height: side, fill: '#fff'}));

        [[0, 0], [14, 0], [0, 14]].forEach(function (c) {
            g.appendChild(svg('rect', {x: x + c[0] * m, y: y + c[1] * m, width: 7 * m, height: 7 * m, fill: '#000'}));
            g.appendChild(svg('rect', {x: x + (c[0] + 1) * m, y: y + (c[1] + 1) * m, width: 5 * m, height: 5 * m, fill: '#fff'}));
            g.appendChild(svg('rect', {x: x + (c[0] + 2) * m, y: y + (c[1] + 2) * m, width: 3 * m, height: 3 * m, fill: '#000'}));
        });

        [[9, 2], [11, 4], [8, 8], [10, 10], [13, 9], [16, 11], [9, 14], [12, 16], [15, 15], [18, 18], [17, 14], [10, 18]].forEach(function (c) {
            g.appendChild(svg('rect', {x: x + c[0] * m, y: y + c[1] * m, width: 2 * m, height: 2 * m, fill: '#000'}));
        });
    }

    function drawList() {
        els.list.innerHTML = fields.map(function (field) {
            var on = parsePosition(get(field.key + '_position')) !== null;

            return '<li class="ts-tle__item' + (selected === field.key ? ' is-selected' : '') + '">' +
                '<input class="form-check-input" type="checkbox" id="ts-tle-on-' + field.key + '" data-toggle="' + field.key + '"' + (on ? ' checked' : '') + '>' +
                '<button type="button" class="ts-tle__pick" data-pick="' + field.key + '"' + (on ? '' : ' disabled') + '>' + esc(field.label) + '</button>' +
                (field.condition ? '<small class="ts-tle__cond">' + esc(field.condition) + '</small>' : '') +
            '</li>';
        }).join('');
    }

    function fieldByKey(key) {
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].key === key) {
                return fields[i];
            }
        }

        return null;
    }

    function drawProps() {
        var field = selected ? fieldByKey(selected) : null;
        var geo = field ? geometry(field) : null;

        if (!field || !geo) {
            els.props.innerHTML = '<p class="ts-tle__hint">' + esc(T('SELECT_HINT')) + '</p>';
            return;
        }

        // Keep the focus (and caret) when the panel is redrawn while the admin types in it.
        var active = document.activeElement && els.props.contains(document.activeElement) ? document.activeElement.getAttribute('data-prop') : null;
        var html = '<h3 class="ts-tle__heading">' + esc(field.label) + '</h3>' +
            '<div class="ts-tle__grid">' +
                prop('x', T('X'), round1(geo.pos.x), 'mm', geo.centered) +
                prop('y', T('Y'), round1(geo.pos.y), 'mm') +
            '</div>';

        if (field.kind === 'qr') {
            html += '<div class="ts-tle__grid">' + prop('qr', T('QR_SIZE'), round1(geo.side), 'mm') + '</div>';
        } else {
            var fontcolor = hex(get(field.key + '_fontcolor'), '000000');

            html += '<div class="ts-tle__grid">' +
                prop('size', T('SIZE'), fontSize(field.key), 'pt') +
                '<label class="ts-tle__prop"><span>' + esc(T('COLOR')) + '</span>' +
                    '<input type="color" class="form-control form-control-color" data-prop="color" value="#' + fontcolor + '">' +
                '</label>' +
            '</div>';
        }

        if (geo.centered) {
            html += '<p class="ts-tle__hint">' + esc(T('CENTERED_HINT')) + '</p>';
        }

        if (geo.overflow) {
            html += '<p class="ts-tle__hint is-warning">' + esc(T('OVERFLOW')) + '</p>';
        }

        if (field.condition) {
            html += '<p class="ts-tle__hint">' + esc(field.condition) + '</p>';
        }

        els.props.innerHTML = html;

        if (active) {
            var el = els.props.querySelector('[data-prop="' + active + '"]');

            if (el) {
                el.focus();
            }
        }
    }

    function prop(name, label, val, unit, disabled) {
        return '<label class="ts-tle__prop"><span>' + esc(label) + '</span>' +
            '<span class="input-group input-group-sm">' +
                '<input type="number" class="form-control" step="0.1" min="0" data-prop="' + name + '" value="' + esc(val) + '"' + (disabled ? ' disabled' : '') + '>' +
                '<span class="input-group-text">' + esc(unit) + '</span>' +
            '</span>' +
        '</label>';
    }

    function drawNotes() {
        var notes = [];

        if (server.invalidSize) {
            notes.push('<div class="alert alert-warning">' + esc(T('INVALID_SIZE')) + '</div>');
        }

        if (usingDefaults()) {
            notes.push('<div class="alert alert-info">' + esc(T('DEFAULTS_NOTE')) + '</div>');
        }

        els.notes.innerHTML = notes.join('');
    }

    // ---------------------------------------------------------------- background

    function fitScale() {
        if (!server) {
            return;
        }

        var available = els.stage.clientWidth - 2;
        var maxHeight = Math.max(320, window.innerHeight * 0.75);

        scale = Math.max(1, Math.min(available / server.width, maxHeight / server.height));

        var w = Math.round(server.width * scale);
        var h = Math.round(server.height * scale);

        els.paper.style.width = w + 'px';
        els.paper.style.height = h + 'px';
        layer.setAttribute('width', w);
        layer.setAttribute('height', h);

        renderBackground();
    }

    var renderTask = null;

    function renderBackground() {
        if (!pdfPage) {
            return;
        }

        var ratio = window.devicePixelRatio || 1;
        var base = pdfPage.getViewport({scale: 1});
        var viewport = pdfPage.getViewport({scale: (parseFloat(els.paper.style.width) * ratio) / base.width});
        var canvas = els.bg;

        if (renderTask) {
            renderTask.cancel();
        }

        canvas.width = Math.round(viewport.width);
        canvas.height = Math.round(viewport.height);

        renderTask = pdfPage.render({canvasContext: canvas.getContext('2d'), viewport: viewport});
        renderTask.promise.catch(function () {
            // Cancelled by a newer render.
        });
    }

    function loadPdfjs() {
        if (pdfjs) {
            return Promise.resolve(pdfjs);
        }

        return import(opts.pdfjs).then(function (lib) {
            lib.GlobalWorkerOptions.workerSrc = opts.worker;
            pdfjs = lib;

            return lib;
        });
    }

    function load() {
        var seq = ++loadSeq;

        status(T('LOADING'));

        fetch(opts.url, {method: 'POST', body: window.ticketFormBody(), cache: 'no-store'})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                if (seq !== loadSeq) {
                    return null;
                }

                server = data;
                fitScale();
                draw();

                var bytes = Uint8Array.from(atob(data.background), function (c) {
                    return c.charCodeAt(0);
                });

                return loadPdfjs()
                    .then(function (lib) {
                        return lib.getDocument({data: bytes, isEvalSupported: false}).promise;
                    })
                    .then(function (doc) {
                        return doc.getPage(1);
                    })
                    .then(function (page) {
                        if (seq !== loadSeq) {
                            return;
                        }

                        pdfPage = page;
                        renderBackground();
                        status('');
                    });
            })
            .catch(function () {
                if (seq === loadSeq) {
                    status(T('LOAD_ERROR'), 'error');
                }
            });
    }

    function scheduleLoad() {
        clearTimeout(loadTimer);
        loadTimer = setTimeout(load, 400);
    }

    // ---------------------------------------------------------------- editing

    function snap(v) {
        var grid = num(prefs.grid, 0);

        return grid > 0 ? Math.round(v / grid) * grid : v;
    }

    function setPosition(key, x, y) {
        materializeDefaults();
        x = clamp(round1(x), 0, server.width);
        y = clamp(round1(y), 0, server.height);
        write(key + '_position', formatPosition(x, y), true);
    }

    function toggle(key, on) {
        materializeDefaults();

        if (on) {
            var pos = remembered[key] || {x: PAGE_MARGIN, y: PAGE_MARGIN};

            write(key + '_position', formatPosition(pos.x, pos.y), true);

            if (key === 'qrcode' && !num(value('qrcode_width'), 0)) {
                write('qrcode_width', 120, true);
            }

            selected = key;
        } else {
            var current = parsePosition(value(key + '_position'));

            if (current) {
                remembered[key] = current;
            }

            write(key + '_position', '', true);

            if (selected === key) {
                selected = null;
            }
        }

        draw();
    }

    function pointerMm(event) {
        var rect = layer.getBoundingClientRect();

        return {x: (event.clientX - rect.left) / scale, y: (event.clientY - rect.top) / scale};
    }

    layer.addEventListener('pointerdown', function (event) {
        var target = event.target.closest ? event.target.closest('.ts-tle__field') : null;

        if (!target) {
            selected = null;
            draw();
            return;
        }

        var key = target.getAttribute('data-key');
        var geo = geometry(fieldByKey(key));

        selected = key;
        drag = {key: key, start: pointerMm(event), pos: geo.pos, moved: false, centered: geo.centered};
        layer.setPointerCapture(event.pointerId);
        event.preventDefault();
        els.paper.focus({preventScroll: true});
        draw();
    });

    layer.addEventListener('pointermove', function (event) {
        if (!drag) {
            return;
        }

        var now = pointerMm(event);
        var dx = now.x - drag.start.x;
        var dy = now.y - drag.start.y;

        if (!drag.moved && Math.abs(dx) * scale < 2 && Math.abs(dy) * scale < 2) {
            return;
        }

        drag.moved = true;

        // A centred reference keeps its X: only its height on the page can change.
        var x = drag.centered ? drag.pos.x : snap(drag.pos.x + dx);

        setPosition(drag.key, x, snap(drag.pos.y + dy));
        draw();
    });

    function endDrag() {
        drag = null;
    }

    layer.addEventListener('pointerup', endDrag);
    layer.addEventListener('pointercancel', endDrag);

    els.paper.addEventListener('keydown', function (event) {
        var moves = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};

        if (!selected || !moves[event.key]) {
            if (event.key === 'Escape') {
                selected = null;
                draw();
            }

            return;
        }

        var geo = geometry(fieldByKey(selected));

        if (!geo) {
            return;
        }

        var step = event.shiftKey ? 5 : 0.5;
        var move = moves[event.key];

        event.preventDefault();
        setPosition(selected, geo.centered ? geo.pos.x : geo.pos.x + move[0] * step, geo.pos.y + move[1] * step);
        draw();
    });

    els.list.addEventListener('change', function (event) {
        var key = event.target.getAttribute('data-toggle');

        if (key) {
            toggle(key, event.target.checked);
        }
    });

    els.list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-pick]');

        if (button) {
            selected = button.getAttribute('data-pick');
            draw();
        }
    });

    els.props.addEventListener('input', function (event) {
        var name = event.target.getAttribute('data-prop');
        var field = selected ? fieldByKey(selected) : null;
        var geo = field ? geometry(field) : null;

        if (!name || !geo) {
            return;
        }

        var v = num(event.target.value, NaN);

        if (name === 'color') {
            materializeDefaults();
            write(field.key + '_fontcolor', event.target.value.replace('#', '').toUpperCase(), true);
        } else if (isNaN(v) || v < 0) {
            return;
        } else if (name === 'x') {
            setPosition(field.key, v, geo.pos.y);
        } else if (name === 'y') {
            setPosition(field.key, geo.pos.x, v);
        } else if (name === 'size' && v > 0) {
            materializeDefaults();
            write(field.key + '_fontsize', String(round1(v)), true);
        } else if (name === 'qr' && v > 0) {
            materializeDefaults();
            write('qrcode_width', String(Math.round(v / QR_MM_PER_PX)), true);
        }

        draw(true);
    });

    els.grid.addEventListener('change', function () {
        prefs.grid = num(els.grid.value, 0);
        savePrefs();
    });

    els.preview.addEventListener('click', function () {
        if (typeof window.openTicketPreview === 'function') {
            window.openTicketPreview();
        }
    });

    // The form fields are the source: redraw when they change, and fetch a new background
    // when the paper size, orientation or venue changes.
    function onFormChange(event) {
        if (writing || !event.target || !event.target.name) {
            return;
        }

        var name = event.target.name.replace(/^jform\[|\]$/g, '');

        if (['ticket_size', 'ticket_orientation', 'override_ticketsize', 'venue'].indexOf(name) !== -1) {
            scheduleLoad();
        }

        draw();
    }

    form.addEventListener('input', onFormChange);
    form.addEventListener('change', onFormChange);

    if (window.ResizeObserver) {
        new ResizeObserver(function () {
            fitScale();
        }).observe(els.stage);
    } else {
        window.addEventListener('resize', fitScale);
    }

    // The tab is hidden on page load when another tab is active; the observer above sizes the
    // paper as soon as it becomes visible.
    load();
})();
