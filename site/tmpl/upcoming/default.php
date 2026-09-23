<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
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
$document->setTitle( 'Tickets - ' . $app->get('sitename') );


$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
$document->addScript('components/com_ticketstation/assets/javascripts/countdown.js');

$ticketbackgroundimage = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/';

$fmt = datefmt_create(
    'nl_NL',
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    IntlDateFormatter::GREGORIAN,
    'EEEE d MMMM YYYY'
);

if ($this->config->variable_transcosts == 0) {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PER_ORDER',
        (new TicketstationFunctions)->showprice($this->config->priceformat ,$this->config->transactioncosts,$this->config->valuta));
} else {
    $transaction_costs = Text::sprintf('COM_TICKETSTATION_TRANSACTION_COSTS_PER_TICKET', $this->config->transcosts);
}

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the ordercode if there is one.
$ordercode = $session->get('ordercode');

## Total for this order:
$getAmount = new getAmount();
$ordertotal = $getAmount->_getAmount($ordercode);
$fees = $getAmount->_getFees($ordercode);

## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));

#class main column
if ($this->ticket->total > 0) {
    $class_main = 'col-xl-9';
} else {
    $class_main = 'col-12';
}
?>

<div class="row ticketstation">
    <div class="col-lg-9">

        <div class="page-header">
            <h1>Tickets</h1>
        </div>
        
        <?php if ((($this->mollie->test_mode == '1') || ($this->mollie->bypass_mode == '1')) && (($this->isadmin == '1'))) { ?>
            <?php if (($this->mollie->test_mode == '1') && ($this->mollie->bypass_mode == '1')) {
                $mollie_text = "BYPASS- & TEST-modus";
            } elseif ($this->mollie->test_mode == '1') {
                $mollie_text = "TEST-modus";
            } elseif ($this->mollie->bypass_mode == '1') {
                $mollie_text = "BYPASS-modus";
            } ?>
            <div class="alert alert-danger mollie-plugin-status"><span style="margin-bottom: 10px;"class="fa fa-exclamation-circle fa-3x"></span><br />LET OP! <?= $mollie_text; ?> is actief!</div>
        <?php } ?>

        <?php if (empty($this->events) && empty($this->upcoming) || ((($this->isadmin == '0') && (($this->mollie->test_mode == '1') || ($this->mollie->bypass_mode == '1'))) && empty($this->upcoming))) {?>

            <div class="ticketmaster_upcoming_event">
                <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                    <h3><strong>Geen evenementen</strong></h3>
                </div>
                <div class="ticketmaster_upcoming_event_content" style="padding: 7px 25px;">
                    <p><strong>Er zijn momenteel geen evenementen waarvoor tickets verkocht worden.</strong></p>
                    <p><strong>Houd onze website en socials in de gaten voor aankomende evenementen.</strong></p>
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

                                $ticketssold 	= (new Ticket)->getTicketsSoldById($row->ticketid);
                                $available_tickets = $row->starting_total_tickets - $ticketssold;

                                ?>



                                <div class="ticketmaster_upcoming_ticket" style="<?= $ticketbackgroundimage_css; ?>">

                                    <?php if ($available_tickets > 0) { ?>
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
                                                    <td width="100px">Datum:</td>
                                                    <td><?= datefmt_format($fmt, strtotime($row->startdate));?></td>
                                                </tr>
                                                <tr>
                                                    <td>Aanvang:</td>
                                                    <td><?= date('H:i', strtotime($row->startdate)); ?> uur</td>
                                                </tr>
                                                <?php if ($this->config->show_venue == 1) { ?>
                                                    <tr>
                                                        <td>Locatie:</td>
                                                        <td><?= $row->venue; ?> - <?= $row->city; ?></td>
                                                    </tr>
                                                <?php } ?>
                                                <?php if($this->config->show_price_eventlist == 1) { ?>
                                                    <tr>
                                                        <td>Prijs:</td>
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
                <?php if($this->config->show_price_eventlist == 1) { ?>
                    <div>
                        <p class="ticketmaster_upcoming_event_footer"><?= Text::sprintf('COM_TICKETSTATION_PRICES_EXCLUDE_TRANSACTION_COSTS', $transaction_costs); ?></p>
                    </div>
                <?php } ?>
            <?php } ?>

        <?php } ?>



        <?php if (!empty($this->upcoming)) {?>

            <div class="ticketmaster_upcoming_event">
                <div class="ticketmaster_upcoming_event_heading">

                    <h3 style="margin: 10px 0px 10px 10px;"><strong>Aankomende evenementen</strong></h3>

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
                                <div id="startverkooptiteldiv<?= $upcoming->eventid; ?>" style="margin-left: 10px;font-size: 1.1em;"><strong>Start verkoop over:</strong>
                                </div>


                                <div id="clockdiv<?= $upcoming->eventid; ?>" class="clockdiv">
                                    <div id="daysdiv<?= $upcoming->eventid; ?>">
                                        <span id="days<?= $upcoming->eventid; ?>" class="days"></span>
                                        <div id="dayscaption<?= $upcoming->eventid; ?>" class="smalltext">dagen</div>
                                    </div>
                                    <div id="hoursdiv<?= $upcoming->eventid; ?>">
                                        <span id="hours<?= $upcoming->eventid; ?>" class="hours"></span>
                                        <div id="hourscaption<?= $upcoming->eventid; ?>" class="smalltext">uren</div>
                                    </div>
                                    <div id="minutesdiv<?= $upcoming->eventid; ?>">
                                        <span id="minutes<?= $upcoming->eventid; ?>" class="minutes"></span>
                                        <div id="minutescaption<?= $upcoming->eventid; ?>" class="smalltext">minuten</div>
                                    </div>
                                    <div id="secondsdiv<?= $upcoming->eventid; ?>">
                                        <span id="seconds<?= $upcoming->eventid; ?>" class="seconds"></span>
                                        <div id="secondscaption<?= $upcoming->eventid; ?>" class="smalltext">seconden</div>
                                    </div>
                                </div>
                                <div style="font-weight:bold;margin-left:10px;font-size:1.5em;">
                                    <span id="renewpage<?= $upcoming->eventid; ?>"></span>
                                </div>
                                <div id="startverkoopdatumtijddiv<?= $upcoming->eventid; ?>" style="margin-left: 10px;font-size:0.9em;font-style:italic;color:#444;font-weight:bold;">
                                    (<?= datefmt_format($fmt, strtotime($upcoming->startdate));?> om <?= date('H:i', strtotime($upcoming->startdate)); ?> uur)
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

    <div class="col-lg-3">
        <div class="module">
            <div class="module-inner">
                <h3 class="module-title "><?= Text::_('COM_TICKETSTATION_CART'); ?> </h3>
                <div class="module-ct">
                    <?php if ($this->ticket->total > 0) { ?>
                        <div id="ticketmaster-cartdetails">

                            <div id="cart-information" class="cart-information">

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
                                        <td><?= Text::_('COM_TICKETSTATION_FEES'); ?></td>
                                        <td style="text-align: right;"><?php  echo (new TicketstationFunctions)->showprice($this->config->priceformat, $fees, $this->config->valuta); ?></td>
                                    </tr>
                                    <tr style="border-top: 1px solid #aaa;">
                                        <td><strong><?php  echo Text::_('COM_TICKETSTATION_ORDERTOTAL_CART'); ?></strong></td>
                                        <td style="text-align: right;"><strong><?php  echo (new TicketstationFunctions)->showprice($this->config->priceformat, $ordertotal, $this->config->valuta); ?></strong></td>
                                    </tr>
                                </table>

                                <div style="margin-top: 20px;">
                                    <a class="btn btn-primary pull-right" onClick="location.href='<?= $gotocart; ?>'">
                                        <span>Bekijken</span>
                                    </a>
                                </div>

                            </div>

                        </div>
                    <?php } else { ?>
                        <div>Je winkelmand is leeg</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

</div>




