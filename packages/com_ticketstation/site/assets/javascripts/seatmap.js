/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Zoom buttons of the seat charts drawn by SeatChart::open(): they scale the chart inside its
 * scroll area and keep the point in the middle of the view where it was.
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
})();
