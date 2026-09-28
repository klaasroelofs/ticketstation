/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Documentation page (view=docs): live search with highlighting, sidebar with
 * scrollspy, deep links to subsections, expand/collapse all, print and back-to-top.
 * Everything works on the content already in the page; no server requests.
 */
(() => {
    'use strict';

    const root = document.querySelector('.ticketstation-docs');
    if (!root) {
        return;
    }

    const t = (key, ...args) => {
        let text = Joomla.Text._('COM_TICKETSTATION_DOCS_' + key);
        args.forEach((arg, i) => {
            text = text.replace('%' + (i + 1) + '$s', arg).replace('%s', arg);
        });
        return text;
    };

    const input     = document.getElementById('ts-docs-search');
    const clearBtn  = document.getElementById('ts-docs-search-clear');
    const status    = document.getElementById('ts-docs-search-status');
    const noResults = document.getElementById('ts-docs-noresults');
    const jump      = document.getElementById('ts-docs-jump');
    const backToTop = document.getElementById('ts-docs-backtotop');
    const hintText  = status.textContent;
    const topics    = Array.from(root.querySelectorAll('[data-docs-topic]'));
    const groups    = Array.from(root.querySelectorAll('[data-docs-group]'));
    const navGroups = Array.from(root.querySelectorAll('[data-docs-navgroup]'));
    const allDetails = Array.from(root.querySelectorAll('.ts-docs-topic details'));

    const navItem = (slug) => root.querySelector('[data-docs-nav="' + slug + '"]');

    /* ---------------------------------------------------------------------------
     * Offset below Atum's sticky toolbar, so anchors and the sidebar aren't hidden
     * ------------------------------------------------------------------------- */
    const updateOffset = () => {
        let offset = 0;
        document.querySelectorAll('#subhead-container, header.header').forEach((el) => {
            const style = getComputedStyle(el);
            if (style.position === 'fixed') {
                offset = Math.max(offset, el.getBoundingClientRect().bottom);
            } else if (style.position === 'sticky') {
                // Measure where it ends once stuck, not where it sits at the top of the page.
                offset = Math.max(offset, (parseFloat(style.top) || 0) + el.offsetHeight);
            }
        });
        root.style.setProperty('--ts-docs-offset', Math.max(0, Math.round(offset)) + 'px');
    };
    updateOffset();
    window.addEventListener('resize', updateOffset);

    /* ---------------------------------------------------------------------------
     * Deep links: open the targeted subsection (or topic) and scroll to it
     * ------------------------------------------------------------------------- */
    // Topic the user just jumped to. The scrollspy keeps it active until the user scrolls
    // themselves: short topics at the bottom of the page can never reach the top.
    let pinnedTopic = null;

    const showTarget = (id, smooth) => {
        const target = id ? document.getElementById(id) : null;
        if (!target || !root.contains(target)) {
            return false;
        }
        if (target.closest('[hidden]')) {
            // Filtered away by the current search: drop the search so the target is visible.
            setQuery('');
        }
        if (target.tagName === 'DETAILS') {
            target.open = true;
        }
        const topic = target.closest('[data-docs-topic]');
        pinnedTopic = topic ? topic.dataset.docsTopic : null;
        updateOffset();
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
        return true;
    };

    window.addEventListener('hashchange', () => showTarget(location.hash.slice(1), true));
    if (location.hash) {
        // Wait a tick so Atum has laid out its toolbar before we measure and scroll.
        window.requestAnimationFrame(() => showTarget(decodeURIComponent(location.hash.slice(1)), false));
    }

    jump.addEventListener('change', () => {
        if (jump.value) {
            history.replaceState(null, '', '#' + jump.value);
            showTarget(jump.value, true);
            jump.value = '';
        }
    });

    /* Copy-link buttons on every topic heading and subsection summary */
    const baseUrl = location.href.split('#')[0];
    const addCopyButton = (host, id) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ts-docs-copylink';
        btn.title = t('COPY_LINK');
        btn.setAttribute('aria-label', t('COPY_LINK'));
        btn.innerHTML = '<span class="fa fa-link" aria-hidden="true"></span>';
        btn.addEventListener('click', (event) => {
            // The button sits inside <summary>: don't let the click toggle the section.
            event.preventDefault();
            event.stopPropagation();
            const url = baseUrl + '#' + id;
            history.replaceState(null, '', '#' + id);
            const done = () => {
                btn.classList.add('is-copied');
                btn.title = t('LINK_COPIED');
                btn.innerHTML = '<span class="fa fa-check" aria-hidden="true"></span>';
                setTimeout(() => {
                    btn.classList.remove('is-copied');
                    btn.title = t('COPY_LINK');
                    btn.innerHTML = '<span class="fa fa-link" aria-hidden="true"></span>';
                }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done, done);
            } else {
                done();
            }
        });
        host.appendChild(btn);
    };

    topics.forEach((topic) => {
        const heading = topic.querySelector('h2');
        if (heading) {
            addCopyButton(heading, topic.id);
        }
    });
    allDetails.forEach((details) => {
        const summary = details.querySelector('summary');
        if (summary && details.id) {
            addCopyButton(summary, details.id);
        }
    });

    /* ---------------------------------------------------------------------------
     * Expand / collapse all, print
     * ------------------------------------------------------------------------- */
    root.querySelectorAll('[data-docs-expand]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const open = btn.dataset.docsExpand === '1';
            allDetails.forEach((details) => {
                if (!details.closest('[hidden]')) {
                    details.open = open;
                }
            });
        });
    });

    let printState = null;
    window.addEventListener('beforeprint', () => {
        printState = allDetails.map((details) => details.open);
        allDetails.forEach((details) => { details.open = true; });
    });
    window.addEventListener('afterprint', () => {
        if (printState) {
            allDetails.forEach((details, i) => { details.open = printState[i]; });
            printState = null;
        }
    });
    root.querySelector('[data-docs-print]').addEventListener('click', () => window.print());

    /* ---------------------------------------------------------------------------
     * Search
     * ------------------------------------------------------------------------- */

    // Case- and accent-insensitive: "categorie" also finds "Catégorie".
    const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    // A term matches at the start of a word, so "vat" finds "VAT" but not "private".
    const termPattern = (term, flags = '') => new RegExp(
        '(^|[^\\p{L}\\p{N}])' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
        'u' + flags
    );

    const clearHighlights = () => {
        root.querySelectorAll('mark.ts-docs-hit').forEach((mark) => {
            const parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    };

    // Wraps every occurrence of the terms in <mark>, returns the number of hits.
    const highlight = (container, terms) => {
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => (node.parentElement.closest('script, style, .ts-docs-copylink, .fa')
                ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        const nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        let hits = 0;
        nodes.forEach((node) => {
            const original = node.nodeValue;
            // Fold char by char so positions in the folded text map back to the original.
            let folded = '';
            const map = [];
            for (let i = 0; i < original.length; i++) {
                const f = fold(original[i]);
                for (let j = 0; j < f.length; j++) {
                    folded += f[j];
                    map.push(i);
                }
            }
            map.push(original.length);

            const ranges = [];
            terms.forEach((term) => {
                const re = termPattern(term, 'g');
                let found;
                while ((found = re.exec(folded)) !== null) {
                    const at = found.index + found[1].length;
                    ranges.push([map[at], map[at + term.length - 1] + 1]);
                }
            });
            if (!ranges.length) {
                return;
            }

            ranges.sort((a, b) => a[0] - b[0]);
            const merged = [];
            ranges.forEach((r) => {
                const last = merged[merged.length - 1];
                if (last && r[0] <= last[1]) {
                    last[1] = Math.max(last[1], r[1]);
                } else {
                    merged.push(r.slice());
                }
            });

            const fragment = document.createDocumentFragment();
            let pos = 0;
            merged.forEach(([start, end]) => {
                if (start > pos) {
                    fragment.appendChild(document.createTextNode(original.slice(pos, start)));
                }
                const mark = document.createElement('mark');
                mark.className = 'ts-docs-hit';
                mark.textContent = original.slice(start, end);
                fragment.appendChild(mark);
                pos = end;
                hits++;
            });
            if (pos < original.length) {
                fragment.appendChild(document.createTextNode(original.slice(pos)));
            }
            node.parentNode.replaceChild(fragment, node);
        });

        return hits;
    };

    let savedOpenState = null;
    let foldedText = null;

    const setQuery = (value) => {
        input.value = value;
        runSearch();
    };

    const runSearch = () => {
        const query = input.value.trim();
        const terms = Array.from(new Set(fold(query).split(/\s+/).filter((term) => term.length > 1)));

        clearHighlights();
        clearBtn.hidden = query === '';

        if (!terms.length) {
            topics.forEach((topic) => { topic.hidden = false; });
            groups.concat(navGroups).forEach((group) => { group.hidden = false; });
            root.querySelectorAll('[data-docs-nav]').forEach((item) => {
                item.hidden = false;
                item.querySelector('.ts-docs-count').hidden = true;
            });
            if (savedOpenState) {
                allDetails.forEach((details, i) => { details.open = savedOpenState[i]; });
                savedOpenState = null;
            }
            noResults.hidden = true;
            status.textContent = hintText;
            return;
        }

        if (!savedOpenState) {
            savedOpenState = allDetails.map((details) => details.open);
        }
        if (!foldedText) {
            foldedText = new Map();
            topics.forEach((topic) => foldedText.set(topic, fold(topic.textContent)));
            allDetails.forEach((details) => foldedText.set(details, fold(details.textContent)));
        }

        const patterns = terms.map((term) => termPattern(term));
        let totalHits = 0;
        let topicHits = 0;

        topics.forEach((topic) => {
            // A topic matches when it contains every word; within it, open the subsections
            // that contain at least one of them.
            const text = foldedText.get(topic);
            const match = patterns.every((re) => re.test(text));
            const item = navItem(topic.dataset.docsTopic);
            const count = item.querySelector('.ts-docs-count');

            topic.hidden = !match;
            item.hidden = !match;

            if (!match) {
                count.hidden = true;
                return;
            }

            topic.querySelectorAll('details').forEach((details) => {
                const detailsText = foldedText.get(details);
                details.open = patterns.some((re) => re.test(detailsText));
            });

            const hits = highlight(topic, terms);
            count.textContent = hits;
            count.hidden = false;
            totalHits += hits;
            topicHits++;
        });

        groups.forEach((group) => {
            group.hidden = !group.querySelector('[data-docs-topic]:not([hidden])');
        });
        navGroups.forEach((group) => {
            group.hidden = !group.querySelector('[data-docs-nav]:not([hidden])');
        });

        if (topicHits === 0) {
            noResults.textContent = t('SEARCH_NONE', query);
            noResults.hidden = false;
            status.textContent = t('SEARCH_NONE', query);
        } else {
            noResults.hidden = true;
            if (totalHits === 1) {
                status.textContent = t('SEARCH_RESULT_ONE');
            } else if (topicHits === 1) {
                status.textContent = t('SEARCH_RESULTS_ONE_TOPIC', totalHits);
            } else {
                status.textContent = t('SEARCH_RESULTS', totalHits, topicHits);
            }
        }
    };

    let timer = null;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 150);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            // The page sits inside Joomla's adminForm: Enter must not submit it.
            event.preventDefault();
            clearTimeout(timer);
            runSearch();
            const first = root.querySelector('mark.ts-docs-hit');
            if (first) {
                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else if (event.key === 'Escape') {
            setQuery('');
        }
    });
    clearBtn.addEventListener('click', () => {
        setQuery('');
        input.focus();
    });

    // "/" focuses the search field, unless the user is already typing somewhere.
    document.addEventListener('keydown', (event) => {
        const el = document.activeElement;
        const typing = el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
        if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey) {
            event.preventDefault();
            input.focus();
            input.select();
        }
    });

    /* ---------------------------------------------------------------------------
     * Scrollspy for the sidebar, back-to-top button
     * ------------------------------------------------------------------------- */
    const setActive = (slug) => {
        root.querySelectorAll('.ts-docs-navlink.active').forEach((link) => {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });
        const item = slug ? navItem(slug) : null;
        if (item) {
            const link = item.querySelector('.ts-docs-navlink');
            link.classList.add('active');
            link.setAttribute('aria-current', 'true');
        }
    };

    let ticking = false;
    const onScroll = () => {
        ticking = false;
        const line = (parseInt(getComputedStyle(root).getPropertyValue('--ts-docs-offset'), 10) || 0) + 80;
        const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
        let current = null;
        topics.forEach((topic) => {
            if (topic.hidden) {
                return;
            }
            const top = topic.getBoundingClientRect().top;
            // At the very bottom the last topics can't scroll up to the line any more;
            // then the last one that has come into view counts as the current one.
            if (top <= line || (atBottom && top < window.innerHeight)) {
                current = topic.dataset.docsTopic;
            }
        });
        if (pinnedTopic && !navItem(pinnedTopic).hidden) {
            current = pinnedTopic;
        }
        if (!current) {
            const firstVisible = topics.find((topic) => !topic.hidden);
            current = firstVisible ? firstVisible.dataset.docsTopic : null;
        }
        setActive(current);
        backToTop.hidden = window.scrollY < 600;
    };
    // Scrolling by the user (not by a jump) releases the pinned topic.
    ['wheel', 'touchmove'].forEach((type) => {
        window.addEventListener(type, () => { pinnedTopic = null; }, { passive: true });
    });
    document.addEventListener('keydown', (event) => {
        if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(event.key)) {
            pinnedTopic = null;
        }
    });

    // Sidebar links: also re-jump when the topic is already in the address bar
    // (clicking the same #hash again fires no hashchange).
    root.querySelectorAll('.ts-docs-navlink').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const id = link.getAttribute('href').slice(1);
            history.replaceState(null, '', '#' + id);
            showTarget(id, true);
            onScroll();
        });
    });

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(onScroll);
        }
    }, { passive: true });
    onScroll();

    backToTop.addEventListener('click', (event) => {
        event.preventDefault();
        pinnedTopic = null;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        history.replaceState(null, '', baseUrl);
    });
})();
