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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

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
$shop_on  = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));

## Determine available tickets: for a parent with child tickets the total over all published
## variants, following their counter settings (the same figure as in the upcoming-events list)
$availability      = Availability::summary((int) $this->items->ticketid);
$available_tickets = $availability->available;

## Calculate percentage available tickets
## (guard against a ticket without a capacity, which would divide by zero)
$percentage_available = ($availability->capacity > 0)
    ? round((($available_tickets / $availability->capacity) * 100), 0)
    : 0;

if ($available_tickets <= 0) {
    $availability_class = 'ts-availability--soldout';
} elseif ($percentage_available < 11) {
    $availability_class = 'ts-availability--critical';
} elseif ($percentage_available < 26) {
    $availability_class = 'ts-availability--low';
} else {
    $availability_class = 'ts-availability--ok';
}

## Venue website link (stored without scheme in the venue form, e.g. "www.example.nl")
$venue_website_url = preg_match('#^https?://#i', $this->items->website) ? $this->items->website : 'https://' . $this->items->website;

## One table row per ticket: the child tickets (variants) of this ticket, or else the ticket itself.
## Only a ticket without variants offers the waiting list and the "few tickets left" notice.
$ticketRows = [];

if (count($this->childs) != 0) {
    foreach ($this->childs as $child) {
        ## Tickets left for this variant: the shared parent pool or its own cap.
        $ticketRows[] = (object) [
            'ticket'      => $child,
            'available'   => Availability::forPurchase((int) $child->ticketid),
            'waitinglist' => false,
            'fewLeft'     => false,
        ];
    }
} else {
    $ticketRows[] = (object) [
        'ticket'      => $this->items,
        'available'   => $available_tickets,
        'waitinglist' => $this->config->show_waitinglist == 1,
        'fewLeft'     => ($percentage_available < 0.5) && ($available_tickets > 0),
    ];
}

?>

<div class="ticketstation ticketstation--event">

    <?php echo LayoutHelper::render('steps', ['current' => 1], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS'); ?></h1>
    </div>

    <section class="ts-card ts-ticketinfo">
        <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_TICKET_INFORMATION'); ?></h2>

        <dl class="ts-meta">
            <dt><?php echo Text::_('COM_TICKETSTATION_EVENT'); ?></dt>
            <dd><?php echo htmlspecialchars($this->items->eventname, ENT_QUOTES, 'UTF-8'); ?></dd>

            <dt><?php echo Text::_('COM_TICKETSTATION_DATE'); ?></dt>
            <dd><?php echo date('d-m-Y H:i', strtotime($this->items->startdate)); ?></dd>

            <?php if ($this->config->show_venue == 1) { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_VENUE'); ?></dt>
                <dd><?php echo $this->items->venue; ?> - <?php echo $this->items->city; ?></dd>
            <?php } ?>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_address == 1 && ($this->items->street != '' || $this->items->zipcode != '')) { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_ADDRESS'); ?></dt>
                <dd><?php echo htmlspecialchars(trim($this->items->street . ', ' . $this->items->zipcode . ' ' . $this->items->city, ', '), ENT_QUOTES, 'UTF-8'); ?></dd>
            <?php } ?>

            <?php if ($this->config->show_venue == 1 && $this->config->show_venue_website == 1 && $this->items->website != '') { ?>
                <dt><?php echo Text::_('COM_TICKETSTATION_WEBSITE'); ?></dt>
                <dd><a href="<?php echo htmlspecialchars($venue_website_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($this->items->website, ENT_QUOTES, 'UTF-8'); ?></a></dd>
            <?php } ?>
        </dl>

        <?php if ($this->config->show_venue == 1 && $this->config->show_venue_description == 1 && trim(strip_tags($this->items->venuedescription)) != '') { ?>
            <div class="ts-venue-description">
                <?php echo $this->items->venuedescription; ?>
            </div>
        <?php } ?>

        <?php if ($this->config->show_available_tickets == 1) { ?>

            <h3 class="ts-subtitle" id="ts-availability-label"><?php echo Text::_('COM_TICKETSTATION_TICKETS_AVAILABLE'); ?></h3>

            <div id="percentage-available" class="ts-availability <?php echo $availability_class; ?>">
                <div class="ts-availability__track" role="progressbar" aria-labelledby="ts-availability-label" aria-valuenow="<?php echo $percentage_available; ?>" aria-valuemin="0" aria-valuemax="100">
                    <div id="percentage-available-bar" class="ts-availability__bar" style="width: <?php echo $available_tickets <= 0 ? 0 : max(1, $percentage_available); ?>%;"></div>
                </div>
                <span id="percentage-available-bar-text" class="ts-availability__text"><?php echo $available_tickets <= 0 ? Text::_('COM_TICKETSTATION_SOLD_OUT2') : $percentage_available . '%'; ?></span>
            </div>

        <?php } ?>
    </section>

    <table class="ts-table ts-tickets">
        <thead>
            <tr>
                <th scope="col"><?php echo Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
                <th scope="col"><?php echo Text::_('COM_TICKETSTATION_PRICE'); ?></th>
                <th scope="col"><?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?></th>
                <th scope="col"><span class="ts-visually-hidden"><?php echo Text::_('COM_TICKETSTATION_ORDER'); ?></span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ticketRows as $ticketRow) {

                $ticket    = $ticketRow->ticket;
                $ticketid  = (int) $ticket->ticketid;
                $published = ($ticket->eventpublished == 1) && ($ticket->ticketpublished == 1);
                $hasLimit  = ($ticket->min_qty != 0 || $ticket->max_qty != 0);
                $hasNote   = $ticketRow->fewLeft || !empty($ticket->free_text_1) || $hasLimit;

                ## Only offer a quantity when there is something to add it to (tickets or the waiting list)
                $canOrder = $published && ($ticketRow->available > 0 || $ticketRow->waitinglist);

                ## Quantities on offer: up to the ticket's maximum per order (10 without one), and no
                ## more than are left - unless it's sold out and the quantity is for the waiting list.
                $maxQty = $ticket->max_qty > 0 ? (int) $ticket->max_qty : 10;

                if ($ticketRow->available > 0) {
                    $maxQty = max(1, min($maxQty, (int) $ticketRow->available));
                }

                $minimum = str_replace('%%MIN_AMOUNT%%', $ticket->min_qty, Text::_('COM_TICKETSTATION_MINIMUM_FOR_ORDER'));
                $maximum = str_replace('%%MAX_AMOUNT%%', $ticket->max_qty, Text::_('COM_TICKETSTATION_MAXIMUM_FOR_ORDER'));
                ?>

                <tr class="ts-tickets__row<?php echo $hasNote ? ' ts-tickets__row--has-note' : ''; ?>">
                    <td class="ts-tickets__name"><?php echo htmlspecialchars($ticket->ticketname, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="ts-tickets__price"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $ticket->ticketprice, $this->config->valuta); ?></td>
                    <td class="ts-tickets__qty">
                        <?php if ($canOrder) { ?>
                            <label class="ts-visually-hidden" for="qty_<?php echo $ticketid; ?>"><?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?></label>
                            <select id="qty_<?php echo $ticketid; ?>" class="ts-select ts-select--qty">
                                <?php for ($qty = 1; $qty <= $maxQty; $qty++) { ?>
                                    <option value="<?php echo $qty; ?>"><?php echo $qty; ?></option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                    </td>
                    <td class="ts-tickets__action">
                        <?php if ($ticketRow->available <= 0) { ?>

                            <?php if ($ticketRow->waitinglist && $published) { ?>
                                <button type="button" class="ts-btn ts-btn--secondary ts-btn--sm ts-btn--waitinglist" onclick="waitinglist(<?php echo $ticketid; ?>)">
                                    <?php echo Text::_('COM_TICKETSTATION_JOIN_WAITINGLIST'); ?>
                                </button>
                            <?php } else { ?>
                                <span class="ts-badge ts-badge--soldout"><?php echo Text::_('COM_TICKETSTATION_SOLD_OUT2'); ?></span>
                            <?php } ?>

                        <?php } elseif ($published) { ?>

                            <button type="button" class="ts-btn ts-btn--secondary ts-btn--sm ts-btn--add" onclick="buytickets(<?php echo $ticketid; ?>)">
                                <?php echo Text::_('COM_TICKETSTATION_ORDER'); ?>
                            </button>

                        <?php } ?>
                    </td>
                </tr>

                <?php if ($hasNote) { ?>
                    <tr class="ts-tickets__note-row">
                        <td colspan="4">
                            <?php if ($ticketRow->fewLeft) { ?>
                                <div class="ts-alert ts-alert--warning"><?php echo Text::_('COM_TICKETSTATION_FEW_TICKETS_LEFT'); ?></div>
                            <?php } ?>

                            <?php if (!empty($ticket->free_text_1)) { ?>
                                <span class="ts-tickets__note"><?php echo $ticket->free_text_1; ?></span>
                            <?php } ?>

                            <?php if ($ticket->min_qty != 0) { ?>
                                <span class="ts-tickets__limit"><?php echo $minimum; ?></span>
                            <?php } ?>

                            <?php if ($ticket->max_qty != 0) { ?>
                                <span class="ts-tickets__limit"><?php echo $maximum; ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>

            <?php } ?>
        </tbody>
    </table>

    <!-- Filled with the result of adding tickets; don't remove -->
    <div id="message" class="ts-message" role="status" aria-live="polite" style="display: none;"></div>

    <div class="ts-actions">
        <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $shop_on; ?>">
            <?php echo Text::_('COM_TICKETSTATION_BACK'); ?>
        </a>

        <a id="continue-button" class="ts-btn ts-btn--primary ts-btn--next" href="<?php echo $gotocart; ?>"<?php echo (count($this->ordered) == 0 && $this->waiting == 0) ? ' style="display: none;"' : ''; ?>>
            <?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?>
        </a>
    </div>

</div>

<script type="text/javascript">

    function updateCart(){

        var order = 'ordercode=' + <?php echo $ordercode; ?> ;

        jQuery.ajax({
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
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=updatecart&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: order,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {
                if (!html.includes('empty_cart') || html.includes('waitinglist_items')) {
                    jQuery("#continue-button").show(0);
                } else {
                    jQuery("#continue-button").hide(0);
                }

            }
        });

    }

    function updateAvailable(){

        jQuery('#percentage-available-bar').addClass('is-loading');

        var ticketid = 'ticketid=' + <?php echo $this->items->ticketid; ?> ;

        jQuery("#percentage-available-bar").delay(1000).queue(function() {
            jQuery.ajax({
                //this is the php file that processes the data and send mail
                url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=updateavailable&format=raw",
                //POST method is used
                type: "POST",
                //pass the data
                data: ticketid,
                //Do not cache the page
                cache: false,
                //success
                success: function (html) {
                    jQuery("#percentage-available-bar").css("width", html).removeClass('is-loading');
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
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=buyticket&format=raw",
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
                jQuery( "#message" ).stop(true, true).html(html.msg).show();
                updateCart();
                updateAvailable();
                jQuery( "#message" ).delay(3000).fadeOut(500);
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
            url: "<?php echo Uri::root(true); ?>/index.php?option=com_ticketstation&controller=order&task=waitinglist&format=raw",
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
