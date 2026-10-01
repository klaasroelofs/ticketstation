/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * New reservation wizard (view=reservation): pings ReservationController::touch() while a step
 * is open, so the tickets in the reservation stay out of the automatic cleanup of unfinished
 * orders (shortest setting: 10 minutes) for as long as the admin is working on it.
 */
(() => {
    'use strict';

    const options = Joomla.getOptions('com_ticketstation.reservation');
    if (!options || !options.url) {
        return;
    }

    const INTERVAL = 2 * 60 * 1000;

    const touch = () => {
        const body = new FormData();
        body.append(options.token, '1');

        // Best effort: a missed ping is caught up by the next one, and steps 3 and 4 check on
        // the server whether the tickets are still there.
        fetch(options.url, { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
    };

    setInterval(touch, INTERVAL);
})();
