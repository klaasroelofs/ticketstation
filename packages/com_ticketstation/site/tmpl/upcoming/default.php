<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS') . ' - ' . $app->get('sitename') );


TicketstationFunctions::addSiteStylesheet();
$document->addScript('components/com_ticketstation/assets/javascripts/countdown.js');

## Texts used by countdown.js (read there through Joomla.Text, which needs core.js)
$document->getWebAssetManager()->useScript('core');
foreach (['DAY', 'DAYS', 'HOUR', 'HOURS', 'MINUTE', 'MINUTES', 'SECOND', 'SECONDS', 'RELOADING'] as $countdownText) {
    Text::script('COM_TICKETSTATION_COUNTDOWN_' . $countdownText);
}

## Day and month names follow the active site language (e.g. "zaterdag 3 oktober 2026" / "Saturday 3 October 2026")
$fmt = datefmt_create(
    str_replace('-', '_', $app->getLanguage()->getTag()),
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    IntlDateFormatter::GREGORIAN,
    'EEEE d MMMM yyyy'
);

## Without online payments only free tickets are sold on the website: no transaction costs.
$show_transaction_costs = $this->config->variable_transcosts != 2 && Shop::paymentsOn();

if ($this->config->variable_transcosts == 0) {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PER_ORDER',
        (new TicketstationFunctions)->showprice($this->config->priceformat ,$this->config->transactioncosts,$this->config->valuta));
} elseif ($show_transaction_costs) {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PERCENTAGE', $this->config->transcosts);
}

## Menu item for the links to the ticket views
$itemid = TicketstationFunctions::getSiteItemid();

## Background image of a ticket or event, handed to component.css as --ts-ticket-image
$backgroundStyle = [TicketstationFunctions::class, 'backgroundImageStyle'];

?>

<div class="ticketstation ticketstation--upcoming">

    <div class="page-header">
        <h1 class="ts-page-title"><?= Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS'); ?></h1>
    </div>

    <?php if ($this->testmode) {
        $mollie_text = Text::_('COM_TICKETSTATION_MOLLIE_MODE_TEST');
        ?>
        <div class="ts-alert ts-alert--danger ts-mode-notice" role="alert">
            <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 3.5a.9.9 0 0 1 .9.95l-.25 4.3a.65.65 0 0 1-1.3 0l-.25-4.3A.9.9 0 0 1 8 4.5zm0 6.6a.85.85 0 1 1 0 1.7.85.85 0 0 1 0-1.7z"/></svg>
            <span><?= Text::sprintf('COM_TICKETSTATION_MOLLIE_MODE_ACTIVE', $mollie_text); ?></span>
        </div>
    <?php } ?>

    <?php if (empty($this->events) && empty($this->upcoming)) {?>

        <section class="ts-card ts-empty">
            <h2 class="ts-card__title"><?= Text::_('COM_TICKETSTATION_NO_EVENTS'); ?></h2>
            <p><?= Text::_('COM_TICKETSTATION_NO_EVENTS_DESC'); ?></p>
            <p><?= Text::_('COM_TICKETSTATION_NO_EVENTS_STAY_TUNED'); ?></p>
        </section>

    <?php } else { ?>

        <?php foreach ($this->events as $event) { ?>

            <section class="ts-card ts-event">
                <h2 class="ts-card__title ts-event__title"><?= $event->eventname; ?></h2>

                <div class="ts-event__tickets">

                    <?php
                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ):

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];

                        if ($row->eventid == $event->eventid) {

                            if ($row->show_seatplans == 1)
                            {
                                $link 		= Route::_('index.php?option=com_ticketstation&view=seatedevent&cid='.$row->ticketid . ($itemid ? '&Itemid=' . $itemid : ''));
                            }
                            else
                            {
                                $link 		= Route::_('index.php?option=com_ticketstation&view=event&id='.$row->ticketid . ($itemid ? '&Itemid=' . $itemid : ''));
                            }

                            ## The ticket's own background, or else the event's
                            $ticketbackgroundimage_css = $backgroundStyle('ticket' . $row->ticketid) ?: $backgroundStyle('event' . $row->eventid);

                            // Over all published variants (child tickets) and following their counter
                            // settings; for seated tickets the free seats on the chart.
                            $availability      = Availability::summary((int) $row->ticketid);
                            $available_tickets = $availability->available;

                            // The badge colour follows the share still available, with the same
                            // thresholds as the availability bar in the event view.
                            $percentage_available = ($availability->capacity > 0)
                                ? round((($available_tickets / $availability->capacity) * 100), 0)
                                : 0;
                            $availability_badge = $percentage_available < 11 ? 'ts-badge--critical'
                                : ($percentage_available < 26 ? 'ts-badge--few' : 'ts-badge--available');

                            // A sold-out ticket stays clickable when the waiting list is on, so the
                            // customer can reach the event page to join it. Seated tickets are excluded:
                            // their seat-picker has no waiting list.
                            $waitinglist_open = ($available_tickets < 1 && $this->config->show_waitinglist == 1 && $row->show_seatplans != 1);

                            // With online payments off, a ticket of which nothing is free is only sold at
                            // the box office; a seating chart as soon as one of its seats costs something.
                            $box_office_only = Shop::boxOfficeOnly((int) $row->ticketid, $row->show_seatplans != 1);

                            if ($box_office_only) {
                                $waitinglist_open = false;
                            }

                            $clickable = !$box_office_only && ($available_tickets > 0 || $waitinglist_open);

                            $ticket_classes = 'ts-ticket'
                                . ($clickable ? ' ts-ticket--link' : '')
                                . ($available_tickets < 1 ? ' ts-ticket--soldout' : '')
                                . ($ticketbackgroundimage_css ? ' ts-ticket--has-image' : '');

                            ?>

                            <article class="<?= $ticket_classes; ?>"<?= $ticketbackgroundimage_css ? ' style="' . $ticketbackgroundimage_css . '"' : ''; ?>>

                                <div class="ts-ticket__header">
                                    <h3 class="ts-ticket__title">
                                        <?php if ($clickable) { ?>
                                            <a class="ts-ticket__link" href="<?= $link; ?>"><?= $row->ticketname; ?></a>
                                        <?php } else { ?>
                                            <?= $row->ticketname; ?>
                                        <?php } ?>
                                    </h3>
                                </div>

                                <div class="ts-ticket__body">

                                    <?php if (trim(strip_tags((string) $row->eventdescription)) !== '') { ?>
                                        <div class="ts-ticket__description"><?= $row->eventdescription; ?></div>
                                    <?php } ?>

                                    <dl class="ts-meta">
                                        <dt><?= Text::_('COM_TICKETSTATION_DATE'); ?></dt>
                                        <dd><?= datefmt_format($fmt, strtotime($row->startdate));?></dd>

                                        <dt><?= Text::_('COM_TICKETSTATION_START_TIME'); ?></dt>
                                        <dd><?= Text::sprintf('COM_TICKETSTATION_TIME_OCLOCK', date('H:i', strtotime($row->startdate))); ?></dd>

                                        <?php if ($this->config->show_venue == 1) { ?>
                                            <dt><?= Text::_('COM_TICKETSTATION_VENUE'); ?></dt>
                                            <dd><?= $row->venue; ?> - <?= $row->city; ?></dd>
                                        <?php } ?>

                                        <?php if($this->config->show_price_eventlist == 1) { ?>
                                            <dt><?= Text::_('COM_TICKETSTATION_PRICE'); ?></dt>
                                            <?php
                                            // With variants the price is theirs: one price, or "from" the lowest.
                                            if ($row->variant_min_price !== null) {
                                                $price = (new TicketstationFunctions)->showprice($this->config->priceformat, $row->variant_min_price, $this->config->valuta);

                                                if ((float) $row->variant_min_price != (float) $row->variant_max_price) {
                                                    $price = Text::sprintf('COM_TICKETSTATION_PRICE_FROM', $price);
                                                }
                                            } else {
                                                $price = (new TicketstationFunctions)->showprice($this->config->priceformat, $row->ticketprice, $this->config->valuta);
                                            }
                                            ?>
                                            <dd class="ts-ticket__price"><strong><?= $price; ?></strong></dd>
                                        <?php } ?>
                                    </dl>

                                    <?php if ((($this->config->show_quantity_eventlist == 1) && ($available_tickets > 0)) || $available_tickets < 1 || $box_office_only) { ?>
                                        <div class="ts-ticket__status">
                                            <?php if ($box_office_only && $available_tickets > 0) { ?>
                                                <span class="ts-badge ts-badge--boxoffice"><?= Text::_( 'COM_TICKETSTATION_AT_THE_BOX_OFFICE' ); ?></span>
                                            <?php } elseif ($available_tickets < 1) { ?>
                                                <span class="ts-badge ts-badge--soldout"><?= Text::_( 'COM_TICKETSTATION_SOLD_OUT2' ); ?></span>
                                                <?php if ($waitinglist_open) { ?>
                                                    <span class="ts-badge ts-badge--waitinglist"><?= Text::_( 'COM_TICKETSTATION_WAITINGLIST_AVAILABLE' ); ?></span>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="ts-badge <?= $availability_badge; ?>"><?= Text::_( 'COM_TICKETSTATION_PLACES_LEFT' ); ?> <?= $available_tickets; ?></span>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>

                                </div>
                            </article>
                        <?php } ?>
                    <?php endfor; ?>
                </div>
            </section>
        <?php } ?>

    <?php } ?>

    <?php if (!empty($this->upcoming)) {?>

        <section class="ts-card ts-event ts-event--upcoming">
            <h2 class="ts-card__title ts-event__title"><?= Text::_('COM_TICKETSTATION_UPCOMING_EVENTS'); ?></h2>

            <div class="ts-event__tickets">
                <?php foreach ($this->upcoming as $upcoming) {

                    $ticketbackgroundimage_css = $backgroundStyle('event' . $upcoming->eventid);
                    ?>

                    <article class="ts-ticket ts-ticket--presale<?= $ticketbackgroundimage_css ? ' ts-ticket--has-image' : ''; ?>"<?= $ticketbackgroundimage_css ? ' style="' . $ticketbackgroundimage_css . '"' : ''; ?>>

                        <div class="ts-ticket__header">
                            <h3 class="ts-ticket__title"><?= $upcoming->eventname; ?></h3>
                        </div>

                        <div class="ts-ticket__body">
                            <p id="startverkooptiteldiv<?= $upcoming->eventid; ?>" class="ts-countdown-title"><?= Text::_('COM_TICKETSTATION_SALE_STARTS_IN'); ?>:</p>

                            <div id="clockdiv<?= $upcoming->eventid; ?>" class="ts-countdown">
                                <?php foreach (['days' => 'DAYS', 'hours' => 'HOURS', 'minutes' => 'MINUTES', 'seconds' => 'SECONDS'] as $unit => $caption) { ?>
                                    <div id="<?= $unit . 'div' . $upcoming->eventid; ?>" class="ts-countdown__unit">
                                        <span id="<?= $unit . $upcoming->eventid; ?>" class="ts-countdown__value"></span>
                                        <span id="<?= $unit . 'caption' . $upcoming->eventid; ?>" class="ts-countdown__label"><?= Text::_('COM_TICKETSTATION_COUNTDOWN_' . $caption); ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="ts-countdown-reload" id="renewpage<?= $upcoming->eventid; ?>"></div>

                            <p id="startverkoopdatumtijddiv<?= $upcoming->eventid; ?>" class="ts-countdown-date">
                                (<?= Text::sprintf('COM_TICKETSTATION_SALE_STARTS_AT', datefmt_format($fmt, strtotime($upcoming->startdate)), date('H:i', strtotime($upcoming->startdate))); ?>)
                            </p>
                        </div>
                    </article>

                    <script>
                        initializeClock('<?= $upcoming->eventid; ?>', '<?= $upcoming->startdate; ?>');
                    </script>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <?php ## Once for the whole page, and only when priced tickets are actually listed
    if (!empty($this->events) && $this->config->show_price_eventlist == 1 && $show_transaction_costs) { ?>
        <p class="ts-note ts-note--center"><?= Text::sprintf('COM_TICKETSTATION_PRICES_EXCLUDE_TRANSACTION_COSTS', $transaction_costs); ?></p>
    <?php } ?>

</div>
