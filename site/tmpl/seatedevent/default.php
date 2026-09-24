<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
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
$document->setTitle( Text::_('COM_TICKETSTATION_SELECT_SEATS') . ' - ' . $app->get('sitename') );
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
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));

## Hint under "chosen seats": with price categories the customer also picks the category there.
$seatHint = Text::_($this->pricechoice ? 'COM_TICKETSTATION_CLICK_TO_CHOOSE_PRICE' : 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS');

## Venue website link (stored without scheme in the venue form, e.g. "www.example.nl")
$venue_website_url = preg_match('#^https?://#i', $this->ticketdetails->website) ? $this->ticketdetails->website : 'https://' . $this->ticketdetails->website;
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



        <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_SELECT_SEATS'); ?></strong></h2>

        <div class="ticketmaster_event_info">
            <h4><strong><?php echo Text::_('COM_TICKETSTATION_TICKET_INFORMATION'); ?>:</strong></h4>
            <table>
                <tr>
                    <td width="130px" style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_EVENT'); ?>:</td>
                    <td><?php echo $this->ticketdetails->eventname; ?> - <?php echo $this->ticketdetails->ticketname; ?></td>
                </tr>
                <tr>
                    <td style="padding-right:5px;font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_DATE'); ?>:</td>
                    <td><?php echo date('d-m-Y H:i', strtotime($this->ticketdetails->startdate)); ?></td>
                </tr>
                <?php if ($this->config->show_venue == 1) { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_VENUE'); ?>:</td>
                        <td><?php echo $this->ticketdetails->venue; ?> - <?php echo $this->ticketdetails->city; ?></td>
                    </tr>
                <?php } ?>
                <?php if ($this->config->show_venue == 1 && $this->config->show_venue_address == 1 && ($this->ticketdetails->street != '' || $this->ticketdetails->zipcode != '')) { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_ADDRESS'); ?>:</td>
                        <td><?php echo htmlspecialchars(trim($this->ticketdetails->street . ', ' . $this->ticketdetails->zipcode . ' ' . $this->ticketdetails->city, ', '), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php } ?>
                <?php if ($this->config->show_venue == 1 && $this->config->show_venue_website == 1 && $this->ticketdetails->website != '') { ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo Text::_('COM_TICKETSTATION_WEBSITE'); ?>:</td>
                        <td><a href="<?php echo htmlspecialchars($venue_website_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($this->ticketdetails->website, ENT_QUOTES, 'UTF-8'); ?></a></td>
                    </tr>
                <?php } ?>
            </table>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_description == 1 && trim(strip_tags($this->ticketdetails->venuedescription)) != '') { ?>
                <div class="ticketstation_venue_description">
                    <?php echo $this->ticketdetails->venuedescription; ?>
                </div>
            <?php } ?>

            <div style="height:45px; margin:8px 0px 10px 0px; color:#000; text-align:center; padding-bottom:2px;">

                <div id="ajaxMessage" style="display:none; text-align:center; margin-bottom:5px; height:25px;"></div>
                <div id="message"><!-- Dont remove this container, it is used for ordering messages --></div>

            </div>

            <div class="row ticketstation_seat_panels">

                <div class="col-lg-6">
                    <div class="ticketstation_seat_panel">
                        <h3 class="ticketstation_seat_panel_title"><?php echo Text::_('COM_TICKETSTATION_INSTRUCTIONS'); ?>:</h3>
                        <div><?php echo Text::_('COM_TICKETSTATION_SEAT_INSTRUCTION'); ?></div>
                        <div><img src="components/com_ticketstation/assets/images/stoelkeuze.png" style="max-width: 300px; width:100%; margin:10px 0;" alt=""></div>
                        <div style="font-size:95%;">
                            <div>&#8226; <?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_INFO' ); ?><br />&#8226; <?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_SEATS' ); ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="ticketstation_seat_panel">
                        <h3 class="ticketstation_seat_panel_title"><?php echo Text::_( 'COM_TICKETSTATION_CHOSEN_SEATS' ); ?></h3>
                        <div id="items">

                            <?php for ($i = 0, $n = count($this->seats); $i < $n; $i++ ){
                                $row = $this->seats[$i];
                                ?>

                                <div id="<?php echo $row->seat_sector; ?>" class="item" style="margin:0px; padding:2px; z-index:5;">
                                    <div id="seat-choice" class="seat-choice" style="background-color:#<?php echo htmlspecialchars($row->background_color, ENT_QUOTES, 'UTF-8'); ?>;
                                            float:left; border-color:#<?php echo htmlspecialchars($row->border_color, ENT_QUOTES, 'UTF-8'); ?>; cursor:pointer; font-size:80%; margin:0px;
                                            color:#<?php echo htmlspecialchars($row->font_color, ENT_QUOTES, 'UTF-8'); ?>;">
                                        <?php echo htmlspecialchars($row->row_name . $row->seatid, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>

                            <?php } ?>

                        </div>

                        <!-- Shows the hint by default; filled with the seat options (price category, remove button) when a chosen seat is clicked -->
                        <div id="ticket-options" class="note-multi-ticket">
                            <?php echo $seatHint; ?>
                        </div>
                    </div>
                </div>

            </div>

            <h4><strong><?php echo Text::_('COM_TICKETSTATION_SEATING_PLAN'); ?>:</strong></h4>

        </div>

        <div>

            <div class="ticketmaster_turn_phone" style="display: none;">
                <img src="/components/com_ticketstation/assets/images/rotate-phone.gif" alt="" style="width:50%;margin-left:auto;margin-right:auto;">
                <p><?php echo Text::_('COM_TICKETSTATION_ROTATE_PHONE'); ?></p>
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
                    <span><?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?></span>
                </a>
            </div>

            <a class="btn btn-primary pull-left" onClick="history.back()">
                <span><?php echo Text::_('COM_TICKETSTATION_BACK'); ?></span>
            </a>

        </div>

    </div>
</div>




<script type="text/javascript">

    var seatHint = <?php echo json_encode($seatHint); ?>;

    $("#close").bind("click", function(e){
        window.parent.document.location.reload();
        parent.Mediabox.close();
    });

    $('#ticket-options').on('change', '.ticketid', function (event) {

        var currentId = $(this).attr('id');
        var ticketid = $("#"+currentId).val();

        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
        var data = 'ticketid=' + ticketid  + '&ordercode=' + <?php echo $ordercode; ?> +'&orderid='+currentId + '&' + tokenName + '=1';

        $.ajax({
            //this is the php file that processes the data
            url: "/index.php?option=com_ticketstation&controller=orderseated&task=updateSeat&format=raw",
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
                $( "#ticket-options" ).html(seatHint);
                updateCart();

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $('#ticket-options').on('click', '.remove', function (event) {

        var currentId = $(this).attr('id');

        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
        var data = 'id=' + currentId  + '&ordercode=' + <?php echo $ordercode; ?> + '&' + tokenName + '=1';

        $.ajax({
            //this is the php file that processes the data
            url: "/index.php?option=com_ticketstation&controller=orderseated&task=removeseat&format=raw",
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

                    $( "#ticket-options" ).html(seatHint);

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
        loadSeatOptions($(this).attr('id'));

    });

    // Shows the options of a chosen seat (price category, remove button) under "chosen seats".
    function loadSeatOptions(currentId) {

        var data = 'id=' + currentId  + '&ordercode=' + <?php echo $ordercode; ?> +'';

        $.ajax({
            //this is the php file that processes the data
            url: "/index.php?option=com_ticketstation&controller=orderseated&task=loadSeat&format=raw",
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


    }

    $(document).ready(function () {


        // When client clicks the seat:
        $( ".seat-element" ).click(function() {

            // Get the current clicked id :)
            var seatNumber = $(this).attr('id');
            var currentId = seatNumber.split('-');

            var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
            var data = 'id=' + currentId[1]  + '&ordercode=' + <?php echo $ordercode; ?> + '&' + tokenName + '=1';

            $.ajax({
                //this is the php file that processes the data
                url: "/index.php?option=com_ticketstation&controller=orderseated&task=makeReservation&format=raw",
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

                        // Price categories: open the category choice for the new seat right away.
                        if (data.pricechoice == 1) {
                            loadSeatOptions(data.id);
                        }

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
            url: "/index.php?option=com_ticketstation&controller=orderseated&task=loadCart&format=raw",
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
                jQuery("#seatselection").delay(500).show(0);
                if (!html.includes('empty_cart')) {
                    jQuery("#continue-button").show(0);
                } else {
                    jQuery("#continue-button").hide(0);
                }

            }
        });

    }

</script>
