<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

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
$document->setTitle( Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS') . ' - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));

## Determine available tickets: for a parent with child tickets the total over all published
## variants, following their counter settings (the same figure as in the upcoming-events list)
$availability      = Availability::summary((int) $this->items->ticketid);
$available_tickets = $availability->available;

## Calculate percentage available tickets
## (guard against a ticket without a capacity, which would divide by zero)
$percentage_available = ($availability->capacity > 0)
    ? round((($available_tickets / $availability->capacity) * 100), 0)
    : 0;

## Venue website link (stored without scheme in the venue form, e.g. "www.example.nl")
$venue_website_url = preg_match('#^https?://#i', $this->items->website) ? $this->items->website : 'https://' . $this->items->website;

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

                    <li class="active"><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS'); ?></span></li>

                    <li class="next"><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_CART'); ?></span></li>

                    <li class=""><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_ORDER_DETAILS'); ?></span></li>

                    <li class=""><span class="progress-bar-text"><?php echo Text::_('COM_TICKETSTATION_STEP_PAYMENT'); ?></span></li>

                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row ticketstation">

    <div class="col-12" style="padding-left: 5px;padding-right: 5px;">

        <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS'); ?></strong></h2>

        <div class="ticketmaster_event_info">
            <h4><strong><?php echo Text::_('COM_TICKETSTATION_TICKET_INFORMATION'); ?>:</strong></h4>
            <table>
                <tr>
                    <td width="130px" style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_EVENT'); ?>:</td>
                    <td><?php echo htmlspecialchars($this->items->eventname, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td style="padding-right:5px;font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_DATE'); ?>:</td>
                    <td><?php echo date('d-m-Y H:i', strtotime($this->items->startdate)); ?></td>
                </tr>
                <?php if ($this->config->show_venue == 1) { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_VENUE'); ?>:</td>
                        <td><?php echo $this->items->venue; ?> - <?php echo $this->items->city; ?></td>
                    </tr>
                <?php } ?>
                <?php if ($this->config->show_venue == 1 && $this->config->show_venue_address == 1 && ($this->items->street != '' || $this->items->zipcode != '')) { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_ADDRESS'); ?>:</td>
                        <td><?php echo htmlspecialchars(trim($this->items->street . ', ' . $this->items->zipcode . ' ' . $this->items->city, ', '), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php } ?>
                <?php if ($this->config->show_venue == 1 && $this->config->show_venue_website == 1 && $this->items->website != '') { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_WEBSITE'); ?>:</td>
                        <td><a href="<?php echo htmlspecialchars($venue_website_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($this->items->website, ENT_QUOTES, 'UTF-8'); ?></a></td>
                    </tr>
                <?php } ?>
            </table>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_description == 1 && trim(strip_tags($this->items->venuedescription)) != '') { ?>
                <div class="ticketstation_venue_description">
                    <?php echo $this->items->venuedescription; ?>
                </div>
            <?php } ?>

            <?php if ($this->config->show_available_tickets == 1) { ?>

                <h4><strong><?php echo Text::_('COM_TICKETSTATION_TICKETS_AVAILABLE'); ?>:</strong></h4>

                <div id="percentage-available" class="percentage-available" style="max-width: 500px;">

                    <?php if($available_tickets <= 0) { ?>

                        <div id="percentage-available-bar" class="percentage-available-bar percentage-available-bar-striped  percentage-available-bar-bg-soldout" style="width:100%;" role="progressbar" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100"><span id="percentage-available-bar-text"><?php echo Text::_('COM_TICKETSTATION_SOLD_OUT2'); ?></span>
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

            <div style="min-height:45px; color:#444; text-align:center; padding-bottom:2px;">

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
                        <?php echo Text::_('COM_TICKETSTATION_PRICE'); ?>:
                    </th>
                    <th>
                        <?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?>:
                    </th>
                    <th>
                    </th>
                    </thead>
                    <?php foreach ($this->childs as $row ) {

                        ## Tickets left for this variant: the shared parent pool or its own cap.
                        $total_tickets = Availability::forPurchase((int) $row->ticketid);

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
                        <?php echo Text::_('COM_TICKETSTATION_PRICE'); ?>:
                    </th>
                    <th>
                        <?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?>:
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
                                <span style="font-weight:normal;color:#BB2721;"><em><?php echo Text::_('COM_TICKETSTATION_FEW_TICKETS_LEFT'); ?></em></span>
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

                <?php if (count($this->ordered) == 0 && $this->waiting == 0) {
                    $style_continue = 'display: none;';
                } else {
                    $style_continue = '';
                } ?>
                <div id="continue-button" style="<?= $style_continue; ?>">
                    <a class="btn btn-primary pull-right" onClick="location.href='<?php echo $gotocart; ?>'">
                        <span><?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?></span>
                    </a>
                </div>

                <a class="btn btn-primary pull-left" onClick="history.back()">
                    <span><?php echo Text::_('COM_TICKETSTATION_BACK'); ?></span>
                </a>

            </div>

        </div>
    </div>
</div>

<script type="text/javascript">

    function updateCart(){

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
                jQuery("#seatselection").delay(500).show(0);
                if (!html.includes('empty_cart') || html.includes('waitinglist_items')) {
                    jQuery("#continue-button").show(0);
                } else {
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

                // #message is display:none by default, so it has to be shown explicitly. It stays
                // visible (no fade-out): it tells the customer to continue to leave their details.
                jQuery( "#message" ).stop(true, true).show();
                jQuery( "#message" ).html(html.msg);
                updateCart();

            },
            error:function (xhr, ajaxOptions, thrownError){
            }
        });


    }

</script>