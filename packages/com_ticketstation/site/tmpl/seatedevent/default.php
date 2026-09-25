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
$seatchart_png = Uri::root(true) . '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->ticketdetails->ticketid.'.png';
$image_png = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->ticketdetails->ticketid.'.png';
$seatchart_jpg = Uri::root(true) . '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->ticketdetails->ticketid.'.jpg';
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

## Size of the chart (seat positions are relative to it) and its background image
$glassbox_style = 'width:' . (int) $width . 'px; height:' . ((int) $height + 20) . 'px;'
    . (file_exists($image) ? ' background-image: url(' . $seatchart . ');' : '');


## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
$shop_on  = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

## Hint under "chosen seats": with price categories the customer also picks the category there.
$seatHint = Text::_($this->pricechoice ? 'COM_TICKETSTATION_CLICK_TO_CHOOSE_PRICE' : 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS');

## Venue website link (stored without scheme in the venue form, e.g. "www.example.nl")
$venue_website_url = preg_match('#^https?://#i', $this->ticketdetails->website) ? $this->ticketdetails->website : 'https://' . $this->ticketdetails->website;
?>

<div class="ticketstation ticketstation--seatedevent">

    <?php echo LayoutHelper::render('steps', ['current' => 1], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_SELECT_SEATS'); ?></h1>
    </div>

    <section class="ts-card ts-ticketinfo">
        <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_TICKET_INFORMATION'); ?></h2>

        <dl class="ts-meta">
            <dt><?php echo Text::_('COM_TICKETSTATION_EVENT'); ?></dt>
            <dd><?php echo $this->ticketdetails->eventname; ?> - <?php echo $this->ticketdetails->ticketname; ?></dd>

            <dt><?php echo Text::_('COM_TICKETSTATION_DATE'); ?></dt>
            <dd><?php echo date('d-m-Y H:i', strtotime($this->ticketdetails->startdate)); ?></dd>

            <?php if ($this->config->show_venue == 1) { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_VENUE'); ?></dt>
                <dd><?php echo $this->ticketdetails->venue; ?> - <?php echo $this->ticketdetails->city; ?></dd>
            <?php } ?>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_address == 1 && ($this->ticketdetails->street != '' || $this->ticketdetails->zipcode != '')) { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_ADDRESS'); ?></dt>
                <dd><?php echo htmlspecialchars(trim($this->ticketdetails->street . ', ' . $this->ticketdetails->zipcode . ' ' . $this->ticketdetails->city, ', '), ENT_QUOTES, 'UTF-8'); ?></dd>
            <?php } ?>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_website == 1 && $this->ticketdetails->website != '') { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_WEBSITE'); ?></dt>
                <dd><a href="<?php echo htmlspecialchars($venue_website_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($this->ticketdetails->website, ENT_QUOTES, 'UTF-8'); ?></a></dd>
            <?php } ?>
        </dl>

        <?php if ($this->config->show_venue == 1 && $this->config->show_venue_description == 1 && trim(strip_tags($this->ticketdetails->venuedescription)) != '') { ?>
            <div class="ts-venue-description">
                <?php echo $this->ticketdetails->venuedescription; ?>
            </div>
        <?php } ?>
    </section>

    <div class="ts-panels">

        <section class="ts-card ts-panel ts-panel--instructions">
            <h2 class="ts-card__title"><?php echo rtrim(Text::_('COM_TICKETSTATION_INSTRUCTIONS'), ': '); ?></h2>
            <p><?php echo Text::_('COM_TICKETSTATION_SEAT_INSTRUCTION'); ?></p>
            <img class="ts-instruction-image" src="components/com_ticketstation/assets/images/stoelkeuze.png" alt="">
            <ul class="ts-legend">
                <li><?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_INFO' ); ?></li>
                <li><?php echo Text::_( 'COM_TICKETSTATION_DROPPABLE_ORDERED_SEATS' ); ?></li>
            </ul>
        </section>

        <section class="ts-card ts-panel ts-panel--chosen">
            <h2 class="ts-card__title"><?php echo rtrim(Text::_( 'COM_TICKETSTATION_CHOSEN_SEATS' ), ': '); ?></h2>
            <div id="items" class="ts-chosen-seats">

                <?php for ($i = 0, $n = count($this->seats); $i < $n; $i++ ){
                    $row = $this->seats[$i];
                    ?>

                    <div id="<?php echo $row->seat_sector; ?>" class="ts-chosen-seat">
                        <span class="ts-seat-chip" style="background-color:#<?php echo htmlspecialchars($row->background_color, ENT_QUOTES, 'UTF-8'); ?>; border-color:#<?php echo htmlspecialchars($row->border_color, ENT_QUOTES, 'UTF-8'); ?>; color:#<?php echo htmlspecialchars($row->font_color, ENT_QUOTES, 'UTF-8'); ?>;">
                            <?php echo htmlspecialchars($row->row_name . $row->seatid, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                <?php } ?>

            </div>

            <!-- Shows the hint by default; filled with the seat options (price category, remove button) when a chosen seat is clicked -->
            <div id="ticket-options" class="ts-seat-options">
                <?php echo $seatHint; ?>
            </div>
        </section>

    </div>

    <section class="ts-seatmap-section">
        <h2 class="ts-section-title"><?php echo Text::_('COM_TICKETSTATION_SEATING_PLAN'); ?></h2>

        <div class="ts-rotate-hint">
            <img src="<?php echo Uri::root(true); ?>/components/com_ticketstation/assets/images/rotate-phone.gif" alt="">
            <p><?php echo Text::_('COM_TICKETSTATION_ROTATE_PHONE'); ?></p>
        </div>

        <div class="ts-seatmap">
            <div class="ts-seatmap__canvas glassbox" id="glassbox" style="<?php echo $glassbox_style; ?>">

                <?php

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
                                    style="left:'.$x.'px; top:'.$y.'px; background-color:'.$background.';
                                           width:'.$row->width.'px; height:'.$row->height.'px;
                                           position:absolute; '.$style.'">'.htmlspecialchars($row->row_name . $row->seatid, ENT_QUOTES, 'UTF-8').'</div>';
                    }else{

                        echo '<div id="seat-'.$row->id.'" class="seat-element"
                                    style="left:'.$x.'px; top:'.$y.'px;  background-color:'.$background.';
                                           width:'.$row->width.'px; height:'.$row->height.'px;
                                           position:absolute; '.$style.'">
                                                <div style = "line-height:'.$row->height.'px;"><strong>'.$row->ticketname.'</strong></div>
                                           </div>';
                    }
                }
                ?>

            </div>
        </div>
    </section>

    <!-- Floating messages after clicking a seat, so the chart doesn't move; don't remove -->
    <div id="ajaxMessage" class="ts-toast" role="status" aria-live="polite" style="display: none;"></div>

    <div class="ts-actions">
        <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $shop_on; ?>">
            <?php echo Text::_('COM_TICKETSTATION_BACK'); ?>
        </a>

        <a id="continue-button" class="ts-btn ts-btn--primary ts-btn--next" href="<?php echo $gotocart; ?>"<?php echo count($this->ordered) == 0 ? ' style="display: none;"' : ''; ?>>
            <?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?>
        </a>
    </div>

</div>

<script type="text/javascript">
(function ($) {

    var seatHint = <?php echo json_encode($seatHint); ?>;

    // Shows a message at the bottom of the screen; errors stay a little longer.
    function showMessage(type, msg) {
        var alertBox = $('<div class="ts-alert"></div>').addClass('ts-alert--' + type).html(msg);

        $('#ajaxMessage').stop(true, true).empty().append(alertBox).show()
            .delay(type === 'danger' ? 5000 : 3000).fadeOut(500);
    }

    $('#ticket-options').on('change', '.ticketid', function (event) {

        var currentId = $(this).attr('id');
        var ticketid = $("#"+currentId).val();

        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
        var data = 'ticketid=' + ticketid  + '&ordercode=' + <?php echo $ordercode; ?> +'&orderid='+currentId + '&' + tokenName + '=1';

        $.ajax({
            //this is the php file that processes the data
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=orderseated&task=updateSeat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (data) {
                // We're done, show data
                showMessage('success', data);
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
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=orderseated&task=removeseat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            // data type = json
            dataType: 'json',
            //Do not cache the page
            cache: false,
            //success
            success: function (data) {

                if(data.error == 1){

                    showMessage('danger', data.msg);

                }else{

                    $( '#' + data.id ).remove();
                    $( '#seat-' + data.id).css('background-color', '#'+data.background);
                    $( '#seat-' + data.id).css('color', '#'+data.color);

                    showMessage('success', data.msg);

                    $( "#ticket-options" ).html(seatHint);

                    updateCart();
                }


            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $('#items').on('click', '.ts-chosen-seat', function (event) {

        loadSeatOptions($(this).attr('id'));

    });

    // Shows the options of a chosen seat (price category, remove button) under "chosen seats".
    function loadSeatOptions(currentId) {

        var data = 'id=' + currentId  + '&ordercode=' + <?php echo $ordercode; ?> +'';

        $.ajax({
            //this is the php file that processes the data
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=orderseated&task=loadSeat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (data) {
                // We're done, show data
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
                url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=orderseated&task=makeReservation&format=raw",
                //POST method is used
                type: "POST",
                //pass the data
                data: data,
                // data type = json
                dataType: 'json',
                //Do not cache the page
                cache: false,
                //success
                success: function (data) {
                    // We're done, show data

                    if(data.error == 1){

                        showMessage('danger', data.msg);

                    }else{

                        $( '#seat-'+ data.id ).css('backgroundColor', 'orange');
                        $( '#seat-'+ data.id ).css('color', '#FFF');

                        showMessage('success', data.msg);

                        $('<div class="ts-chosen-seat"><span class="ts-seat-chip ts-seat-chip--selected"></span></div>')
                            .attr('id', data.id)
                            .find('.ts-seat-chip').text(data.seatid).end()
                            .appendTo("#items");

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

    function updateCart(){

        var order = 'ordercode=' + <?php echo $ordercode; ?> ;

        $.ajax({
            //this is the php file that processes the data and send mail
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=itemcount&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                $("#basket-item-count").html(html);
                if (html !== '0') {
                    $("#ticketstation_basket_module").show(0);
                } else {
                    $("#ticketstation_basket_module").hide(0);
                }
            }
        });

        $.ajax({
            //this is the php file that processes the data and send mail
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=updatecart&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                if (!html.includes('empty_cart')) {
                    $("#continue-button").show(0);
                } else {
                    $("#continue-button").hide(0);
                }

            }
        });

    }

})(jQuery);
</script>
