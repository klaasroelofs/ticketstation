<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
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
$document->setTitle( Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS') . ' - ' . $app->get('sitename') );


$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
$document->addScript('components/com_ticketstation/assets/javascripts/countdown.js');

## Texts used by countdown.js (read there through Joomla.Text, which needs core.js)
$document->getWebAssetManager()->useScript('core');
foreach (['DAY', 'DAYS', 'HOUR', 'HOURS', 'MINUTE', 'MINUTES', 'SECOND', 'SECONDS', 'RELOADING'] as $countdownText) {
    Text::script('COM_TICKETSTATION_COUNTDOWN_' . $countdownText);
}

$ticketbackgroundimage = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/';

## Day and month names follow the active site language (e.g. "zaterdag 3 oktober 2026" / "Saturday 3 October 2026")
$fmt = datefmt_create(
    str_replace('-', '_', $app->getLanguage()->getTag()),
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    IntlDateFormatter::GREGORIAN,
    'EEEE d MMMM yyyy'
);

$show_transaction_costs = $this->config->variable_transcosts != 2;

if ($this->config->variable_transcosts == 0) {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PER_ORDER',
        (new TicketstationFunctions)->showprice($this->config->priceformat ,$this->config->transactioncosts,$this->config->valuta));
} elseif ($show_transaction_costs) {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PER_TICKET', $this->config->transcosts);
}

## Menu item for the links to the ticket views
$itemid = TicketstationFunctions::getSiteItemid();

?>

<div class="row ticketstation">
    <div class="col-12">

        <div class="page-header">
            <h1><?= Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS'); ?></h1>
        </div>

        <?php if ((($this->mollie->test_mode == '1') || ($this->mollie->bypass_mode == '1')) && (($this->isadmin == '1'))) { ?>
            <?php if (($this->mollie->test_mode == '1') && ($this->mollie->bypass_mode == '1')) {
                $mollie_text = Text::_('COM_TICKETSTATION_MOLLIE_MODE_BYPASS_AND_TEST');
            } elseif ($this->mollie->test_mode == '1') {
                $mollie_text = Text::_('COM_TICKETSTATION_MOLLIE_MODE_TEST');
            } elseif ($this->mollie->bypass_mode == '1') {
                $mollie_text = Text::_('COM_TICKETSTATION_MOLLIE_MODE_BYPASS');
            } ?>
            <div class="alert alert-danger mollie-plugin-status"><span style="margin-bottom: 10px;"class="fa fa-exclamation-circle fa-3x"></span><br /><?= Text::sprintf('COM_TICKETSTATION_MOLLIE_MODE_ACTIVE', $mollie_text); ?></div>
        <?php } ?>

        <?php if (empty($this->events) && empty($this->upcoming) || ((($this->isadmin == '0') && (($this->mollie->test_mode == '1') || ($this->mollie->bypass_mode == '1'))) && empty($this->upcoming))) {?>

            <div class="ticketmaster_upcoming_event">
                <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                    <h3><strong><?= Text::_('COM_TICKETSTATION_NO_EVENTS'); ?></strong></h3>
                </div>
                <div class="ticketmaster_upcoming_event_content" style="padding: 7px 25px;">
                    <p><strong><?= Text::_('COM_TICKETSTATION_NO_EVENTS_DESC'); ?></strong></p>
                    <p><strong><?= Text::_('COM_TICKETSTATION_NO_EVENTS_STAY_TUNED'); ?></strong></p>
                </div>
            </div>

        <?php } else { ?>

            <?php
            foreach ($this->events as $event) { ?>

                <div class="ticketmaster_upcoming_event">
                    <div class="ticketmaster_upcoming_event_heading">

                        <h3 style="margin: 10px 0px 10px 10px;"><strong><?= $event->eventname; ?></strong></h3>

                    </div>

                    <div class="ticketmaster_upcoming_event_content">

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

                                $ticketbackgroundimage_css = '';
                                if (file_exists(JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/ticket' . $row->ticketid . '.jpg')) {
                                    $ticketbackgroundimage = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/ticket'.$row->ticketid . '.jpg';
                                    $ticketbackgroundimage_css = "background-image: linear-gradient(rgba(255,255,255,0.6), rgba(255,255,255,0.6)), url('". $ticketbackgroundimage ."');color:#000;";
                                } elseif (file_exists(JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $row->eventid . '.jpg')){
                                    $ticketbackgroundimage = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/event'.$row->eventid . '.jpg';
                                    $ticketbackgroundimage_css = "background-image: linear-gradient(rgba(255,255,255,0.6), rgba(255,255,255,0.6)), url('". $ticketbackgroundimage ."');color:#000;";
                                }

                                // Over all published variants (child tickets) and following their counter
                                // settings; for seated tickets the free seats on the chart.
                                $available_tickets = Availability::forListing((int) $row->ticketid);

                                // A sold-out ticket stays clickable when the waiting list is on, so the
                                // customer can reach the event page to join it. Seated tickets are excluded:
                                // their seat-picker has no waiting list.
                                $waitinglist_open = ($available_tickets < 1 && $this->config->show_waitinglist == 1 && $row->show_seatplans != 1);

                                ?>



                                <div class="ticketmaster_upcoming_ticket" style="<?= $ticketbackgroundimage_css; ?>">

                                    <?php if ($available_tickets > 0 || $waitinglist_open) { ?>
                                        <a href="<?= $link; ?>">
                                            <span class="ticketmaster_upcoming_ticketlink"></span>
                                        </a>
                                    <?php } ?>

                                    <div class="ticketmaster_upcoming_ticket_heading">

                                        <h4 style="margin-left: 10px;"><strong><?= $row->ticketname; ?></strong></h4>

                                    </div>

                                    <div class="ticketmaster_upcoming_ticket_content" >


                                        <?= $row->eventdescription; ?>



                                        <div>
                                            <table>
                                                <tr>
                                                    <td width="100px"><?= Text::_('COM_TICKETSTATION_DATE'); ?>:</td>
                                                    <td><?= datefmt_format($fmt, strtotime($row->startdate));?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= Text::_('COM_TICKETSTATION_START_TIME'); ?>:</td>
                                                    <td><?= Text::sprintf('COM_TICKETSTATION_TIME_OCLOCK', date('H:i', strtotime($row->startdate))); ?></td>
                                                </tr>
                                                <?php if ($this->config->show_venue == 1) { ?>
                                                    <tr>
                                                        <td><?= Text::_('COM_TICKETSTATION_VENUE'); ?>:</td>
                                                        <td><?= $row->venue; ?> - <?= $row->city; ?></td>
                                                    </tr>
                                                <?php } ?>
                                                <?php if($this->config->show_price_eventlist == 1) { ?>
                                                    <tr>
                                                        <td><?= Text::_('COM_TICKETSTATION_PRICE'); ?>:</td>
                                                        <td><strong><?= (new TicketstationFunctions)->showprice($this->config->priceformat ,$row->ticketprice,$this->config->valuta); ?></strong></td>
                                                    </tr>
                                                <?php } ?>
                                                <?php if (($this->config->show_quantity_eventlist == 1) && ($available_tickets > 0)) { ?>
                                                    <tr>
                                                        <?php if ($available_tickets < 50) { ?>
                                                            <td style="vertical-align:middle; height:40px;" colspan="2">
                                                                <div class="label label-warning">
                                                                    <?= Text::_( 'COM_TICKETSTATION_PLACES_LEFT' ); ?> <?= $available_tickets; ?>
                                                                </div>
                                                            </td>
                                                        <?php } else { ?>
                                                            <td style="vertical-align:middle; height:40px;" colspan="2">
                                                                <div class="label label-info">
                                                                    <?= Text::_( 'COM_TICKETSTATION_PLACES_LEFT' ); ?> <?= $available_tickets; ?>
                                                                </div>
                                                            </td>
                                                        <?php } ?>
                                                    </tr>
                                                <?php } ?>
                                                <?php if ($available_tickets < 1) { ?>
                                                    <tr>
                                                        <td style="vertical-align:middle; height:40px;" colspan="2">
                                                            <div class="label label-important ">
                                                                <?= Text::_( 'COM_TICKETSTATION_SOLD_OUT2' ); ?>
                                                            </div>
                                                            <?php if ($waitinglist_open) { ?>
                                                                <div class="label label-info" style="margin-left: 5px;">
                                                                    <?= Text::_( 'COM_TICKETSTATION_WAITINGLIST_AVAILABLE' ); ?>
                                                                </div>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php if($this->config->show_price_eventlist == 1 && $show_transaction_costs) { ?>
                    <div>
                        <p class="ticketmaster_upcoming_event_footer"><?= Text::sprintf('COM_TICKETSTATION_PRICES_EXCLUDE_TRANSACTION_COSTS', $transaction_costs); ?></p>
                    </div>
                <?php } ?>
            <?php } ?>

        <?php } ?>



        <?php if (!empty($this->upcoming)) {?>

            <div class="ticketmaster_upcoming_event">
                <div class="ticketmaster_upcoming_event_heading">

                    <h3 style="margin: 10px 0px 10px 10px;"><strong><?= Text::_('COM_TICKETSTATION_UPCOMING_EVENTS'); ?></strong></h3>

                </div>
                <?php foreach ($this->upcoming as $upcoming) {

                    $ticketbackgroundimage_css = '';
                    if (file_exists(JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $upcoming->eventid . '.jpg')){
                        $ticketbackgroundimage = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/event'.$upcoming->eventid . '.jpg';
                        $ticketbackgroundimage_css = "background-image: linear-gradient(rgba(255,255,255,0.6), rgba(255,255,255,0.6)), url('". $ticketbackgroundimage ."');color:#000;";
                    }
                    ?>



                    <div class="ticketmaster_upcoming_event_content">

                        <div class="ticketmaster_upcoming_ticket" style="<?= $ticketbackgroundimage_css; ?>">

                            <div class="ticketmaster_upcoming_ticket_heading" >

                                <h4 style="margin-left: 10px;"><strong><?= $upcoming->eventname; ?></strong></h4>

                            </div>

                            <div class="ticketmaster_upcoming_ticket_content">
                                <div id="startverkooptiteldiv<?= $upcoming->eventid; ?>" style="margin-left: 10px;font-size: 1.1em;"><strong><?= Text::_('COM_TICKETSTATION_SALE_STARTS_IN'); ?>:</strong>
                                </div>


                                <div id="clockdiv<?= $upcoming->eventid; ?>" class="clockdiv">
                                    <div id="daysdiv<?= $upcoming->eventid; ?>">
                                        <span id="days<?= $upcoming->eventid; ?>" class="days"></span>
                                        <div id="dayscaption<?= $upcoming->eventid; ?>" class="smalltext"><?= Text::_('COM_TICKETSTATION_COUNTDOWN_DAYS'); ?></div>
                                    </div>
                                    <div id="hoursdiv<?= $upcoming->eventid; ?>">
                                        <span id="hours<?= $upcoming->eventid; ?>" class="hours"></span>
                                        <div id="hourscaption<?= $upcoming->eventid; ?>" class="smalltext"><?= Text::_('COM_TICKETSTATION_COUNTDOWN_HOURS'); ?></div>
                                    </div>
                                    <div id="minutesdiv<?= $upcoming->eventid; ?>">
                                        <span id="minutes<?= $upcoming->eventid; ?>" class="minutes"></span>
                                        <div id="minutescaption<?= $upcoming->eventid; ?>" class="smalltext"><?= Text::_('COM_TICKETSTATION_COUNTDOWN_MINUTES'); ?></div>
                                    </div>
                                    <div id="secondsdiv<?= $upcoming->eventid; ?>">
                                        <span id="seconds<?= $upcoming->eventid; ?>" class="seconds"></span>
                                        <div id="secondscaption<?= $upcoming->eventid; ?>" class="smalltext"><?= Text::_('COM_TICKETSTATION_COUNTDOWN_SECONDS'); ?></div>
                                    </div>
                                </div>
                                <div style="font-weight:bold;margin-left:10px;font-size:1.5em;">
                                    <span id="renewpage<?= $upcoming->eventid; ?>"></span>
                                </div>
                                <div id="startverkoopdatumtijddiv<?= $upcoming->eventid; ?>" style="margin-left: 10px;font-size:0.9em;font-style:italic;color:#444;font-weight:bold;">
                                    (<?= Text::sprintf('COM_TICKETSTATION_SALE_STARTS_AT', datefmt_format($fmt, strtotime($upcoming->startdate)), date('H:i', strtotime($upcoming->startdate))); ?>)
                                </div>

                            </div>
                        </div>
                    </div>

                    <script>
                        initializeClock('<?= $upcoming->eventid; ?>', '<?= $upcoming->startdate; ?>');
                    </script>
                <?php } ?>

            </div>
        <?php } ?>



    </div>
</div>




