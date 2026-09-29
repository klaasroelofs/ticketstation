/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Zoom buttons of the seat charts drawn by SeatChart::open(): they scale the chart inside its
 * scroll area and keep the point in the middle of the view where it was.
 *
 * Seat tooltips: a seat with data-ts-tip (lines separated by newlines, the first one being the
 * seat itself) shows that text next to it on hover or keyboard focus, and for a moment after a
 * tap, since a tap also picks the seat. An ancestor with data-ts-tip-taken/data-ts-tip-mine
 * supplies the line for a taken seat or the customer's own choice.
 */
(function () {
    'use strict';

    var STEPS = [1, 1.5, 2, 3, 4];

    function zoomTo(frame, zoom) {
        var scroll = frame.querySelector('.ts-chart-scroll');
        var before = parseFloat(frame.style.getPropertyValue('--ts-zoom')) || 1;
        var cx = (scroll.scrollLeft + scroll.clientWidth / 2) / before;
        var cy = (scroll.scrollTop + scroll.clientHeight / 2) / before;

        frame.style.setProperty('--ts-zoom', zoom);
        scroll.scrollLeft = cx * zoom - scroll.clientWidth / 2;
        scroll.scrollTop = cy * zoom - scroll.clientHeight / 2;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-ts-zoom]');

        if (!button) {
            return;
        }

        hideTip();

        var frame = button.closest('.ts-chart-frame');
        var current = parseFloat(frame.style.getPropertyValue('--ts-zoom')) || 1;
        var index = STEPS.indexOf(current);
        var action = button.getAttribute('data-ts-zoom');

        if (action === 'in') {
            zoomTo(frame, STEPS[Math.min(STEPS.length - 1, index + 1)]);
        } else if (action === 'out') {
            zoomTo(frame, STEPS[Math.max(0, index - 1)]);
        } else {
            zoomTo(frame, 1);
        }
    });

    var TAP_TIME = 2500;
    var tip = null;
    var tipSeat = null;
    var tipTimer = 0;
    // Redraws the tooltip when the seat changes state while it is shown (picked or removed).
    var watcher = window.MutationObserver ? new MutationObserver(function () {
        if (tipSeat) {
            showTip(tipSeat);
        }
    }) : null;

    function seatOf(target) {
        return target && target.closest ? target.closest('.ts-chart [data-ts-tip]') : null;
    }

    function showTip(seat) {
        var frame = seat.closest('.ts-chart-frame');

        if (!frame) {
            return;
        }

        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'ts-chart-tip';
            // No role="tooltip": Joomla templates style (and hide) every element with that role.
            tip.setAttribute('aria-hidden', 'true');
        }

        if (tip.parentNode !== frame) {
            frame.appendChild(tip);
        }

        var lines = seat.getAttribute('data-ts-tip').split('\n');
        var labels = seat.closest('[data-ts-tip-taken]');

        if (labels && seat.classList.contains('seat-element--mine')) {
            lines.push(labels.getAttribute('data-ts-tip-mine'));
        } else if (labels && seat.classList.contains('seat-element--taken')) {
            lines.push(labels.getAttribute('data-ts-tip-taken'));
        }

        tip.textContent = '';
        lines.forEach(function (line, i) {
            var el = document.createElement(i === 0 ? 'strong' : 'span');
            el.textContent = line;
            tip.appendChild(el);
        });
        tip.hidden = false;

        if (tipSeat !== seat && watcher) {
            watcher.disconnect();
            watcher.observe(seat, {attributes: true, attributeFilter: ['class']});
        }
        tipSeat = seat;

        // Above the seat, or below it when there is no room; never past the sides of the chart.
        // Measured at the frame's corner, since at its previous place it may be squeezed.
        tip.style.left = '0';
        tip.style.top = '0';

        var f = frame.getBoundingClientRect();
        var s = seat.getBoundingClientRect();
        var gap = 6;
        var left = s.left - f.left + s.width / 2 - tip.offsetWidth / 2;
        var top = s.top - f.top - tip.offsetHeight - gap;

        tip.style.left = Math.max(0, Math.min(left, f.width - tip.offsetWidth)) + 'px';
        tip.style.top = (top < 0 ? s.bottom - f.top + gap : top) + 'px';
    }

    function hideTip() {
        clearTimeout(tipTimer);

        if (tip) {
            tip.hidden = true;
        }

        if (watcher) {
            watcher.disconnect();
        }
        tipSeat = null;
    }

    document.addEventListener('pointerover', function (event) {
        var seat = seatOf(event.target);

        if (seat && event.pointerType !== 'touch' && seat !== tipSeat) {
            showTip(seat);
        }
    });

    document.addEventListener('pointerout', function (event) {
        var seat = seatOf(event.target);

        if (seat && event.pointerType !== 'touch' && seat === tipSeat && !seat.contains(event.relatedTarget)) {
            hideTip();
        }
    });

    // A tap shows the tooltip for a moment; a tap anywhere else closes it.
    document.addEventListener('pointerdown', function (event) {
        if (event.pointerType !== 'touch') {
            return;
        }

        var seat = seatOf(event.target);

        if (!seat) {
            hideTip();
            return;
        }

        showTip(seat);
        clearTimeout(tipTimer);
        tipTimer = setTimeout(hideTip, TAP_TIME);
    });

    document.addEventListener('focusin', function (event) {
        var seat = seatOf(event.target);

        if (seat) {
            showTip(seat);
        }
    });

    document.addEventListener('focusout', function (event) {
        if (seatOf(event.target) === tipSeat) {
            hideTip();
        }
    });

    // Scrolling the chart (or the page) would leave the tooltip behind.
    document.addEventListener('scroll', function () {
        if (tipSeat) {
            hideTip();
        }
    }, true);
})();
