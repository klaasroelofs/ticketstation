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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatChart;
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

## The chart: canvas, background image and shapes; it scales with the screen (SeatChart).
SeatChart::loadAssets();
$chartOwner      = (int) $this->ticketdetails->ticketid;
$chartSettings   = SeatChart::settings($chartOwner);
$chartBackground = SeatChart::background($chartSettings, $chartOwner);
$chartShapes     = SeatChart::shapes($chartSettings);
$chartCanvas     = SeatChart::canvas($chartSettings, $this->items, $chartBackground, $chartShapes);


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
            <dd><?php echo Date::long($this->ticketdetails->startdate, true); ?></dd>

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

        <div class="ts-seatmap">
            <?php echo SeatChart::open($chartCanvas, $chartBackground, $chartSettings, $chartShapes, $this->items); ?>

                <?php

                ## The seats in this customer's own order.
                $mine = array_map('intval', array_column($this->ordered, 'seat_sector'));
                $hex  = fn ($value, $fallback) => '#' . SeatChart::hex($value, $fallback);

                foreach ($this->items as $row) {

                    if ($row->booked > 0 && in_array((int) $row->id, $mine, true)){

                        ## Chosen by this customer: orange, as right after picking it.
                        $style = 'color:#fff; border-color:' . $hex($row->border_color, '000000') . '; cursor:no-drop; background-color:orange;';

                    }elseif ($row->booked > 0){

                        $style = 'color:#fff; border-color:#000; cursor:no-drop; background-color:#FF0000;';

                    }else{

                        $style = 'color:' . $hex($row->font_color, '000000') . '; border-color:' . $hex($row->border_color, '198d02') . '; background-color:' . $hex($row->background_color, 'e1fdda') . ';';
                    }

                    $label = (int) $row->type === 1 ? $row->row_name . $row->seatid : $row->ticketname;

                    echo '<div id="seat-' . (int) $row->id . '" class="seat-element" style="' . SeatChart::seatStyle($row, $chartCanvas) . $style . '">'
                        . ((int) $row->type === 1 ? '' : '<strong>') . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ((int) $row->type === 1 ? '' : '</strong>')
                        . '</div>';
                }
                ?>

            <?php echo SeatChart::close(); ?>
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

    // A call that failed (no connection, or an expired session that the server refuses with 403).
    function requestFailed() {
        showMessage('danger', <?php echo json_encode(Text::_('COM_TICKETSTATION_REQUEST_FAILED')); ?>);
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
            error: requestFailed
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
                    $( '#seat-' + data.id).css('cursor', ''); // back to the stylesheet's pointer

                    showMessage('success', data.msg);

                    $( "#ticket-options" ).html(seatHint);

                    updateCart();
                }


            },
            error: requestFailed
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
            error: requestFailed
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
                        $( '#seat-'+ data.id ).css('cursor', 'no-drop');

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
                error: requestFailed
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
