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
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$session  = Factory::getApplication()->getSession();

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_CART') . ' - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

## Total for this order:
$total      = (new getAmount)->_getAmount($session->get('ordercode'));
$fees       = (new getAmount)->_getFees($session->get('ordercode'));
$discount   = (new getAmount)->_getDiscount($session->get('ordercode'));

$ordertotal = $total;

$itemid = TicketstationFunctions::getSiteItemid();
$link = Route::_('index.php?option=com_ticketstation&view=checkout' . ($itemid ? '&Itemid=' . $itemid : ''));
$shop_on = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

$items   = count($this->items);
$waiters = count($this->waiters);

$trashIcon = '<svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 1h3a1 1 0 0 1 1 1v1H14a.5.5 0 0 1 0 1h-.54l-.8 9.6A1.5 1.5 0 0 1 11.17 15H4.83a1.5 1.5 0 0 1-1.5-1.4L2.54 4H2a.5.5 0 0 1 0-1h3.5V2a1 1 0 0 1 1-1zm0 2h3V2h-3v1zM6 6.5a.5.5 0 0 0-1 .03l.3 5.5a.5.5 0 0 0 1-.06L6 6.5zm4.97.03a.5.5 0 0 0-1-.06l-.3 5.5a.5.5 0 1 0 1 .06l.3-5.5zM8 6a.5.5 0 0 0-.5.5v5.5a.5.5 0 0 0 1 0V6.5A.5.5 0 0 0 8 6z"/></svg>';

?>

    <script language="javascript">

        var max = 255;
        jQuery(document).ready(function() {
            jQuery('#remarks').keyup(function() {
                if (jQuery(this).val().length > max) {
                    jQuery(this).val(jQuery(this).val().substr(0, max));
                }

                jQuery('#chars-remaining').html('<?php echo Text::_('COM_TICKETSTATION_REMAINING'); ?> ' + (max - jQuery(this).val().length));
            });
        });

        jQuery(document).ready(function() {

            jQuery('#checkout').click(function(e) {
                e.preventDefault();

                var required = jQuery("#required").val();

                if (required > 0) {
                    jQuery('#additional_required').show();
                    return false;
                }

                // No note field (switched off in the Configuration): straight on to checkout.
                if (!jQuery('textarea#remarks').length) {
                    document.location.href = '<?php echo $link; ?>';
                    return;
                }

                // Save the customer note (an empty note removes an earlier one), then continue.
                var data = {
                    content: jQuery('#remarks').val(),
                    ordercode: <?php echo (int) $session->get('ordercode'); ?>
                };
                data['<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>'] = 1;

                jQuery.ajax({
                    url      : "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=cart&task=saveRemark&format=raw",
                    type     : "POST",
                    data     : data,
                    dataType : 'json',
                    cache    : false
                }).done(function(response) {
                    if (response.status == 200) {
                        jQuery("#chars-remaining").html(response.msg).addClass('is-saved');
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
                    jQuery("#chars-remaining").html(msg);
                    setTimeout(function() {
                        document.location.href = '<?php echo $link; ?>';
                    }, 2500);
                }

            });

        });

    </script>

<?php if ($items == 0 && $waiters == 0) {
    ## Nothing in the cart: back to the event list
    header('Location: '.$shop_on);
    die();
} ?>

<div class="ticketstation ticketstation--cart">

    <?php if ($items != 0) { ?>
        <?php echo LayoutHelper::render('steps', ['current' => 2], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>
    <?php } ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_CART'); ?></h1>
    </div>

    <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_YOUR_CART_TEXT'); ?></p>

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
            <?php foreach ($this->items as $row): ?>

                <tr id="row-<?php echo $row->orderid; ?>" class="ts-summary__item">
                    <td>
                        <span class="ts-summary__name">
                            <?php echo htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?>

                            <?php if (isset($row->seat_sector) ? $row->seat_sector : 0 != 0): ?>
                                <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . checkSeat($row->orderid, $this->coords); ?>
                            <?php endif; ?>
                        </span>

                        <span class="ts-summary__date">
                            <?php echo Text::_('COM_TICKETSTATION_DATE'); ?>: <?php echo date($this->config->dateformat, strtotime($row->startdate)); ?>

                            <?php if (isset($row->show_end_date) ? $row->show_end_date : 0 == 1): ?>
                                - <?php echo date($this->config->dateformat, strtotime($row->end_date)); ?>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td class="ts-price">
                        <span class="ts-summary__price-cell">
                            <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>" href="<?php echo Route::_('index.php?option=com_ticketstation&controller=order&task=remove&orderid=' . $row->orderid . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1' . ($itemid ? '&Itemid=' . $itemid : '')); ?>">
                                <?php echo $trashIcon; ?>
                                <span class="ts-visually-hidden"><?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?></span>
                            </a>
                            <span class="ts-summary__amount"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $row->ticketprice, $this->config->valuta); ?></span>
                        </span>
                    </td>
                </tr>

            <?php endforeach; ?>

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

                            <?php if (isset($row->seat_sector) ? $row->seat_sector : 0 != 0): ?>
                                <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . checkSeat($row->orderid, $this->coords); ?>
                            <?php endif; ?>
                        </span>

                        <span class="ts-summary__date">
                            <?php echo Text::_('COM_TICKETSTATION_DATE'); ?>: <?php echo date($this->config->dateformat, strtotime($row->startdate)); ?>
                            <?php if (isset($row->show_end_date) ? $row->show_end_date : 0 == 1): ?>
                                - <?php echo date($this->config->dateformat, strtotime($row->end_date)); ?>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td class="ts-price">
                        <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>" href="<?php echo Route::_('index.php?option=com_ticketstation&controller=order&task=removeWaiting&id=' . $row->id . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1' . ($itemid ? '&Itemid=' . $itemid : '')); ?>">
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
                <td class="ts-price"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat , ($ordertotal-$fees)+$discount, $this->config->valuta); ?></td>
            </tr>

            <?php if ($discount > 0): ?>
                <tr class="ts-summary__discount">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($this->items[0]->discount_type == 1):?> (<?php echo $this->items[0]->discount_amount;?>%)<?php endif; ?></th>
                    <td class="ts-price">- <?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $discount, $this->config->valuta); ?></td>
                </tr>
            <?php endif; ?>

            <?php if ($fees > 0 && $this->config->variable_transcosts != 2): ?>
                <tr class="ts-summary__fees">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_FEES'); ?><?php if ($this->config->variable_transcosts == '1') { ?> (<?php echo $this->config->transcosts ?>%)<?php } ?></th>
                    <td class="ts-price"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $fees, $this->config->valuta); ?></td>
                </tr>
            <?php endif; ?>

            <tr class="ts-summary__total">
                <th scope="row"><?php echo Text::_('COM_TICKETSTATION_CART_TOTAL'); ?></th>
                <td class="ts-price"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $ordertotal, $this->config->valuta); ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if ($this->config->show_remark_field == 1) { ?>

        <div class="ts-field ts-remarks">
            <label class="ts-label" for="remarks"><?php echo Text::_('COM_TICKETSTATION_ENTER_REMARKS'); ?></label>
            <textarea class="ts-textarea" rows="3" id="remarks" name="remarks" maxlength="255"><?php echo htmlspecialchars($this->customerNote, ENT_QUOTES, 'UTF-8'); ?></textarea>
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

<?php function checkSeat($value, $seat)
{

    for ($i = 0, $n = count($seat); $i < $n; $i++)
    {

        if ($value == $seat[$i]->orderid)
        {
            if ($seat[$i]->row_name != '')
            {
                $seat_number = $seat[$i]->row_name . $seat[$i]->seatid;
            }
            else
            {
                $seat_number = $seat[$i]->seatid;
            }
        }
    }

    return $seat_number;
}

?>
