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

$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( 'Tickets kiezen - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## Redirection link in JRoute:
$gotocart = Route::_('index.php?view=cart');

## Total for this order:
$getAmount = new getAmount();
$ordertotal = $getAmount->_getAmount($ordercode);
$fees = $getAmount->_getFees($ordercode);
//$ordertotal = $total-$fees;

## Determine available tickets
$available_tickets = $this->items->starting_total_tickets - $this->soldtickets;

## Calculate percentage available tickets
$percentage_available = round((($available_tickets / $this->items->starting_total_tickets) * 100), 0);

## Load Ticketstation functions
$TicketstationFunctions = new TicketstationFunctions();
?>

<script src="https://code.jquery.com/jquery-latest.min.js"></script>

<script type="text/javascript">
    jQuery(document).ready(function() {

        jQuery('head').append("<style>ul.checkout-bar:before {width:11%;} ul.checkout-bar li.active:before {background: #BB2721;} ul.checkout-bar li.active {color: #BB2721;}</style>");

    });
</script>

<div class="row ticketstation">
    <div class="col-12">
        <div>
            <div class="checkout-wrap">
                <ul class="checkout-bar first">

                    <li class="active"><span class="progress-bar-text">Tickets kiezen</span></li>

                    <li class="next"><span class="progress-bar-text">Winkelmand</span></li>

                    <li class=""><span class="progress-bar-text">Bestelgegevens</span></li>

                    <li class=""><span class="progress-bar-text">Betalen</span></li>

                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row ticketstation">

    <div class="col-xl-9" style="padding-left: 5px;padding-right: 5px;">

        <h2 class="ticketmaster-header"><strong>Tickets kiezen</strong></h2>

        <div class="ticketmaster_event_info">
            <h4><strong>Ticketinformatie:</strong></h4>
            <table>
                <tr>
                    <td width="130px" style="font-weight:bold;">Evenement:</td>
                    <td><?php echo htmlspecialchars($this->items->eventname, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td style="padding-right:5px;font-weight:bold;">Datum:</td>
                    <td><?php echo date('d-m-Y H:i', strtotime($this->items->startdate)); ?></td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Locatie:</td>
                    <td><?php echo $this->items->venue; ?> - <?php echo $this->items->city; ?></td>
                </tr>
            </table>

            <?php if ($this->config->show_quantity_eventlist == 1) { ?>

                <h4><strong>Tickets beschikbaar:</strong></h4>

                <div id="percentage-available" class="percentage-available" style="max-width: 500px;">

                    <?php if($available_tickets <= 0) { ?>

                        <div id="percentage-available-bar" class="percentage-available-bar percentage-available-bar-striped  percentage-available-bar-bg-soldout" style="width:100%;" role="progressbar" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100"><span id="percentage-available-bar-text">Uitverkocht</span>
                        </div>

                    <?php } elseif($percentage_available < 11) { ?>

                        <div id="percentage-available-bar" class="percentage-available-bar percentage-available-bar-striped  percentage-available-bar-bg-warning" style="width: <?php echo ($percentage_available < 0.5) ? "1" : $percentage_available; ?>%;" role="progressbar" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100"><span id="percentage-available-bar-text" class="percentage-available-bar-text-warning"><?php echo $percentage_available; ?>%</span>
                        </div>

                    <?php } elseif($percentage_available < 26) { ?>

                        <div id="percentage-available-bar" class="percentage-available-bar percentage-available-bar-striped  percentage-available-bar-bg-caution" style="width: <?php echo $percentage_available; ?>%;" role="progressbar" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100"><span id="percentage-available-bar-text"><?php echo $percentage_available; ?>%</span>
                        </div>

                    <?php } else { ?>

                        <div id="percentage-available-bar" class="percentage-available-bar percentage-available-bar-striped  percentage-available-bar-bg" style="width: <?php echo $percentage_available; ?>%;" role="progressbar" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100"><span id="percentage-available-bar-text"><?php echo $percentage_available; ?>%</span>
                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

            <div style="height:45px; color:#444; text-align:center; padding-bottom:2px;">

                <div id="message" style="display:none;"><!-- Dont remove this container, it is used for ordering messages --></div>

            </div>

        </div>

        <div>

            <?php if (count($this->childs) != 0) { ?>

                <table class="table">
                    <thead>
                    <th>
                    </th>
                    <th>
                        Prijs:
                    </th>
                    <th>
                        Aantal:
                    </th>
                    <th>
                    </th>
                    </thead>
                    <?php foreach ($this->childs as $row ) {

                        ## For the ticket totals -- If parent:
                        if ($row->counter_choice == 0) {
                            $total_tickets = $available_tickets;
                        } else {
                            ## using the child counter:
                            $total_tickets = $row->totaltickets;
                        }

                        ?>

                        <tr>
                            <td style="border-top:none !important;vertical-align: middle;">
                                <div style="font-weight:bold;"><?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td style="border-top:none !important;vertical-align: middle;">
                                <div><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat ,$row->ticketprice, $this->config->valuta); ?></div>
                            </td>
                            <td style="border-top:none !important;vertical-align: middle;">
                                <div style="float:left;">
                                    <select id="qty_<?php echo $row->ticketid;?>">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                    </select>
                                </div>
                            </td>
                            <td style="border-top:none !important;width: 100px;padding-left: 0px;">

                                <?php if ($total_tickets <= 0) { ?>

                                    <div class="btn-ticket-small-disabled pull-right">
                                        <?php echo Text::_('COM_TICKETSTATION_SOLD_OUT2'); ?>
                                    </div>

                                <?php } else { ?>

                                    <?php if (($row->eventpublished == 1) && ($row->ticketpublished == 1)) { ?>

                                        <a class="btn-ticket-small pull-right" onclick="buytickets(<?php echo $row->ticketid;?>,2)">
                                            <span><?php echo Text::_('COM_TICKETSTATION_ORDER'); ?></span>
                                        </a>

                                    <?php } ?>

                                <?php } ?>

                            </td>
                        </tr>
                        <?php if (!empty($row->free_text_1) && ($row->min_qty == 0 && $row->max_qty == 0 )) { ?>
                            <tr>
                                <td colspan="4" style="border-top:none !important;">
                                    <span style="font-weight:normal"><em><?php echo $row->free_text_1; ?></em></span>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if ( $row->min_qty != 0 || $row->max_qty != 0 ) { ?>

                            <?php $minimum = str_replace('%%MIN_AMOUNT%%', $row->min_qty, Text::_('COM_TICKETSTATION_MINIMUM_FOR_ORDER')); ?>
                            <?php $maximum = str_replace('%%MAX_AMOUNT%%', $row->max_qty, Text::_('COM_TICKETSTATION_MAXIMUM_FOR_ORDER')); ?>

                            <tr>
                                <td colspan="4" style="border-top:none !important;">
                                    <?php if (!empty($row->free_text_1)) { ?>
                                        <span style="font-weight:normal"><em><?php echo $row->free_text_1; ?></em></span><br/>
                                    <?php } ?>

                                    <span style="padding-bottom:5px;color:#BB2721;font-weight:bold;">

										<?php if($row->max_qty != 0 && $row->min_qty != 0){ ?>
                                            <?php echo $minimum; ?> <?php echo $row->min_qty; ?> || <?php echo $maximum; ?> <?php echo $row->max_qty; ?>
                                        <?php }else if($row->max_qty != 0 && $row->min_qty == 0){ ?>
                                            <?php echo $maximum; ?>
                                        <?php }else if($row->max_qty == 0 && $row->min_qty != 0){ ?>
                                            <?php echo $minimum; ?> <?php echo $row->min_qty; ?>
                                        <?php } ?>

									</span>
                                </td>
                            </tr>

                        <?php } ?>


                    <?php } ?>

                </table>

            <?php } else { ?>

                <table class="table">
                    <thead>
                    <th>
                    </th>
                    <th>
                        Prijs:
                    </th>
                    <th>
                        Aantal:
                    </th>
                    <th>
                    </th>
                    </thead>
                    <tr>
                        <td style="border-top:none !important;vertical-align: middle;">
                            <div style="font-weight:bold;"><?php echo htmlspecialchars($this->items->ticketname, ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td style="border-top:none !important;vertical-align: middle;">
                            <div><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat ,$this->items->ticketprice, $this->config->valuta); ?></div>
                        </td>
                        <td style="border-top:none !important;vertical-align: middle;">
                            <div style="float:left;">
                                <select id="qty_<?php echo $this->items->ticketid;?>">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                </select>
                            </div>
                        </td>
                        <td style="border-top:none !important;width: 100px;padding-left: 0px;">

                            <?php if ($available_tickets <= 0) { ?>

                                <?php if ($this->config->show_waitinglist == 1 && ($this->items->eventpublished == 1) && ($this->items->ticketpublished == 1)) { ?>

                                    <a class="btn-ticket-small pull-right" onclick="waitinglist(<?php echo $this->items->ticketid;?>)">
                                        <span><?php echo Text::_('COM_TICKETSTATION_JOIN_WAITINGLIST'); ?></span>
                                    </a>

                                <?php } else { ?>

                                    <div class="btn-ticket-small-disabled pull-right">
                                        <?php echo Text::_('COM_TICKETSTATION_SOLD_OUT2'); ?>
                                    </div>

                                <?php } ?>

                            <?php } else { ?>

                                <?php if (($this->items->eventpublished == 1) && ($this->items->ticketpublished == 1)) { ?>

                                    <a class="btn-ticket-small pull-right" onclick="buytickets(<?php echo $this->items->ticketid;?>,2)">
                                        <span><?php echo Text::_('COM_TICKETSTATION_ORDER'); ?></span>
                                    </a>

                                <?php } ?>

                            <?php } ?>

                        </td>
                    </tr>
                    <?php if (($percentage_available < 0.5) && ($available_tickets > 0)) { ?>
                        <tr>
                            <td colspan="4" style="border-top:none !important;background-color: antiquewhite; text-align: center;">
                                <span style="font-weight:normal;color:#BB2721;"><em>Nog slechts enkele tickets beschikbaar!</em></span>
                            </td>
                        </tr>

                    <?php } ?>

                    <?php if (!empty($this->items->free_text_1) && ($this->items->min_qty == 0 && $this->items->max_qty == 0 )) { ?>
                        <tr>
                            <td colspan="4" style="border-top:none !important;">
                                <span style="font-weight:normal;"><em><?php echo $this->items->free_text_1; ?></em></span>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ( $this->items->min_qty != 0 || $this->items->max_qty != 0 ) { ?>

                        <?php $minimum = str_replace('%%MIN_AMOUNT%%', $this->items->min_qty, Text::_('COM_TICKETSTATION_MINIMUM_FOR_ORDER')); ?>
                        <?php $maximum = str_replace('%%MAX_AMOUNT%%', $this->items->max_qty, Text::_('COM_TICKETSTATION_MAXIMUM_FOR_ORDER')); ?>

                        <tr>
                            <td colspan="4" style="border-top:none !important;">
                                <?php if (!empty($this->items->free_text_1)) { ?>
                                    <span style="font-weight:normal"><em><?php echo $this->items->free_text_1; ?></em></span><br/>
                                <?php } ?>

                                <span style="padding-bottom:5px;color:#BB2721;font-weight:bold;">

									<?php if($this->items->max_qty != 0 && $this->items->min_qty != 0){ ?>
                                        <?php echo $minimum; ?> || <?php echo $maximum; ?>
                                    <?php }else if($this->items->max_qty != 0 && $this->items->min_qty == 0){ ?>
                                        <?php echo $maximum; ?>
                                    <?php }else if($this->items->max_qty == 0 && $this->items->min_qty != 0){ ?>
                                        <?php echo $minimum; ?>
                                    <?php } ?>

								</span>
                            </td>
                        </tr>

                    <?php } ?>

                </table>

            <?php } ?>



            <div>

                <?php if (count($this->ordered) == 0) {
                    $style_continue = 'display: none;';
                } else {
                    $style_continue = '';
                } ?>
                <div id="continue-button" style="<?= $style_continue; ?>">
                    <a class="btn btn-primary pull-right" onClick="location.href='<?php echo $gotocart; ?>'">
                        <span>Verder</span>
                    </a>
                </div>

                <a class="btn btn-primary pull-left" onClick="history.back()">
                    <span>Terug</span>
                </a>

            </div>

        </div>
    </div>

    <div class="col-xl-3 ticketmaster_sidebar">
        <div class="module">
            <div class="module-inner">
                <h3 class="module-title "><?php echo Text::_('COM_TICKETSTATION_CART'); ?></h3>
                <div class="module-ct">
                    <div id="ticketmaster-cartdetails">
                        <div id="cart-information" class="cart-information">

                            <?php if ($this->ticket->total == 0) { ?>

                                <p><strong><?php echo Text::_('COM_TICKETSTATION_EMPTY_CART'); ?></strong></p>

                            <?php } else { ?>

                                <?php
                                if ($this->ticket->total > 1) {
                                    $tickets = Text::_('COM_TICKETSTATION_TICKETS');
                                } else {
                                    $tickets = Text::_('COM_TICKETSTATION_TICKET');
                                } ?>

                                <table style="width: 250px;">
                                    <tr>
                                        <td><?php  echo $this->ticket->total .' '.$tickets; ?></td>
                                        <td style="text-align: right;"><?php  echo (new TicketstationFunctions)->showprice($this->config->priceformat, ($ordertotal - $fees), $this->config->valuta); ?></td>
                                    </tr>
                                    <tr style="height: 40px;">
                                        <td><?php echo Text::_('COM_TICKETSTATION_FEES'); ?></td>
                                        <td style="text-align: right;"><?php  echo (new TicketstationFunctions)->showprice($this->config->priceformat, $fees, $this->config->valuta); ?></td>
                                    </tr>
                                    <tr style="border-top: 1px solid #aaa;">
                                        <td><strong><?php  echo Text::_('COM_TICKETSTATION_ORDERTOTAL_CART'); ?></strong></td>
                                        <td style="text-align: right;"><strong><?php  echo (new TicketstationFunctions)->showprice($this->config->priceformat, $ordertotal, $this->config->valuta); ?></strong></td>
                                    </tr>
                                </table>

                            <?php } ?>

                        </div>

                        <div id="cart-information" class="cart-box">

                            <div id="cart-information-loader-2" style="width:100%; height:20px; margin-top:5px;">
                                <div id = "cart-information-loader" style="display: none; margin:0px;" align="center">
                                    <img src="components/com_ticketstation/assets/images/ajaxloader.gif" height="15px" />
                                </div>
                            </div>

                        </div>

                        <div id="cart-information-loader-1" style="font-size:115%; padding-top: 10px; width:15%; float:right; margin-top:2px; display: none;">
                            <img src="components/com_ticketstation/assets/images/ajax-loader.gif" height="20px" />
                        </div>

                        <?php if (count($this->ordered) == 0) {
                            $style_cart_button_div = 'display: none; margin-top: 20px;';
                        } else {
                            $style_cart_button_div = 'margin-top: 20px;';
                        } ?>

                        <div id="to-cart-button" style="<?= $style_cart_button_div; ?>">
                            <a class="btn btn-primary pull-right" onClick="location.href='<?php echo $gotocart; ?>'">
                                <span>Bekijken</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    function updateCart(){

        jQuery('#cart-information-loader').show();

        var order = 'ordercode=' + <?php echo $ordercode; ?> ;

        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "/index.php?option=com_ticketstation&controller=order&task=itemcount&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                jQuery("#basket-item-count").html(html);
                if (html !== '0') {
                    jQuery("#ticketstation_basket_module").show(0);
                } else {
                    jQuery("#ticketstation_basket_module").hide(0);
                }
            }
        });

        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "/index.php?option=com_ticketstation&controller=order&task=updatecart&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                //if process.php returned 1/true (send mail success)
                jQuery("#cart-information").html(html);
                jQuery('#cart-information-loader').delay(500).hide(0);
                jQuery("#seatselection").delay(500).show(0);
                if (!html.includes('empty_cart')) {
                    jQuery("#to-cart-button").show(0);
                    jQuery("#continue-button").show(0);
                } else {
                    jQuery("#to-cart-button").hide(0);
                    jQuery("#continue-button").hide(0);
                }

            }
        });

    }

    function updateAvailable(){

        jQuery('#percentage-available-bar').addClass('percentage-available-bar-loading');

        var ticketid = 'ticketid=' + <?php echo $this->items->ticketid; ?> ;

        jQuery("#percentage-available-bar").delay(1000).queue(function() {
            jQuery.ajax({
                //this is the php file that processes the data and send mail
                url: "/index.php?option=com_ticketstation&controller=order&task=updateavailable&format=raw",
                //POST method is used
                type: "POST",
                //pass the data
                data: ticketid,
                //Do not cache the page
                cache: false,
                //success
                success: function (html) {
                    jQuery("#percentage-available-bar").css("width", html).removeClass('percentage-available-bar-loading');
                    jQuery("#percentage-available-bar-text").html(html);
                }
            });
            jQuery("#percentage-available-bar").dequeue();
        });

    }

    function buytickets(ticket){

        var inputdata = jQuery("#qty_"+ticket).val();

        //organize the data properly
        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
        var data = 'amount=' + inputdata + '&ticketid=' + ticket +  '&ordercode='
            + <?php echo $ordercode; ?> + '&togo='  + <?php echo $available_tickets; ?> + '&eventid=' + <?php echo $this->items->eventid; ?> + '&' + tokenName + '=1';

        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "/index.php?option=com_ticketstation&controller=order&task=buyticket&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            // data type = json
            dataType: 'json',
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                // We're done, show data

                if(html.status == 666) {
                    jQuery( "#message" ).show();
                    jQuery( '#message' ).html(html.msg);
                    updateCart();
                    updateAvailable();
                    jQuery( "#message" ).delay(3000).fadeOut(500);

                }else{
                    jQuery( "#message" ).show();
                    jQuery( "#message" ).html(html.msg);
                    updateCart();
                    updateAvailable();
                    jQuery( "#message" ).delay(3000).fadeOut(500);

                }

            },
            error:function (xhr, ajaxOptions, thrownError){
            }
        });


    }

    function waitinglist(items){

        var inputdata = jQuery("#qty_"+items).val();

        //organize the data properly
        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
        var data = 'amount=' + inputdata + '&ticketid=' + items +  '&ordercode='
            + <?php echo $ordercode; ?> + '&togo='  + <?php echo $available_tickets; ?> + '&eventid=' + <?php echo $this->items->eventid; ?> + '&' + tokenName + '=1';

        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "/index.php?option=com_ticketstation&controller=order&task=waitinglist&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            // data type = json
            dataType: 'json',
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                // We're done, show data

                if(html.status == 666) {

                    jQuery( '#message' ).html(html.msg);
                    updateCart();

                }else{
                    jQuery( "#message" ).html(html.msg);
                    updateCart();

                }

            },
            error:function (xhr, ajaxOptions, thrownError){
            }
        });


    }

</script>