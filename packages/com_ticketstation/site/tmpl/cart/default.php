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
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
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
$totals = OrderTotals::get($ordercode, true);

$itemid = TicketstationFunctions::getSiteItemid();
$link = Route::_('index.php?option=com_ticketstation&view=checkout' . ($itemid ? '&Itemid=' . $itemid : ''));
$shop_on = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

$items   = count($this->items);
$waiters = count($this->waiters);

$token = Session::getFormToken();

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

    <?php echo LayoutHelper::render('cart_lines', ['items' => $this->items, 'waiters' => $this->waiters, 'coords' => $this->coords ?? [], 'config' => $this->config, 'totals' => $totals, 'ordercode' => $ordercode], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

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

            fetch('<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=' + (this.dataset.task || 'buyticket') + '&format=raw', {
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
