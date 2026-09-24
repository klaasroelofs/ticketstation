<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$session  = Factory::getApplication()->getSession();

$button    = 'btn';
$btndanger = 'btn btn-small btn-danger';

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

            jQuery('head').append("<style>ul.checkout-bar li.previous:after {width:100%;} ul.checkout-bar li.active:before {background: #BB2721;} ul.checkout-bar li.active {color: #BB2721;}</style>");

        });

        jQuery(document).ready(function() {

            jQuery('a.delete').click(function(e) {
                e.preventDefault();

                var parent = jQuery(this).parent();
                var orderid = parent.attr('id').replace('tm-cart-price-', '');
                var container = parent.attr('id').replace('tm-cart-price-', 'tm-cart-container');
                var data = 'cid=' + orderid;

                var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
                jQuery.ajax({
                    type      : 'POST',
                    url       : '/index.php?option=com_ticketstation&controller=order&task=remove&format=raw',
                    data      : 'orderid=' + parent.attr('id').replace('tm-cart-price-', '') + '&' + tokenName + '=1',
                    dataType  : 'json',
                    beforeSend: function() {
                        jQuery('#tm-loader').show();
                        jQuery("#row-" + orderid).addClass("error");
                    },
                    success   : function(result) {
                        jQuery("#row-" + orderid).remove();
                        jQuery("#tai" + orderid + "information").hide();
                        //jQuery("#tm-cart-total-price").html(result.total);
                        jQuery("#tm-cart-total-fees").html(result.fees);
                        jQuery('#tm-loader').hide();
                    }
                });

            });

        });

        jQuery(document).ready(function() {

            jQuery('a.deletewaiting').click(function(e) {
                e.preventDefault();

                var parent = jQuery(this).parent();
                var orderid = parent.attr('id').replace('tm-cart-waiting-', '');
                var container = parent.attr('id').replace('tm-cart-waiting-', 'tm-cart-container');
                var data = 'cid=' + orderid;

                var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
                jQuery.ajax({
                    type      : 'POST',
                    url       : '/index.php?option=com_ticketstation&controller=order&task=removeWaiting&format=raw',
                    data      : 'id=' + parent.attr('id').replace('tm-cart-waiting-', '') + '&' + tokenName + '=1',
                    beforeSend: function() {
                        jQuery('#tm-loader').show();
                        jQuery("#wait-" + orderid).addClass("error");
                    },
                    success   : function(result) {
                        jQuery("#wait-" + orderid).remove();
                        jQuery("#tm-cart-total-price").html(result);
                        jQuery('#tm-loader').hide();
                    }
                });

            });

        });

        jQuery(document).ready(function() {

            jQuery('#checkout').click(function(e) {
                e.preventDefault();

                // getting the remarks if available.
                var remarks = jQuery("#remarks").val();
                var required = jQuery("#required").val();

                if (required > 0) {
                    jQuery('#additional_required').show();
                    return false;
                }

                if (remarks == '') {

                    // If remarks is empty, submit now.
                    document.location.href = '<?php echo $link; ?>';

                } else {

                    // Please do AJAX call with data. -- Get post data first.
                    var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
                    var data = 'content=' + remarks + '&ordercode=' + <?php echo $session->get('ordercode'); ?> + '&' + tokenName + '=1';

                    jQuery.ajax({
                        //this is the php file that processes the data and send mail
                        url       : "/index.php?option=com_ticketstation&controller=cart&task=saveRemark&format=raw",
                        //POST method is used
                        type      : "POST",
                        // data:
                        data      : data,
                        // data type = json
                        dataType  : 'json',
                        //Do not cache the page
                        cache     : false,
                        // Before sending the form
                        beforeSend: function() {
                            jQuery("#test").html(' <?php echo Text::_('COM_TICKETSTATION_PLEASE_WAIT'); ?>');
                            jQuery('<img class="inline" style="margin-right:5px;" src="components/com_ticketstation/assets/images/loading.gif" />').prependTo("#test");
                        },
                        // On Success trigger
                        success   : function(html) {

                            if (html.status == 666) {
                                jQuery("#chars-remaining").html(html.msg);
                                jQuery("#inner").html(html.msg);
                            } else {
                                jQuery("#chars-remaining").html(html.msg);
                                jQuery("#chars-remaining").css('color', '#04B404');

                                setTimeout(function() {
                                    document.location.href = '<?php echo $link; ?>';
                                }, 1000);
                            }

                        }
                    });

                }

            });

        });

    </script>

    <div class="row ticketstation">
        <div class="col-12">
            <?php if ($items != 0) { ?>
                <div>
                    <div class="checkout-wrap">
                        <ul class="checkout-bar">

                            <li class="visited previous">
                                <span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS'); ?></span>
                            </li>

                            <li class="active"><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_CART'); ?></span></li>

                            <li class="next"><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_ORDER_DETAILS'); ?></span></li>

                            <li class=""><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_STEP_PAYMENT'); ?></span></li>

                        </ul>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="row ticketstation">
        <div class="col-xl-9">

        <?php if ($items == 0 && $waiters == 0) { ?>

            <?php
                header('Location: '.$shop_on);
                die();
            ?>

            <div style="min-height:250px;">
                <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_YOUR_CART_EMPTY'); ?></strong></h2>

                <p style="margin:30px 0px;"><?php echo Text::_('COM_TICKETSTATION_GO_TO_UPCOMING'); ?></p>

                <a class="btn btn-primary pull-left" onClick="location.href='<?php echo $shop_on; ?>'">
                    <span><?php echo Text::_('COM_TICKETSTATION_AVAILABLE_EVENTS'); ?></span>
                </a>
            </div>

        <?php } else { ?>

            <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_CART'); ?></strong></h2>

            <div id="tm-cart-text">
                <p><?php echo Text::_('COM_TICKETSTATION_YOUR_CART_TEXT'); ?></p>
            </div>

            <div class="failed" style="display: none;">
                <?php echo Text::_('COM_TICKETSTATION_CART_FAILED'); ?>
            </div>

            <div id="ticketmaster-loading" align="center" style="margin-bottom:3px; height:20px;">
                <div id="tm-loader" style=" display: none; ">
                    <img src="components/com_ticketstation/assets/images/ajaxloader.gif" height="20px" />
                </div>
            </div>
            <div>
                <table class="table" id="cart" style="border:none; max-width:100%;">

                    <?php if ($items != 0) { ?>
                        <thead>
                        <th width="60%"><?php echo Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
                        <th width="40%">
                            <div align="right"><?php echo Text::_('COM_TICKETSTATION_PRICE'); ?></div>
                        </th>

                        </thead>
                    <?php } ?>

                    <?php foreach ($this->items as $row): ?>

                        <tr id="row-<?php echo $row->orderid; ?>">
                            <td>
                                <?php echo htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?>

                                <?php if (isset($row->seat_sector) ? $row->seat_sector : 0 != 0): ?>
                                    <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . checkSeat($row->orderid, $this->coords); ?>
                                <?php endif; ?>

                                <br />

                                <?php echo Text::_('COM_TICKETSTATION_DATE'); ?>: <?php echo date($this->config->dateformat, strtotime($row->startdate)); ?>

                                <?php if (isset($row->show_end_date) ? $row->show_end_date : 0 == 1): ?>
                                    - <?php echo date($this->config->dateformat, strtotime($row->end_date)); ?>
                                <?php endif; ?>

                            </td>
                            <td>
                                <div style="text-align: right;">
                                    <a style="margin-right: 10px;" class="btn btn-danger btn-mini" href="<?php echo Route::_('index.php?option=com_ticketstation&controller=order&task=remove&orderid=' . $row->orderid . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1' . ($itemid ? '&Itemid=' . $itemid : '')); ?>">
                                        <span class="fa fa-trash"></span>
                                    </a>
                                    <?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $row->ticketprice, $this->config->valuta); ?>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (count($this->waiters) != 0) { ?>
                        <tr>
                            <td colspan="3">
                                <div class="waitinglist_message">
                                    <?php echo Text::_('COM_TICKETSTATION_ITEMS_ON_WAITINGLIST'); ?><br />
                                    <?php echo Text::_('COM_TICKETSTATION_A_PAYMENT_REQUEST_WILL_BE_SENT'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>

                    <?php foreach ($this->waiters as $row): ?>
                        <tr id="wait-<?php echo $row->id; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8'); ?></strong> - <?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?>

                                <?php if (isset($row->seat_sector) ? $row->seat_sector : 0 != 0): ?>
                                    <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . checkSeat($row->orderid, $this->coords); ?>
                                <?php endif; ?>

                                <br />

                                <?php echo Text::_('COM_TICKETSTATION_DATE'); ?>: <?php echo date($this->config->dateformat, strtotime($row->startdate)); ?>
                                <?php if (isset($row->show_end_date) ? $row->show_end_date : 0 == 1): ?>
                                    - <?php echo date($this->config->dateformat, strtotime($row->end_date)); ?>
                                <?php endif; ?>

                                <div align="center">
                                    <a class="btn btn-danger btn-xs btn-mini" href="<?php echo Route::_('index.php?option=com_ticketstation&controller=order&task=removeWaiting&id=' . $row->id . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1' . ($itemid ? '&Itemid=' . $itemid : '')); ?>">
                                        <span class="fa fa-trash"></span>
                                    </a>
                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr>
                        <td>
                            <div style="text-align:right;font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></div>
                        </td>
                        <td>
                            <div style="font-weight:bold;" align="right"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat , ($ordertotal-$fees)+$discount, $this->config->valuta); ?></div>
                        </td>
                    </tr>

                    <?php if ($discount > 0): ?>

                        <tr>
                            <td>
                                <div style="text-align:right;"><?php echo Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($this->items[0]->discount_type == 1):?> (<?php echo $this->items[0]->discount_amount;?>%)<?php endif; ?>:</div>
                            </td>
                            <td>
                                <div align="right"><sup>-</sup>/<sub>-</sub> <?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $discount, $this->config->valuta); ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($fees > 0 && $this->config->variable_transcosts != 2): ?>
                        <tr>
                            <td>
                                <div style="text-align:right;"><?php echo Text::_('COM_TICKETSTATION_FEES'); ?><?php if ($this->config->variable_transcosts == '1') { ?> (<?php echo $this->config->transcosts ?>%) <?php } ?></div>
                            </td>
                            <td>
                                <div align="right"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $fees, $this->config->valuta); ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td>
                            <div style="text-align:right; font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_CART_TOTAL'); ?></div>
                        </td>
                        <td>
                            <div align="right" style="font-weight:bold;"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $ordertotal, $this->config->valuta); ?></div>
                        </td>
                    </tr>
                </table>

                <div style="clear:both; margin-top: 15px;"></div>

                <div style="clear:both;"></div>

                <?php if ($this->config->show_remark_field == 1) { ?>

                    <div class="remarks_text">

                        <h4><?php echo Text::_('COM_TICKETSTATION_ENTER_REMARKS'); ?></h4>

                        <textarea rows="3" style="width:98%;" id="remarks" name="remarks" maxlength="255"></textarea>
                        <div id="chars-remaining" class="chars-remaining"><?php echo Text::_('COM_TICKETSTATION_REMAINING'); ?> 255</div>

                    </div>

                <?php } else { ?>

                    <input type="hidden" name="remarks" id="remarks" value="" />

                <?php } ?>

                <input type="hidden" name="required" id="required" value="<?php echo count($this->requests); ?>" />

                <?php if ($this->config->use_coupons) { ?>

                    <div style="margin: 35px 0;">

                        <h4><?php echo Text::_('COM_TICKETSTATION_COUPON_CODE'); ?></h4>

                        <div style="margin-bottom:10px;">
                            <?php echo Text::_('COM_TICKETSTATION_COUPON_CODE_DESC'); ?>
                        </div>

                        <form action="index.php" method="POST" name="adminForm" id="adminForm" class="form-inline">

                            <input name="couponcode" id="couponcode" type="text" class="input-medium" style="margin-bottom: 20px;margin-right: 25px;" size="25" maxlength="50" />
                            <input type="hidden" name="task" id="coupon" value="coupon" />
                            <input type="hidden" name="controller" id="cart" value="checkout" />
                            <input type="hidden" name="option" id="option" value="com_ticketstation" />
                            <?php echo HTMLHelper::_('form.token'); ?>

                            <input name="button" type="submit" value="<?php echo Text::_('COM_TICKETSTATION_SUBMIT_COUPON'); ?>" class="btn-ticket-small" />

                        </form>

                    </div>

                <?php } ?>

                <div>

                    <a class="btn btn-primary pull-right" id="checkout">
                        <span><?php echo Text::_('COM_TICKETSTATION_TO_CHECKOUT'); ?></span>
                    </a>
                    <a class="btn btn-primary pull-left" onClick="document.location.href='<?php echo $shop_on; ?>'">
                        <span><?php echo Text::_('COM_TICKETSTATION_CONTINUE_SHOPPING'); ?></span>
                    </a>

                </div>

            </div>

        <?php } ?>

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