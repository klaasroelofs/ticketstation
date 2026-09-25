/**
 * mod_ticketstation_basket - keeps the full basket in step with the component.
 *
 * com_ticketstation's event views add a ticket over AJAX and then write the new ticket count
 * into #basket-item-count. That is the cue to re-read the totals through the component's
 * `updatecart` task and swap them into the module.
 *
 * The component also show/hides #ticketstation_basket_module, but jQuery resolves that id to the
 * first element on the page, which is the mini basket when both modes are in use. The full basket
 * therefore decides its own visibility from the count, and applies it to the module chrome too:
 * the module title is rendered by the template around our markup and would otherwise stay on
 * screen while the module is hidden.
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */
((document, Joomla) => {
    'use strict';

    const options = Joomla.getOptions('mod_ticketstation_basket') || {};
    const root = document.querySelector('[data-basket-module="full"]');

    if (!options.updateUrl || !root) {
        return;
    }

    const details = root.querySelector('[data-basket-details]');
    const actions = root.querySelector('[data-basket-actions]');
    const ownCounter = root.querySelector('[data-basket-count]');

    // The component writes the count into #basket-item-count, which is the mini basket's counter
    // when there is one. Without a mini basket this module's own counter takes that id.
    if (ownCounter && !document.getElementById('basket-item-count')) {
        ownCounter.id = 'basket-item-count';
    }

    const counter = document.getElementById('basket-item-count');

    if (!details || !actions || !counter) {
        return;
    }

    /**
     * The module chrome is template specific, so it is recognised by its title: the smallest
     * ancestor of the module that also contains a heading with the module title. Without a
     * (shown) title there is no chrome to hide and the module itself is all there is.
     */
    const findChrome = () => {
        const title = (options.title || '').trim();

        if (!title) {
            return root;
        }

        for (let node = root.parentElement; node && node !== document.body; node = node.parentElement) {
            const headings = node.querySelectorAll('h1, h2, h3, h4, h5, h6, .module-title, .card-header');

            if (Array.from(headings).some((heading) => !root.contains(heading) && heading.textContent.trim() === title)) {
                return node;
            }
        }

        return root;
    };

    const chrome = findChrome();

    const isEmpty = () => counter.textContent.trim() === '0';

    // Inline display rather than the hidden attribute: chrome CSS such as `.card { display: flex }`
    // would override the latter.
    const syncVisibility = () => {
        const visible = !(options.hideEmpty && isEmpty());

        root.style.display = visible ? 'block' : 'none';
        actions.hidden = isEmpty();

        if (chrome !== root) {
            chrome.style.display = visible ? '' : 'none';
        }
    };

    let latestRequest = 0;

    const refresh = async () => {
        syncVisibility();

        // Two quick changes fire two requests; only the newest answer may update the module.
        const request = ++latestRequest;

        try {
            const response = await fetch(options.updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok || request !== latestRequest) {
                return;
            }

            details.innerHTML = await response.text();

            // The component's markup carries a fixed 250px width meant for its own sidebar.
            details.querySelectorAll('table').forEach((table) => {
                table.classList.add('ticketstation-basket-table');
                table.style.removeProperty('width');
            });
        } catch (error) {
            // Keep the previous totals; the next cart change retries.
        }
    };

    syncVisibility();

    new MutationObserver(refresh).observe(counter, { childList: true, characterData: true, subtree: true });
})(document, Joomla);
