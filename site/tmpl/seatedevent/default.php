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
$document->setTitle( 'Stoelen selecteren - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## The image of the seat chart
$seatchart_png = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->ticketdetails->ticketid.'.png';
$image_png = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->ticketdetails->ticketid.'.png';
$seatchart_jpg = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->ticketdetails->ticketid.'.jpg';
$image_jpg = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->ticketdetails->ticketid.'.jpg';

if (file_exists($image_png)) {
    $seatchart = $seatchart_png;
    $image = $image_png;
} else {
    $seatchart = $seatchart_jpg;
    $image = $image_jpg;
}

## Get the image size
if (file_exists($image)) {
    ## Get the image size
    list($width, $height, $type, $attr) = getimagesize($image);
} else {
    $width = 750;
    $height = 850;
}


## Redirection link in JRoute:
$gotocart = Route::_('index.php?view=cart');

## Total for this order:
$getAmount = new getAmount();
$ordertotal = $getAmount->_getAmount($session->get('ordercode'));
$fees = $getAmount->_getFees($session->get('ordercode'));
//$ordertotal = $total-$fees;

## Load Ticketstation functions
$TicketstationFunctions = new TicketstationFunctions();
?>

<style>

    #glassbox {
        /* PLEASE DO NOT CHANGE */
        height:<?php echo $height+20; ?>px;
        background-repeat:no-repeat;
        background-position: 0px 30px;
        position:relative;
        width:<?php echo $width; ?>px;
        -moz-border-radius: 5px;
        -webkit-border-radius: 5px;
    }

</style>

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



        <h2 class="ticketmaster-header"><strong>Stoelen selecteren</strong></h2>

        <div class="ticketmaster_event_info">
            <h4><strong>Ticketinformatie:</strong></h4>
            <table>
                <tr>
                    <td width="130px" style="font-weight:bold;">Evenement:</td>
                    <td><?php echo $this->ticketdetails->eventname; ?> - <?php echo $this->ticketdetails->ticketname; ?></td>
                </tr>
                <tr>
                    <td style="padding-right:5px;font-weight:bold;">Datum:</td>
                    <td><?php echo date('d-m-Y H:i', strtotime($this->ticketdetails->startdate)); ?></td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Locatie:</td>
                    <td><?php echo $this->ticketdetails->venue; ?> - <?php echo $this->ticketdetails->city; ?></td>
                </tr>
            </table>

            <div style="height:45px; margin:8px 0px 10px 0px; color:#000; text-align:center; padding-bottom:2px;">

                <div id="ajaxMessage" style="display:none; text-align:center; margin-bottom:5px; height:25px;"></div>
                <div id="message"><!-- Dont remove this container, it is used for ordering messages --></div>

            </div>

            <h4><strong>Plattegrond:</strong></h4>

        </div>

        <div>

            <div class="ticketmaster_turn_phone" style="display: none;">
                <img src="/components/com_ticketstation/assets/images/rotate-phone.gif" alt="" style="width:50%;margin-left:auto;margin-right:auto;">
                <p>Draai je telefoon.<br/>Het scherm is te smal om de plattegrond weer te geven.</p>
            </div>

            <div class="ticketmaster_box_content" style="padding-bottom: 25px; width:<?php echo $width+12; ?>px; margin: 0 auto;">

                <div class="glassbox" id="glassbox" <?php if (file_exists($image)) { ?> style="background-image: url(<?= $seatchart; ?>);" <?php } ?>>

                    <?php

                    $k = 0;
                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = &$this->items[$i];

                        $x 			 = $row->x_pos;
                        $y 			 = $row->y_pos;
                        $line_height = 'line-height:'. $row->height .'px;';

                        if ($row->booked > 0){

                            $style = 'color:#fff; border-color:#000; cursor:no-drop; '.$line_height;
                            $background = '#FF0000';

                        }else{

                            $style = 'color:#'.$row->font_color.'; border-color:#'.$row->border_color.'; '.$line_height;

                            if ($row->background_color != ''){
                                $background = '#'. $row->background_color;
                            }else{
                                $background = '#e1fdda';
                            }

                        }

                        ## This is a seat --> Load seat data.
                        if ($row->type == 1){
                            echo '<div id="seat-'.$row->id.'" class="seat-element" 
                                        style="/*box-sizing: unset;*/left:'.$x.'px; top:'.$y.'px; background-color:'.$background.';
                                               width:'.$row->width.'px; height:'.$row->height.'px;
                                               position:absolute; '.$style.'">'.$row->seatid.'</div>';
                        }else{

                            echo '<div id="seat-'.$row->id.'" class="seat-element" 
                                        style="/*box-sizing: unset;*/left:'.$x.'px; top:'.$y.'px;  background-color:'.$background.'; 
                                               width:'.$row->width.'px; height:'.$row->height.'px;
                                               position:absolute; '.$style.'">
                                                    <div style = "line-height:'.$row->height.'px;"><strong>'.$row->ticketname.'</strong></div>
                                               </div>';
                        }

                        $k=1 - $k;
                    }
                    ?>

                </div>

            </div>
        </div>

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

    <div class="col-xl-3 ticketmaster_sidebar">

        <div class="module">
            <div class="module-inner">
                <h3 class="module-title ">Instructie:</h3>
                <div>
                    <div>We verzoeken je vriendelijk om je gekozen plaatsen zoveel mogelijk aan te sluiten aan reeds verkochte plaatsen:</div>
                    <div><img src="components/com_ticketstation/assets/images/stoelkeuze.png" style="max-width: 300px; margin-bottom:20px;"></div>
                </div>

                <div style="padding-bottom:5px; width:250px; clear:both; font-size:95%;">
                    <div>&#8226; <?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_INFO' ); ?><br />&#8226; <?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_SEATS' ); ?></div>
                </div>

                <div class="shoppingbasket" style="clear:both; margin-bottom:10px;">
                    <?php echo Text::_( 'COM_TICKETSTATION_CHOSEN_SEATS' ); ?>
                </div>

                <div style="width:100%; height:90px;">
                    <div id="items" style="margin-bottom:10px; padding-bottom:20px; border:0px; width:100%; height:70px;">

                        <?php $k = 0;

                        for ($i = 0, $n = count($this->seats); $i < $n; $i++ ){
                            $row =  $this->seats[$i];
                            $background_color = $row->background_color;
                            ?>

                            <div id="<?php echo $row->seat_sector; ?>" class="item" style="margin:0px; padding:2px; z-index:5;">
                                <div id="seat-choice" class="seat-choice" style="background-color:<?php echo htmlspecialchars($row->background_color, ENT_QUOTES, 'UTF-8'); ?>;
                                        float:left; border-color:<?php echo htmlspecialchars($row->border_color, ENT_QUOTES, 'UTF-8'); ?>; cursor:pointer; font-size:80%; margin:0px;
                                        color:<?php echo htmlspecialchars($row->font_color, ENT_QUOTES, 'UTF-8'); ?>;">
                                    <?php echo $row->seatid; ?>
                                </div>
                            </div>

                            <?php $k=1 - $k; } ?>

                    </div>
                </div>

                <div class="shoppingbasket" style="clear:both; margin-top:65px;">
                    <?php echo Text::_( 'COM_TICKETSTATION_SEAT_OPTIONS' ); ?>
                </div>

                <div style="margin-bottom:40px; padding-top:10px; height:50px;" id="ticket-options" class="note-multi-ticket">
                    <?php echo Text::_( 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS' ); ?>
                </div>
            </div>
        </div>

        <div class="module">
            <div class="module-inner">
                <h3 class="module-title "><?php echo Text::_('COM_TICKETSTATION_CART'); ?></h3>
                <div class="module-ct">
                    <div id="ticketmaster-cartdetails">

                        <div id="cart-information" class="cart-information">

                            <?php if (count($this->ordered) == 0) { ?>

                                <p><strong><?php echo Text::_('COM_TICKETSTATION_EMPTY_CART'); ?></strong></p>

                            <?php } else { ?>

                                <?php
                                if (count($this->ordered) > 1) {
                                    $tickets = Text::_('COM_TICKETSTATION_TICKETS');
                                } else {
                                    $tickets = Text::_('COM_TICKETSTATION_TICKET');
                                } ?>

                                <table style="width: 250px;">
                                    <tr>
                                        <td><?php  echo count($this->ordered) . ' ' . $tickets; ?></td>
                                        <td style="text-align: right;"><?php  echo $TicketstationFunctions->showprice($this->config->priceformat, ($ordertotal - $fees), $this->config->valuta); ?></td>
                                    </tr>
                                    <tr style="height: 40px;">
                                        <td><?php echo Text::_('COM_TICKETSTATION_FEES'); ?></td>
                                        <td style="text-align: right;"><?php  echo $TicketstationFunctions->showprice($this->config->priceformat, $fees, $this->config->valuta); ?></td>
                                    </tr>
                                    <tr style="border-top: 1px solid #aaa;">
                                        <td><strong><?php  echo Text::_('COM_TICKETSTATION_ORDERTOTAL_CART'); ?></strong></td>
                                        <td style="text-align: right;"><strong><?php  echo $TicketstationFunctions->showprice($this->config->priceformat, $ordertotal, $this->config->valuta); ?></strong></td>
                                    </tr>
                                </table>

                            <?php } ?>

                        </div>

                        <div id="cart-information" class="cart-box">

                            <div id="cart-information-loader-2" style="width:100%; height:20px; margin: 10px 0;">
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

    $("#close").bind("click", function(e){
        window.parent.document.location.reload();
        parent.Mediabox.close();
    });

    $('#ticket-options').on('change', '.ticketid', function (event) {

        var currentId = $(this).attr('id');
        var ticketid = $("#"+currentId).val();

        var tokenName = '<?php echo $session->getToken(); ?>';
        var data = 'ticketid=' + ticketid  + '&ordercode=' + <?php echo $ordercode; ?> +'&orderid='+currentId + '&' + tokenName + '=1';

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=orderseated&task=updateSeat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            //beforeSend: function() {
            //	$( "#ajaxLoader" ).show();
            //},
            success: function (data) {
                // We're done, show data
                //$( "#ajaxLoader" ).hide();
                $( "#ajaxMessage" ).show();
                $( '#ajaxMessage').html('<div class="alert alert-message" style="color:green;">'+ data +'</div>');
                setTimeout(function(){ $('#ajaxMessage').fadeOut(500); }, 3000);
                $( "#ticket-options" ).html('<?php echo Text::_( 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS' ); ?>');
                updateCart();

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $('#ticket-options').on('click', '.remove', function (event) {

        var currentId = $(this).attr('id');

        var tokenName = '<?php echo $session->getToken(); ?>';
        var data = 'id=' + currentId  + '&ordercode=' + <?php echo $ordercode; ?> + '&' + tokenName + '=1';

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=orderseated&task=removeseat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            // data type = json
            dataType: 'json',
            //Do not cache the page
            cache: false,
            //success
            //beforeSend: function() {
            //	$( "#ajaxLoader" ).show();
            //},
            success: function (data) {

                // We're done, show data
                //$( "#ajaxLoader" ).hide();

                if(data.error == 1){

                    $( "#ajaxMessage" ).html(data.msg);

                }else{

                    $( '#' + data.id ).remove();
                    $( '#seat-' + data.id).css('background-color', '#'+data.background);
                    $( '#seat-' + data.id).css('color', '#'+data.color);

                    $( "#ajaxMessage" ).show();
                    $( '#ajaxMessage').html('<div class="alert alert-message" style="color:green;">'+ data.msg +'</div>');

                    setTimeout(function(){ $('#ajaxMessage').fadeOut(500); }, 3000);

                    $( "#ticket-options" ).html('<?php echo Text::_( 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS' ); ?>');

                    updateCart();
                }


            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $('#items').on('click', '.item', function (event) {

        $( "#multi-ticket" ).hide();
        var currentId = $(this).attr('id');

        var data = 'id=' + currentId  + '&ordercode=' + <?php echo $ordercode; ?> +'';

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=orderseated&task=loadSeat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            //beforeSend: function() {
            //	$( "#ajaxLoader" ).show();
            //},
            success: function (data) {
                // We're done, show data
                //	$( "#ajaxLoader" ).hide();
                $( "#ticket-options" ).html(data);

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });


    });

    $(document).ready(function () {


        // When client clicks the seat:
        $( ".seat-element" ).click(function() {

            // Get the current clicked id :)
            var seatNumber = $(this).attr('id');
            var currentId = seatNumber.split('-');

            var tokenName = '<?php echo $session->getToken(); ?>';
            var data = 'id=' + currentId[1]  + '&ordercode=' + <?php echo $ordercode; ?> + '&' + tokenName + '=1';

            $.ajax({
                //this is the php file that processes the data
                url: "index.php?option=com_ticketstation&controller=orderseated&task=makeReservation&format=raw",
                //POST method is used
                type: "POST",
                //pass the data
                data: data,
                // data type = json
                dataType: 'json',
                //Do not cache the page
                cache: false,
                //success
                //beforeSend: function() {
                //	$( "#ajaxLoader" ).show();
                //},
                success: function (data) {
                    // We're done, show data
                    //	$( "#ajaxLoader" ).hide();

                    $( "#ajaxMessage" ).show();

                    if(data.error == 1){

                        $( '#ajaxMessage').html('<div class="alert alert-error">'+ data.msg +'</div>');
                        //setTimeout(function(){ $('#ajaxMessage').fadeOut(500); }, 3000);

                    }else{

                        $( '#seat-'+ data.id ).css('backgroundColor', 'orange');
                        $( '#seat-'+ data.id ).css('color', '#FFF');

                        $( '#ajaxMessage').html('<div class="alert alert-message" style="color:green;">'+ data.msg +'</div>');

                        setTimeout(function(){ $('#ajaxMessage').fadeOut(500); }, 3000);


                        if(data.multiseat == 1){
                            $( "#multi-ticket" ).show();
                            $('<div id="'+data.id+'" class="item" style="padding:2px; border:0px;"><div id="seat-choice" class="seat-choice" style="background-color:orange; float:left; border-color:#CCC; cursor:pointer; font-size:80%;">'+data.seatid+'</div></div>').appendTo("#items");
                        }else{
                            $('<div id="'+data.id+'" class="item" style="padding:2px; border:0px;"><div id="seat-choice" class="seat-choice" style="background-color:#FEFEFE; float:left; border-color:#CCC; cursor:pointer; font-size:80%;">'+data.seatid+'</div></div>').appendTo("#items");
                        }

                        updateCart();

                    }

                },
                error:function (xhr, ajaxOptions, thrownError){
                    alert(xhr.status);
                }
            });


        });

    });

    function loadCart(){

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=orderseated&task=loadCart&format=raw",
            //POST method is used
            type: "POST",
            //Do not cache the page
            cache: false,
            //success
            //beforeSend: function() {
            //	$( "#ajaxLoader" ).show();
            //},
            success: function (data) {
                // We're done, show data
                //	$( "#ajaxLoader" ).hide();
                $( '#shopping_cart').html(data);

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    }

    function updateCart(){

        jQuery('#cart-information-loader').show();

        var order = 'ordercode=' + <?php echo $ordercode; ?> ;
        
        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "index.php?option=com_ticketstation&controller=order&task=itemcount&format=raw",
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
            url: "index.php?option=com_ticketstation&controller=order&task=updatecart&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
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

</script>