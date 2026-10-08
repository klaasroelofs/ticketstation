/**
 * Combined checkout page (Configuration > Checkout layout).
 *
 * Quantity buttons, remove links and the coupon form work in the background. After each change the
 * page asks the server for the cart lines and the payment block as they are now and swaps them in,
 * so totals, service fee, discount and the amount on the pay button always come from the server.
 * Without JavaScript the remove links and the coupon form still work as plain links and a plain
 * form. The details form is never touched, so what the customer typed stays.
 */
(function () {
    'use strict';

    var root = document.querySelector('.ts-checkout');

    if (!root) {
        return;
    }

    var options = root.dataset;
    var busy = false;
    var refocus = null;
    var message = document.getElementById('ts-cart-message');

    function show(box, html) {
        if (!box) {
            return;
        }

        box.innerHTML = html || '';
        box.hidden = !html;
    }

    function failed() {
        var alert = document.createElement('div');

        alert.className = 'ts-alert ts-alert--danger';
        alert.textContent = options.failed;
        show(message, '');
        message.appendChild(alert);
        message.hidden = false;
    }

    function setBusy(state) {
        busy = state;

        var lines = document.getElementById('ts-cart-lines');

        if (lines) {
            lines.setAttribute('aria-busy', state ? 'true' : 'false');

            Array.prototype.forEach.call(lines.querySelectorAll('button.ts-qty__btn'), function (button) {
                button.disabled = state;
            });
        }
    }

    // Swaps in the fresh cart lines and payment block; an empty cart moves on to the event list.
    function apply(data) {
        if (data.empty) {
            window.location.href = data.redirect;

            return false;
        }

        var pay = document.getElementById('ts-checkout-pay');
        var chosen = pay ? pay.querySelector('input[name="method"]:checked') : null;
        var chosenValue = chosen ? chosen.value : null;

        // A folded-out seat list stays open, so several seats can be removed in a row
        var openSeats = Array.prototype.map.call(document.querySelectorAll('#ts-cart-lines details[open][data-seats]'), function (details) {
            return details.dataset.seats;
        });

        document.getElementById('ts-cart-lines').outerHTML = data.lines;

        openSeats.forEach(function (id) {
            var details = document.querySelector('#ts-cart-lines details[data-seats="' + id + '"]');

            if (details) {
                details.open = true;
            }
        });
        pay.outerHTML = data.pay;

        // The payment methods come back unchosen: keep the customer's choice
        if (chosenValue !== null) {
            Array.prototype.forEach.call(document.querySelectorAll('#ts-checkout-pay input[name="method"]'), function (radio) {
                radio.checked = radio.value === chosenValue;
            });
        }

        // Replacing the lines drops the focus (the button that was just pressed is gone): put it
        // back on the button's replacement
        var again = refocus ? document.querySelector('#ts-cart-lines ' + refocus) : null;

        refocus = null;

        if (again && !again.disabled) {
            again.focus();
        }

        return true;
    }

    function getJson(url) {
        return fetch(url, {credentials: 'same-origin', cache: 'no-store'}).then(function (response) {
            if (!response.ok) {
                throw new Error(response.status);
            }

            return response.json();
        });
    }

    function refresh() {
        return getJson(options.refreshUrl).then(function (data) {
            // A problem that came with the change itself (the maximum was reached) stays on screen
            if (data.messages) {
                show(message, data.messages);
            }

            apply(data);
        });
    }

    // One change at a time: a double click must not remove or add two tickets.
    function change(action) {
        if (busy) {
            return;
        }

        setBusy(true);
        show(message, '');

        action()
            .then(refresh)
            .catch(failed)
            .then(function () { setBusy(false); });
    }

    root.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-cart-remove]');
        var add = event.target.closest('[data-cart-add]');

        if (remove) {
            event.preventDefault();

            // The task does its work and then redirects to the cart page; that redirect is not
            // followed. Sent as a POST: a GET to this address is first redirected by Joomla to the
            // SEF address, which would cut the request short before the task has run.
            change(function () {
                return fetch(remove.href, {method: 'POST', body: new URLSearchParams(), credentials: 'same-origin', cache: 'no-store', redirect: 'manual'});
            });
        } else if (add) {
            refocus = '[data-cart-add][data-ticketid="' + add.dataset.ticketid + '"]';

            change(function () {
                var data = new URLSearchParams({
                    amount: 1,
                    ticketid: add.dataset.ticketid,
                    eventid: add.dataset.eventid,
                    ordercode: options.ordercode
                });

                data.append(options.token, 1);

                // A waiting-list line adds a signup with the waitinglist task instead of buying a ticket
                var url = add.dataset.task ? options.buyUrl.replace('task=buyticket', 'task=' + add.dataset.task) : options.buyUrl;

                return fetch(url, {method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store'})
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error(response.status);
                        }

                        return response.json();
                    })
                    .then(function (result) {
                        // Only errors are shown: the new quantity speaks for itself.
                        if (result.msg && result.msg.indexOf('ts-alert--danger') !== -1) {
                            show(message, result.msg);
                        }
                    });
            });
        }
    });

    // Coupon: sent in the background, with the note, as the page does not reload
    var couponForm = document.getElementById('ts-coupon-form');

    if (couponForm) {
        var couponMessage = document.getElementById('ts-coupon-message');

        couponForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (busy) {
                return;
            }

            var data = new FormData(couponForm);
            var remarks = document.getElementById('remarks');

            if (remarks) {
                data.append('remarks', remarks.value);
            }

            setBusy(true);
            show(couponMessage, '');

            fetch(options.couponUrl, {method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store'})
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    return response.json();
                })
                .then(function (result) {
                    show(couponMessage, result.messages);

                    if (result.ok) {
                        couponForm.elements.couponcode.value = '';
                    }

                    apply(result);
                })
                .catch(function () {
                    var alert = document.createElement('div');

                    alert.className = 'ts-alert ts-alert--danger';
                    alert.textContent = options.failed;
                    show(couponMessage, '');
                    couponMessage.appendChild(alert);
                    couponMessage.hidden = false;
                })
                .then(function () { setBusy(false); });
        });
    }

    // The note: the characters that are left
    var remarks = document.getElementById('remarks');
    var remaining = document.getElementById('chars-remaining');

    if (remarks && remaining) {
        var template = remaining.textContent.replace(/\d+\s*$/, '');

        remarks.addEventListener('input', function () {
            remaining.textContent = template + (remarks.maxLength - remarks.value.length);
        });
    }
})();
