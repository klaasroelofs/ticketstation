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
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatOrphans;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_SELECT_SEATS') . ' - ' . $app->get('sitename') );
TicketstationFunctions::addSiteStylesheet();
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

## "Prevent single empty seats": the instruction becomes a rule, checked before continuing.
$preventOrphans  = SeatOrphans::enabled($chartOwner);

## Sent back from the cart or checkout (SeatOrphans::guard()): say why and ring the seats that
## are still left on their own; nothing when the choice has been put right since.
$pageOrphans = $preventOrphans && $app->getInput()->getInt('orphans', 0) === 1
    ? SeatOrphans::forChart($chartOwner, (int) $ordercode) : [];
$orphanIds   = array_map(fn ($seat) => (int) $seat->id, $pageOrphans);


## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
$shop_on  = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

## Hint under "chosen seats": with price categories the customer also picks the category there.
$seatHint = Text::_($this->pricechoice ? 'COM_TICKETSTATION_CLICK_TO_CHOOSE_PRICE' : 'COM_TICKETSTATION_CLICK_TO_SEE_OPTIONS');

## Venue website link (stored without scheme in the venue form, e.g. "www.example.nl")
$venue_website_url = preg_match('#^https?://#i', $this->ticketdetails->website) ? $this->ticketdetails->website : 'https://' . $this->ticketdetails->website;

## The ticket's own background image, or else the event's (the same one as in the event list)
$bannerStyle = TicketstationFunctions::backgroundImageStyle('ticket' . (int) $this->ticketdetails->ticketid)
    ?: TicketstationFunctions::backgroundImageStyle('event' . (int) $this->ticketdetails->eventid);
?>

<div class="ticketstation ticketstation--seatedevent">

    <?php echo LayoutHelper::render('steps', ['current' => 1], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_SELECT_SEATS'); ?></h1>
    </div>

    <?php if ($pageOrphans) { ?>
        <div id="ts-orphan-notice" class="ts-alert ts-alert--danger" role="alert">
            <?php echo SeatOrphans::message($pageOrphans); ?>
        </div>
    <?php } ?>

    <section class="ts-card ts-ticketinfo">
        <?php if ($bannerStyle) { ?>
            <div class="ts-event-banner" style="<?php echo htmlspecialchars($bannerStyle, ENT_QUOTES, 'UTF-8'); ?>" role="img" aria-label="<?php echo htmlspecialchars($this->ticketdetails->eventname, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <?php } ?>

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

        <?php if (trim(strip_tags((string) $this->ticketdetails->eventdescription)) !== '') { ?>
            <div class="ts-event-description">
                <?php echo $this->ticketdetails->eventdescription; ?>
            </div>
        <?php } ?>

        <?php if ($this->config->show_venue == 1 && $this->config->show_venue_description == 1 && trim(strip_tags($this->ticketdetails->venuedescription)) != '') { ?>
            <div class="ts-venue-description">
                <?php echo $this->ticketdetails->venuedescription; ?>
            </div>
        <?php } ?>
    </section>

    <div class="ts-panels">

        <section class="ts-card ts-panel ts-panel--instructions">
            <h2 class="ts-card__title"><?php echo rtrim(Text::_('COM_TICKETSTATION_INSTRUCTIONS'), ': '); ?></h2>
            <p><?php echo Text::_($preventOrphans ? 'COM_TICKETSTATION_SEAT_INSTRUCTION_STRICT' : 'COM_TICKETSTATION_SEAT_INSTRUCTION'); ?></p>
            <?php
            ## Legend with the same look as the seats on the chart. A free seat is shown in the
            ## colours of the first free seat (sections may have colours of their own).
            $freeSeat = current(array_filter($this->items, fn ($seat) => (int) $seat->type === 1 && $seat->booked == 0));
            $freeStyle = $freeSeat
                ? 'color:#' . SeatChart::hex($freeSeat->font_color, '000000') . '; border-color:#' . SeatChart::hex($freeSeat->border_color, '198d02') . '; background-color:#' . SeatChart::hex($freeSeat->background_color, 'e1fdda') . ';'
                : '';

            ## Sold or blocked: red; chosen by this customer: orange (also set by the script below)
            $takenStyle = 'color:#fff; border-color:#000; background-color:#ff0000;';
            $mineStyle  = 'color:#fff; border-color:#000; background-color:#ffa500;';

            ## Example rows of six seats: taken, taken, then the customer's two seats with or without a
            ## gap. With "Prevent single empty seats" the seats left on their own get the red ring of
            ## the chart, and a third row shows that the middle of a free stretch is fine too.
            $exampleSeat = fn (int $number, string $state) => '<span class="ts-seat-swatch' . ['taken' => ' seat-element--taken', 'mine' => ' seat-element--mine', 'free' => '', 'orphan' => ' seat-element--orphan'][$state] . '" style="'
                . ['taken' => $takenStyle, 'mine' => $mineStyle, 'free' => $freeStyle, 'orphan' => $freeStyle][$state] . '">' . $number . '</span>';
            $examples = $preventOrphans
                ? [
                    'wrong'  => ['taken', 'taken', 'orphan', 'mine', 'mine', 'orphan'],
                    'right'  => ['taken', 'taken', 'mine', 'mine', 'free', 'free'],
                    'middle' => ['free', 'free', 'mine', 'mine', 'free', 'free'],
                ]
                : [
                    'wrong' => ['taken', 'taken', 'free', 'mine', 'mine', 'free'],
                    'right' => ['taken', 'taken', 'mine', 'mine', 'free', 'free'],
                ];
            ?>
            <div class="ts-seat-examples">
                <?php foreach ($examples as $kind => $states) { ?>
                    <figure class="ts-seat-example ts-seat-example--<?php echo $kind; ?>">
                        <div class="ts-seat-example__row" aria-hidden="true">
                            <?php foreach ($states as $i => $state) {
                                echo $exampleSeat(101 + $i, $state);
                            } ?>
                        </div>
                        <figcaption>
                            <?php if ($kind === 'wrong') { ?>
                                <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.3 3.3a1 1 0 0 1 1.4 0L8 6.6l3.3-3.3a1 1 0 1 1 1.4 1.4L9.4 8l3.3 3.3a1 1 0 0 1-1.4 1.4L8 9.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L6.6 8 3.3 4.7a1 1 0 0 1 0-1.4z"/></svg>
                            <?php } else { ?>
                                <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M13.7 3.3a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4 0l-3-3a1 1 0 1 1 1.4-1.4L6 9.6l6.3-6.3a1 1 0 0 1 1.4 0z"/></svg>
                            <?php } ?>
                            <?php echo Text::_('COM_TICKETSTATION_SEAT_EXAMPLE_' . strtoupper($kind) . ($preventOrphans && $kind === 'wrong' ? '_STRICT' : '')); ?>
                        </figcaption>
                    </figure>
                <?php } ?>
            </div>

            <?php if ($preventOrphans) { ?>
                <details class="ts-more">
                    <summary><?php echo Text::_('COM_TICKETSTATION_SEAT_INSTRUCTION_MORE'); ?></summary>
                    <p><?php echo Text::_('COM_TICKETSTATION_SEAT_INSTRUCTION_DETAILS'); ?></p>
                </details>
            <?php } ?>

            <ul class="ts-seat-legend" aria-label="<?php echo Text::_('COM_TICKETSTATION_SEAT_LEGEND'); ?>">
                <li><span class="ts-seat-swatch" style="<?php echo $freeStyle; ?>" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_SEAT_FREE'); ?></li>
                <li><span class="ts-seat-swatch seat-element--taken" style="<?php echo $takenStyle; ?>" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_SEAT_TAKEN'); ?></li>
                <li><span class="ts-seat-swatch seat-element--mine" style="<?php echo $mineStyle; ?>" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_SEAT_MINE'); ?></li>
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

        <div class="ts-seatmap" data-ts-tip-taken="<?php echo Text::_('COM_TICKETSTATION_SEAT_TAKEN'); ?>" data-ts-tip-mine="<?php echo Text::_('COM_TICKETSTATION_SEAT_MINE'); ?>">
            <?php echo SeatChart::open($chartCanvas, $chartBackground, $chartSettings, $chartShapes, $this->items); ?>

                <?php

                ## The seats in this customer's own order.
                $mine = array_map('intval', array_column($this->ordered, 'seat_sector'));
                $hex  = fn ($value, $fallback) => '#' . SeatChart::hex($value, $fallback);

                ## Tooltip per seat (seatmap.js): row and seat number, then what it costs. A free seat
                ## on a chart with price categories sells in each of them, so it lists them all. The
                ## currency is free text in the configuration and may be an entity such as &euro;.
                $price      = fn ($amount) => html_entity_decode(TicketstationFunctions::showprice($this->config->priceformat, $amount, $this->config->valuta), ENT_QUOTES, 'UTF-8');
                $categories = array_map(fn ($category) => $category->ticketname . ': ' . $price($category->ticketprice), SeatplanSettings::priceCategories($chartOwner));

                foreach ($this->items as $row) {

                    ## Taken seats are red, the customer's own choice orange; the stylesheet adds stripes
                    ## (--taken) and a dark edge (--mine), so the states don't depend on colour alone.
                    if ($row->booked > 0 && in_array((int) $row->id, $mine, true)){

                        $state = ' seat-element--mine';
                        $style = $mineStyle;

                    }elseif ($row->booked > 0){

                        $state = ' seat-element--taken';
                        $style = $takenStyle;

                    }else{

                        $state = in_array((int) $row->id, $orphanIds, true) ? ' seat-element--orphan' : '';
                        $style = 'color:' . $hex($row->font_color, '000000') . '; border-color:' . $hex($row->border_color, '198d02') . '; background-color:' . $hex($row->background_color, 'e1fdda') . ';';
                    }

                    $label = (int) $row->type === 1 ? $row->row_name . $row->seatid : $row->ticketname;

                    if ((int) $row->type === 1) {
                        $tip = [(string) $row->row_name !== ''
                            ? Text::sprintf('COM_TICKETSTATION_SEATMAP_TIP_ROW_SEAT', $row->row_name, $row->seatid)
                            : Text::sprintf('COM_TICKETSTATION_SEATMAP_TIP_SEAT', $row->seatid)];
                        $tip = array_merge($tip, (int) $row->parent === 0 && $categories ? $categories : [$row->ticketname . ': ' . $price($row->ticketprice)]);
                    } else {
                        $tip = [$row->ticketname, $price($row->ticketprice)];
                    }

                    echo '<div id="seat-' . (int) $row->id . '" class="seat-element' . $state . '" data-border="' . $hex($row->border_color, '198d02') . '"'
                        . ' data-ts-tip="' . htmlspecialchars(implode("\n", $tip), ENT_QUOTES, 'UTF-8') . '" style="' . SeatChart::seatStyle($row, $chartCanvas) . $style . '">'
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

    // Shows a message at the bottom of the screen; errors stay a little longer, a longer text
    // can ask for more time. A click closes it.
    function showMessage(type, msg, duration) {
        var alertBox = $('<div class="ts-alert"></div>').addClass('ts-alert--' + type).html(msg);

        $('#ajaxMessage').stop(true, true).empty().append(alertBox).show()
            .delay(duration || (type === 'danger' ? 5000 : 3000)).fadeOut(500);
    }

    $('#ajaxMessage').on('click', function () {
        $(this).stop(true, true).fadeOut(200);
    });

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
                    // Back to the free seat's own colours (its border is kept in data-border).
                    var seat = $( '#seat-' + data.id);
                    seat.removeClass('seat-element--mine')
                        .css({'background-color': '#' + data.background, 'color': '#' + data.color, 'border-color': seat.attr('data-border')});

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

                        // Orange with a dark edge, as in the legend.
                        $( '#seat-'+ data.id ).addClass('seat-element--mine').css({'background-color': '#ffa500', 'color': '#fff', 'border-color': '#000'});

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

<?php if ($preventOrphans) { ?>
    // "Prevent single empty seats": the whole choice is checked before going to the cart, as
    // seats are claimed one click at a time. Seats left on their own are ringed in red.
    $('#continue-button').on('click', function (event) {

        var link = this.href;
        var tokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';

        event.preventDefault();
        $('.seat-element--orphan').removeClass('seat-element--orphan');

        $.ajax({
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=orderseated&task=checkOrphans&format=raw",
            type: "POST",
            data: 'cid=<?php echo $chartOwner; ?>&' + tokenName + '=1',
            dataType: 'json',
            cache: false,
            success: function (data) {

                if (data.ok) {
                    window.location.href = link;
                    return;
                }

                $.each(data.seats, function (i, id) {
                    $('#seat-' + id).addClass('seat-element--orphan');
                });

                showMessage('danger', data.msg, 12000);

                var first = document.getElementById('seat-' + data.seats[0]);

                if (first) {
                    first.scrollIntoView({block: 'center', inline: 'center', behavior: 'smooth'});
                }
            },
            error: requestFailed
        });
    });
<?php } ?>

    function updateCart(){

        // The choice changed: an earlier "single empty seat" ring or notice no longer applies.
        $('.seat-element--orphan').removeClass('seat-element--orphan');
        $('#ts-orphan-notice').remove();

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
