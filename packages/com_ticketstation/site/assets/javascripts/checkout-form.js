/**
 * The checkout form, on the classic details page and on the combined checkout page: the captcha
 * is started when the page opens, an incomplete form is reported before the captcha runs, and
 * the button can only be used once.
 */
(function () {
    'use strict';

    // The form with the button that places the order (id ts-checkout-submit); that button can only
    // be used once, as a double click must not place the order twice.
    var form = document.getElementById('general');

    if (form) {
        // The captcha (Altcha) starts verifying when the form gets focus. Start it when the page
        // is ready instead, so it is done by the time the customer pays.
        var captcha = form.querySelector('altcha-widget');

        if (captcha) {
            customElements.whenDefined('altcha-widget').then(function () {
                if (captcha.getState() === 'unverified') {
                    captcha.verify();
                }
            });
        }

        // The first field that is not filled in right, not counting the captcha's own checkbox
        // (it is only ticked once the captcha is done)
        function invalidField() {
            return Array.prototype.find.call(form.elements, function (field) {
                return field.willValidate && !(captcha && captcha.contains(field)) && !field.checkValidity();
            });
        }

        // Incomplete form: show what is missing right away, before the captcha (it would first
        // ask the customer to wait while it verifies a form that cannot be sent anyway)
        form.addEventListener('click', function (event) {
            var button = event.target.closest('#ts-checkout-submit');

            if (!button) {
                return;
            }

            var invalid = invalidField();

            if (invalid) {
                event.preventDefault();
                event.stopPropagation();
                form.reportValidity();
                invalid.focus();

                return;
            }

            // The captcha has not finished: wait for it here, instead of the alert it shows
            // ("Verifying... please wait") that the customer has to click away
            if (captcha && typeof captcha.getState === 'function' && captcha.getState() !== 'verified') {
                event.preventDefault();
                event.stopPropagation();
                button.disabled = true;

                var onState = function (stateEvent) {
                    var state = stateEvent.detail && stateEvent.detail.state;

                    if (state === 'verifying') {
                        return;
                    }

                    captcha.removeEventListener('statechange', onState);
                    button.disabled = false;

                    // A moment later: the captcha ticks its own checkbox right after it is done,
                    // and the form is only valid then
                    if (state === 'verified') {
                        setTimeout(function () { form.requestSubmit(); }, 100);
                    }
                };

                captcha.addEventListener('statechange', onState);

                if (captcha.getState() !== 'verifying') {
                    captcha.verify();
                }
            }
        }, true);

        form.addEventListener('submit', function (event) {
            if (invalidField()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                form.reportValidity();

                return;
            }

            // After all other listeners: a captcha may still hold the form back and send it later
            setTimeout(function () {
                var button = document.getElementById('ts-checkout-submit');

                if (button && !event.defaultPrevented) {
                    button.disabled = true;
                }
            }, 0);
        }, true);
    }

    // Back button after the payment provider: the page returns from the cache with the button off
    window.addEventListener('pageshow', function (event) {
        var button = document.getElementById('ts-checkout-submit');

        if (event.persisted && button) {
            button.disabled = false;
        }
    });
})();
