/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Keeps the figures of the "Door today" widget up to date while the dashboard is open: every half
 * minute the numbers of each event are asked for and put into the widget. A hidden tab doesn't ask.
 */
(() => {
  const options = Joomla.getOptions('mod_ticketstation_dashboard') || {};
  const widget = document.querySelector('[data-ts-door]');

  if (!options.doorUrl || !widget) {
    return;
  }

  const set = (parent, selector, value) => {
    const element = parent.querySelector(selector);

    if (element) {
      element.textContent = value;
    }
  };

  const update = (events) => {
    events.forEach((event) => {
      const row = widget.querySelector(`.ts-door[data-event="${event.id}"]`);

      if (!row) {
        return;
      }

      set(row, '.ts-door-scanned', event.scanned);
      set(row, '.ts-door-sold', event.sold);
      set(row, '.ts-door-percentage', event.percentage);
      set(row, '.ts-door-left', event.left);
      set(row, '.ts-door-lastscan', event.lastscan);

      const bar = row.querySelector('.ts-door-bar');

      if (bar) {
        bar.style.width = `${event.percentage}%`;
        bar.parentElement.setAttribute('aria-valuenow', event.percentage);
      }

      const last = row.querySelector('.ts-door-lastscan-row');

      if (last) {
        last.hidden = !event.lastscan;
      }
    });
  };

  const refresh = () => {
    if (document.hidden) {
      return;
    }

    fetch(options.doorUrl, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then((response) => response.json())
      .then((response) => {
        if (response && response.data && Array.isArray(response.data.events)) {
          update(response.data.events);
        }
      })
      .catch(() => {
        // A missed refresh is made up for by the next one
      });
  };

  window.setInterval(refresh, 30000);
})();
