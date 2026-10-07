/**
 * Suggests a correction when the email domain looks like a typo of a common one
 * (gmial.com -> gmail.com). Only a hint: the customer can ignore it.
 */
(function () {
    var domains = [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.nl', 'outlook.com', 'outlook.nl',
        'live.com', 'live.nl', 'yahoo.com', 'yahoo.nl', 'icloud.com', 'me.com', 'msn.com',
        'ziggo.nl', 'kpnmail.nl', 'planet.nl', 'xs4all.nl', 'home.nl', 'online.nl', 'telfort.nl',
        'protonmail.com', 'proton.me', 'gmx.com', 'gmx.net', 'web.de', 't-online.de', 'gmx.de',
        'hotmail.co.uk', 'btinternet.com', 'orange.fr', 'free.fr', 'wanadoo.fr', 'skynet.be', 'telenet.be'
    ];

    function distance(a, b) {
        var prev = [], cur, i, j;

        for (j = 0; j <= b.length; j++) { prev[j] = j; }

        for (i = 1; i <= a.length; i++) {
            cur = [i];

            for (j = 1; j <= b.length; j++) {
                cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
            }

            prev = cur;
        }

        return prev[b.length];
    }

    function suggest(domain) {
        var best = null, bestDistance = 3, d, i;

        for (i = 0; i < domains.length; i++) {
            if (domains[i] === domain) { return null; }

            d = distance(domain, domains[i]);

            if (d < bestDistance) { best = domains[i]; bestDistance = d; }
        }

        // One slip for short domains, at most two for longer ones.
        return best && bestDistance <= (domain.length > 8 ? 2 : 1) ? best : null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('emailaddress'),
            hint  = document.getElementById('emailaddress-suggestion');

        if (!input || !hint) { return; }

        var text   = hint.getAttribute('data-text'),
            accept = hint.getAttribute('data-accept');

        function check() {
            var value = input.value.trim(), at = value.lastIndexOf('@'), match;

            hint.textContent = '';

            if (at < 1) { return; }

            match = suggest(value.slice(at + 1).toLowerCase());

            if (!match) { return; }

            var corrected = value.slice(0, at + 1) + match,
                button    = document.createElement('button');

            hint.appendChild(document.createTextNode(text.replace('%s', corrected) + ' '));
            button.type      = 'button';
            button.className = 'ts-btn ts-btn--secondary ts-btn--sm';
            button.textContent = accept;
            button.addEventListener('click', function () {
                input.value = corrected;
                hint.textContent = '';
                input.focus();
            });
            hint.appendChild(button);
        }

        input.addEventListener('blur', check);
        input.addEventListener('input', function () { hint.textContent = ''; });
    });
})();
