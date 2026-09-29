<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketcleaner;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$session  = Factory::getApplication()->getSession();

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_CART') . ' - ' . $app->get('sitename') );
TicketstationFunctions::addSiteStylesheet();
HTMLHelper::_('jquery.framework');

$ordercode = (int) $session->get('ordercode');

## Total for this order:
$totals     = OrderTotals::get($ordercode, true);
$fees       = $totals->fees;
$discount   = $totals->discount;
$ordertotal = $totals->total;

$itemid = TicketstationFunctions::getSiteItemid();
$link = Route::_('index.php?option=com_ticketstation&view=checkout' . ($itemid ? '&Itemid=' . $itemid : ''));
$shop_on = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

$items   = count($this->items);
$waiters = count($this->waiters);

$price = fn ($amount) => (new TicketstationFunctions)->showprice($this->config->priceformat, $amount, $this->config->valuta);
$token = Session::getFormToken();
## Task links are not routed: the SEF router would turn a ticketid into a path segment and
## drop it. The tasks find the menu item for their redirect themselves.
$task  = fn ($query) => htmlspecialchars(Uri::root(true) . '/index.php?option=com_ticketstation&controller=order&' . $query . '&' . $token . '=1', ENT_QUOTES, 'UTF-8');

## One cart line per ticket type, with its quantity; a seat is a line of its own, by seat number.
$lines = Order::cartLines($this->items, $this->coords ?? []);

## Until when the tickets stay reserved (only while the cart has rows the Ticketcleaner removes)
$reservedUntil = Ticketcleaner::reservedUntil($ordercode);

if ($reservedUntil) {
    $local = Factory::getDate('@' . $reservedUntil)->setTimezone(new DateTimeZone($app->get('offset') ?: 'UTC'));
    $today = Factory::getDate('now')->setTimezone(new DateTimeZone($app->get('offset') ?: 'UTC'))->format('Y-m-d', true);

    $reservedUntilText = $local->format('Y-m-d', true) === $today
        ? Text::sprintf('COM_TICKETSTATION_TIME_OCLOCK', $local->format('H:i', true))
        : Date::long($local->format('Y-m-d H:i:s', true), true);
}

$trashIcon = '<svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 1h3a1 1 0 0 1 1 1v1H14a.5.5 0 0 1 0 1h-.54l-.8 9.6A1.5 1.5 0 0 1 11.17 15H4.83a1.5 1.5 0 0 1-1.5-1.4L2.54 4H2a.5.5 0 0 1 0-1h3.5V2a1 1 0 0 1 1-1zm0 2h3V2h-3v1zM6 6.5a.5.5 0 0 0-1 .03l.3 5.5a.5.5 0 0 0 1-.06L6 6.5zm4.97.03a.5.5 0 0 0-1-.06l-.3 5.5a.5.5 0 1 0 1 .06l.3-5.5zM8 6a.5.5 0 0 0-.5.5v5.5a.5.5 0 0 0 1 0V6.5A.5.5 0 0 0 8 6z"/></svg>';

if ($items == 0 && $waiters == 0) {
    ## Nothing in the cart: back to the event list
    $app->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''), false));
}

?>

<div class="ticketstation ticketstation--cart">

    <?php if ($items != 0) { ?>
        <?php echo LayoutHelper::render('steps', ['current' => 2], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>
    <?php } ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_CART'); ?></h1>
    </div>

    <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_YOUR_CART_TEXT'); ?></p>

    <!-- Messages from the quantity buttons; don't remove -->
    <div id="cart-message" class="ts-message" role="status" aria-live="polite" style="display: none;"></div>

    <!-- Replaced as a whole after a quantity change (see the script below) -->
    <div id="ts-cart-lines">

        <?php if ($reservedUntil) { ?>
            <div class="ts-alert ts-reserved-until">
                <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zM7.75 4a.75.75 0 0 0-.75.75V8.3l2.47 2.47a.75.75 0 0 0 1.06-1.06L8.5 7.68V4.75A.75.75 0 0 0 7.75 4z"/></svg>
                <span><?php echo Text::sprintf('COM_TICKETSTATION_RESERVED_UNTIL', '<strong>' . $reservedUntilText . '</strong>'); ?></span>
            </div>
        <?php } ?>

        <table class="ts-table ts-summary ts-cart" id="cart">

            <?php if ($items != 0) { ?>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
                        <th scope="col" class="ts-price"><?php echo Text::_('COM_TICKETSTATION_PRICE'); ?></th>
                    </tr>
                </thead>
            <?php } ?>

            <tbody>
                <?php foreach ($lines as $line) {

                    $row       = $line->rows[0];
                    $quantity  = $line->quantity;
                    $lineTotal = $line->total;
                    $name      = htmlspecialchars($row->eventname . ' - ' . $row->ticketname, ENT_QUOTES, 'UTF-8');

                    ## Within the ticket's minimum and maximum per order
                    $canDecrease = $quantity > max(1, (int) $row->min_qty);
                    $canIncrease = $row->max_qty == 0 || $quantity < (int) $row->max_qty;
                    ?>

                    <tr class="ts-summary__item">
                        <td>
                            <span class="ts-summary__name">
                                <?php echo $name; ?>

                                <?php if ($line->seated) { ?>
                                    <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . htmlspecialchars($line->seat, ENT_QUOTES, 'UTF-8'); ?>
                                <?php } ?>
                            </span>

                            <span class="ts-summary__date"><?php echo Date::long($row->startdate, true); ?></span>

                            <?php if (!$line->seated) { ?>
                                <span class="ts-qty" role="group" aria-label="<?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?>">
                                    <?php if ($canDecrease) { ?>
                                        <a class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-remove
                                           href="<?php echo $task('task=remove&orderid=' . (int) end($line->rows)->orderid); ?>"
                                           aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_DECREASE', $name); ?>">&minus;</a>
                                    <?php } else { ?>
                                        <span class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn is-disabled" aria-hidden="true">&minus;</span>
                                    <?php } ?>

                                    <span class="ts-qty__value"><?php echo $quantity; ?></span>

                                    <button type="button" class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-add
                                            data-ticketid="<?php echo (int) $row->ticketid; ?>" data-eventid="<?php echo (int) $row->eventid; ?>"
                                            aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_INCREASE', $name); ?>"<?php echo $canIncrease ? '' : ' disabled'; ?>>+</button>

                                    <span class="ts-qty__unit">&times; <?php echo $price($row->price); ?></span>
                                </span>
                            <?php } ?>
                        </td>
                        <td class="ts-price">
                            <span class="ts-summary__price-cell">
                                <?php $removeUrl = $line->seated ? $task('task=remove&orderid=' . (int) $row->orderid) : $task('task=removeTicket&ticketid=' . (int) $row->ticketid); ?>
                                <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" data-cart-remove href="<?php echo $removeUrl; ?>"
                                   title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>">
                                    <?php echo $trashIcon; ?>
                                    <span class="ts-visually-hidden"><?php echo $line->seated ? Text::_('COM_TICKETSTATION_REMOVE') : Text::sprintf('COM_TICKETSTATION_REMOVE_LINE', $name); ?></span>
                                </a>
                                <span class="ts-summary__amount"><?php echo $price($lineTotal); ?></span>
                            </span>
                        </td>
                    </tr>

                <?php } ?>

                <?php if (count($this->waiters) != 0) { ?>
                    <tr class="ts-summary__waiting">
                        <td colspan="2">
                            <div class="ts-alert ts-waitinglist-note">
                                <?php echo Text::_('COM_TICKETSTATION_ITEMS_ON_WAITINGLIST'); ?><br />
                                <?php echo Text::_('COM_TICKETSTATION_A_PAYMENT_REQUEST_WILL_BE_SENT'); ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>

                <?php foreach ($this->waiters as $row): ?>
                    <tr id="wait-<?php echo $row->id; ?>" class="ts-summary__item ts-summary__item--waiting">
                        <td>
                            <span class="ts-summary__name">
                                <?php echo htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                            <span class="ts-summary__date"><?php echo Date::long($row->startdate, true); ?></span>
                        </td>
                        <td class="ts-price">
                            <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" data-cart-remove title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>" href="<?php echo $task('task=removeWaiting&id=' . (int) $row->id); ?>">
                                <?php echo $trashIcon; ?>
                                <span class="ts-visually-hidden"><?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?></span>
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr class="ts-summary__subtotal">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></th>
                    <td class="ts-price"><?php echo $price($totals->tickets); ?></td>
                </tr>

                <?php if ($discount > 0): ?>
                    <tr class="ts-summary__discount">
                        <th scope="row"><?php echo Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($totals->discount_type == 1):?> (<?php echo (float) $totals->discount_amount;?>%)<?php endif; ?></th>
                        <td class="ts-price">- <?php echo $price($discount); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($fees > 0 && $totals->fee_type != OrderTotals::FEE_NONE): ?>
                    <tr class="ts-summary__fees">
                        <th scope="row"><?php echo Text::_('COM_TICKETSTATION_FEES'); ?><?php if ($totals->fee_type == OrderTotals::FEE_VARIABLE) { ?> (<?php echo (float) $totals->fee_rate ?>%)<?php } ?></th>
                        <td class="ts-price"><?php echo $price($fees); ?></td>
                    </tr>
                <?php endif; ?>

                <tr class="ts-summary__total">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_CART_TOTAL'); ?></th>
                    <td class="ts-price"><?php echo $price($ordertotal); ?></td>
                </tr>
            </tfoot>
        </table>

    </div>

    <?php if ($this->config->show_remark_field == 1) { ?>

        <div class="ts-field ts-remarks">
            <label class="ts-label" for="remarks"><?php echo Text::_('COM_TICKETSTATION_ENTER_REMARKS'); ?></label>
            <?php ## form="adminForm": the note is sent along with the coupon form and saved there, so
            ## it survives the page reload after redeeming a coupon. ?>
            <textarea class="ts-textarea" rows="3" id="remarks" name="remarks" maxlength="255"<?php echo $this->config->use_coupons ? ' form="adminForm"' : ''; ?>><?php echo htmlspecialchars($this->customerNote, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <p id="chars-remaining" class="ts-field__hint ts-chars-remaining" aria-live="polite"><?php echo Text::_('COM_TICKETSTATION_REMAINING'); ?> <?php echo 255 - mb_strlen($this->customerNote); ?></p>
        </div>

    <?php } else { ?>

        <input type="hidden" name="remarks" id="remarks" value="" />

    <?php } ?>

    <input type="hidden" name="required" id="required" value="<?php echo count($this->requests); ?>" />

    <?php if ($this->config->use_coupons) { ?>

        <form action="<?php echo Route::_('index.php?option=com_ticketstation' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="POST" name="adminForm" id="adminForm" class="ts-coupon">

            <label class="ts-label" for="couponcode"><?php echo Text::_('COM_TICKETSTATION_COUPON_CODE'); ?></label>
            <p class="ts-field__hint"><?php echo Text::_('COM_TICKETSTATION_COUPON_CODE_DESC'); ?></p>

            <div class="ts-inline-form">
                <input name="couponcode" id="couponcode" type="text" class="ts-input" maxlength="50" autocomplete="off" />
                <button name="button" type="submit" class="ts-btn ts-btn--secondary"><?php echo Text::_('COM_TICKETSTATION_SUBMIT_COUPON'); ?></button>
            </div>

            <input type="hidden" name="task" id="coupon" value="coupon" />
            <input type="hidden" name="controller" id="cart" value="checkout" />
            <input type="hidden" name="option" id="option" value="com_ticketstation" />
            <?php echo HTMLHelper::_('form.token'); ?>

        </form>

    <?php } ?>

    <div class="ts-actions">
        <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $shop_on; ?>">
            <?php echo Text::_('COM_TICKETSTATION_CONTINUE_SHOPPING'); ?>
        </a>

        <a class="ts-btn ts-btn--primary ts-btn--next" id="checkout" href="<?php echo $link; ?>">
            <?php echo Text::_('COM_TICKETSTATION_TO_CHECKOUT'); ?>
        </a>
    </div>

</div>

<script>
    (function ($) {

        var max = 255;
        var requestFailedMsg = <?php echo json_encode('<div class="ts-alert ts-alert--danger">' . Text::_('COM_TICKETSTATION_REQUEST_FAILED') . '</div>'); ?>;

        $('#remarks').on('keyup', function() {
            if ($(this).val().length > max) {
                $(this).val($(this).val().substr(0, max));
            }

            $('#chars-remaining').html('<?php echo Text::_('COM_TICKETSTATION_REMAINING'); ?> ' + (max - $(this).val().length));
        });

        // Quantity buttons and remove links: do the change in the background, then swap in the
        // cart lines of the refreshed page (totals, service fee and discount included). Without
        // JavaScript the remove links still work as plain links.
        function showCartMessage(html) {
            $('#cart-message').stop(true, true).html(html).show();
        }

        function applyCartPage(response) {
            return response.text().then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('ts-cart-lines');

                // No cart lines: the cart is empty and the page moved on to the event list.
                if (!fresh) {
                    window.location.href = response.url;
                    return;
                }

                $('#ts-cart-lines').replaceWith(fresh);
                busy = false;
            });
        }

        // One change at a time: a double click must not remove or add two tickets.
        var busy = false;

        function cartBusy(state) {
            busy = state;
            $('#ts-cart-lines').attr('aria-busy', state ? 'true' : 'false').find('button.ts-qty__btn').prop('disabled', state);
        }

        $('.ticketstation--cart').on('click', '[data-cart-remove]', function (e) {
            e.preventDefault();

            if (busy) {
                return;
            }

            cartBusy(true);
            $('#cart-message').hide();

            fetch(this.href, {credentials: 'same-origin', cache: 'no-store'})
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    return applyCartPage(response);
                })
                .catch(function () { showCartMessage(requestFailedMsg); cartBusy(false); });
        });

        $('.ticketstation--cart').on('click', '[data-cart-add]', function () {
            if (busy) {
                return;
            }

            var data = new URLSearchParams({
                amount: 1,
                ticketid: this.dataset.ticketid,
                eventid: this.dataset.eventid,
                ordercode: <?php echo $ordercode; ?>
            });
            data.append('<?php echo $token; ?>', 1);

            cartBusy(true);
            $('#cart-message').hide();

            fetch('<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=buyticket&format=raw', {
                method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    return response.json();
                })
                .then(function (result) {
                    // Only errors are shown: the new quantity speaks for itself.
                    if (result.msg && result.msg.indexOf('ts-alert--danger') !== -1) {
                        showCartMessage(result.msg);
                    }

                    return fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store'}).then(applyCartPage);
                })
                .catch(function () { showCartMessage(requestFailedMsg); cartBusy(false); });
        });

        $('#checkout').on('click', function(e) {
            e.preventDefault();

            var required = $("#required").val();

            if (required > 0) {
                $('#additional_required').show();
                return false;
            }

            // No note field (switched off in the Configuration): straight on to checkout.
            if (!$('textarea#remarks').length) {
                document.location.href = '<?php echo $link; ?>';
                return;
            }

            // Save the customer note (an empty note removes an earlier one), then continue.
            var data = {
                content: $('#remarks').val(),
                ordercode: <?php echo $ordercode; ?>
            };
            data['<?php echo $token; ?>'] = 1;

            $.ajax({
                url      : "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=cart&task=saveRemark&format=raw",
                type     : "POST",
                data     : data,
                dataType : 'json',
                cache    : false
            }).done(function(response) {
                if (response.status == 200) {
                    $("#chars-remaining").html(response.msg).addClass('is-saved');
                    setTimeout(function() {
                        document.location.href = '<?php echo $link; ?>';
                    }, 1000);
                } else {
                    noteFailed(response.msg);
                }
            }).fail(function() {
                noteFailed(<?php echo json_encode('<span class="ts-text-danger">' . Text::_('COM_TICKETSTATION_SAVING_CONTENT_FAILED') . '</span>'); ?>);
            });

            // The order matters more than the note: say it wasn't saved, but never block checkout.
            function noteFailed(msg) {
                $("#chars-remaining").html(msg);
                setTimeout(function() {
                    document.location.href = '<?php echo $link; ?>';
                }, 2500);
            }

        });

    })(jQuery);
</script>
