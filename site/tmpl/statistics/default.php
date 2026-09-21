<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
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
$document->setTitle( 'Verkopen & Scans' . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/statistics.css' );
HTMLHelper::_('jquery.framework');
$document->addScript('components/com_ticketstation/assets/javascripts/showLogout.js');


if (date("d-m-Y", strtotime($this->lastorder->orderdate)) == date("d-m-Y")) {
    $lastorderday = "vandaag";
} elseif (date("d-m-Y", strtotime($this->lastorder->orderdate)) == date("d-m-Y", strtotime('yesterday'))) {
    $lastorderday = "gisteren";
} else {
    $lastorderday = date("d-m-Y", strtotime($this->lastorder->orderdate));
}

?>

<div class="row ticketstation">

    <div class="page-header">
        <h1>Verkopen & Scans</h1>
    </div>

	<div>
        <div style="max-width:750px;margin-left:auto;margin-right:auto;display: block;">
            <p style='font-size:10pt;font-style:italic;'><b>Stand per:</b> <?php echo date('d-m-Y H:i');?> uur</p>
        </div>
        <div style="margin-bottom:25px;">
            <div class="userinfo" style="display: block;margin: 0 auto;">
                <i class="bi bi-shield-lock"></i> Je bent ingelogd als <strong><?php echo $this->user->name; ?></strong>
                <span class="userinfo-icon"><i id="arrow" class="bi bi-chevron-down"></i></span>
            </div>
            <div class="userinfo-logout" style="display: none;margin: 0 auto;">
                <form action="<?php echo Route::_('index.php', true); ?>" method="post" id="login-form">

                    <div>
                        <input type="submit" name="Submit" class="btn btn-danger" value="<?php echo Text::_('JLOGOUT'); ?>" />
                        <input type="hidden" name="option" value="com_users" />
                        <input type="hidden" name="task" value="user.logout" />
                        <input type="hidden" name="return" value="<?php echo $return; ?>" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </div>
                </form>
            </div>
        </div>
	</div>

    <div class="col-12">
        <div class="ticketmaster_salesscans_card_wrapper" >
            <div class="ticketmaster_salesscans_card_wrapper_heading">
                <h3 style="margin: 10px 0px 10px 10px;"><strong><?php echo $this->ticket->eventname; ?></strong></h3>
                <h4 style="margin: 10px 0px 10px 10px;"><strong>Verkoop <?php echo ($this->salesstats=='ticket'?$this->ticket->ticketname:'totaal');?></strong></h4>
            </div>
            <div class="ticketmaster_salesscans_card_wrapper_content" style="text-align: center;">

                <?php if ($this->isadmin) { ?>

                    <div class="ticketmaster_salesscans_card wide">
                        <?php if (($this->mollie->bypass_mode == '1') && ($this->mollie->test_mode == '1')) { ?>
                            <div class="alert alert-danger" style="padding:10px;text-align:center;font-weight:bold;color:#BB2721;border-radius:10px;margin-bottom:unset;"><span style="margin-bottom: 10px;" class="fa fa-exclamation-circle fa-3x"></span><br />LET OP!<br />De Mollie-plugin staat in BYPASS- en TEST-modus!</div>
                        <?php } elseif ($this->mollie->bypass_mode == '1') { ?>
                            <div class="alert alert-danger" style="padding:10px;text-align:center;font-weight:bold;color:#BB2721;border-radius:10px;margin-bottom:unset;"><span style="margin-bottom: 10px;"class="fa fa-exclamation-circle fa-3x"></span><br />LET OP! De Mollie-plugin staat in BYPASS-modus!</div>
                        <?php } elseif ($this->mollie->test_mode == '1') { ?>
                            <div class="alert alert-danger" style="padding:10px;text-align:center;font-weight:bold;color:#BB2721;border-radius:10px;margin-bottom:unset;"><span style="margin-bottom: 10px;"class="fa fa-exclamation-circle fa-3x"></span><br />LET OP! De Mollie-plugin staat in TEST-modus!</div>
                        <?php } else { ?>
                            <div class="alert alert-success" style="padding:10px;text-align:center;font-weight:bold;color:#008C39;border-radius:10px;margin-bottom:unset;background-color:#008c392e;"><span style="margin-bottom: 10px;"class="fa fa-check-circle fa-3x"></span><br />Correct ingesteld voor online verkoop</div>
                        <?php } ?>
                    </div>

                <?php } ?>

                <?php if ($this->ordercount == 0) { ?>
                    <div style="margin-bottom: 25px;">
                        <h4><strong>Er zijn nog geen tickets verkocht</strong></h4>
                    </div>
                <?php } else { ?>

                    <div class="ticketmaster_salesscans_card">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Verkocht</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;"><?php echo $this->soldtickets; ?> / <?php echo $this->startingtickets; ?></span>
                        </div>
                    </div>
                    <div class="ticketmaster_salesscans_card">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Beschikbaar</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;"><?php echo $this->availabletickets; ?> / <?php echo round((($this->availabletickets / $this->startingtickets) * 100), 0); ?>%</span>
                        </div>
                    </div>
                    <div class="ticketmaster_salesscans_card">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Bestellingen</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;"><?php echo $this->ordercount; ?></span>
                        </div>
                    </div>
                    <div class="ticketmaster_salesscans_card">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>In bestelproces</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;"><?php echo $this->unfinished->tickets; ?> <?php echo ($this->unfinished->tickets == 1 ? "ticket" : "tickets");?><?php echo ($this->isadmin ? " / " . $this->unfinished->orders . " " . ($this->unfinished->orders == 1 ? "order" : "orders"): ""); ?></span>
                        </div>
                    </div>

                    <?php if (($this->salesstats == 'event') && ($this->salesperticket == 1)) { ?>
                        <div class="ticketmaster_salesscans_card wide">
                            <div class="ticketmaster_salesscans_card_heading">
                                <span style="margin: 5px;"><strong>Verkopen per ticket</strong></span>
                            </div>
                            <div class="ticketmaster_salesscans_card_content">


                                <?php foreach($this->ticketinfogrouped as $ticket) {
                                    $startingtickets = $ticket->starting_total_tickets;

                                    $soldtickets = 0;
                                    foreach($this->soldticketsgrouped as $soldperticket) {
                                        if ($soldperticket->ticketid == $ticket->ticketid) {
                                            $soldtickets = $soldperticket->total_tickets_sold;
                                        }
                                    }

                                    $availabletickets = $startingtickets - $soldtickets;

                                    $ordercount = 0;
                                    foreach($this->ordercountgrouped as $ordercountperticket) {
                                        if ($ordercountperticket->ticketid == $ticket->ticketid) {
                                            $ordercount = $ordercountperticket->orders;
                                        }
                                    }

                                    ?>
                                    <table class="sales_per_ticket_table">
                                        <thead>
                                        <th colspan="3"><b><?php echo $ticket->ticketname; ?></b></td>
                                        </thead>
                                        <tr>
                                            <td width="33%"><b>verkocht</b></td>
                                            <td width="33%"><b>beschikbaar</b></td>
                                            <td width="33%"><b>bestellingen</b></td>
                                        </tr>
                                        <tr>
                                            <td><?php echo $soldtickets; ?> / <?php echo $startingtickets; ?></td>
                                            <td><?php echo $availabletickets; ?> / <?php echo round((( $availabletickets/ $startingtickets) * 100), 0)?>%</td>
                                            <td><?php echo $ordercount; ?></td>
                                        </tr>
                                    </table>

                                <?php } ?>

                            </div>
                        </div>
                    <?php } ?>

                    <div class="ticketmaster_salesscans_card wide">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Laatste bestelling</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;">
                                <?php echo $lastorderday; ?> om <?php echo date("H:i", strtotime($this->lastorder->orderdate)); ?> uur <br/>
                                <em>(<?php echo $this->lastorder->quantity;?> <?php echo ($this->lastorder->quantity == 1 ? "ticket" : "tickets");?><?php echo ($this->isadmin ? " / " . $this->lastorder->name : "")?>)</em>
                            </span>
                        </div>
                    </div>

                    <?php if ($this->isadmin) { ?>

                        <?php if (($this->pdfnotcreated->tickets > 0) || ($this->pdfnotsent->tickets > 0)) { ?>
                            <div class="ticketmaster_salesscans_card">
                                <div class="ticketmaster_salesscans_card_heading">
                                    <span style="margin: 5px;"><strong>PDF niet gemaakt</strong></span>
                                </div>
                                <div class="ticketmaster_salesscans_card_content">
                                    <span style="margin: 5px;color: red;"><?php echo $this->pdfnotcreated->tickets; ?> <?php echo ($this->pdfnotcreated->tickets == 1 ? "ticket" : "tickets");?> / <?php echo $this->pdfnotcreated->orders; ?> <?php echo ($this->pdfnotcreated->orders == 1 ? "order" : "orders");?></span>
                                </div>
                            </div>
                            <div class="ticketmaster_salesscans_card">
                                <div class="ticketmaster_salesscans_card_heading">
                                    <span style="margin: 5px;"><strong>PDF niet verzonden</strong></span>
                                </div>
                                <div class="ticketmaster_salesscans_card_content">
                                    <span style="margin: 5px;color: red;"><?php echo $this->pdfnotsent->tickets; ?> <?php echo ($this->pdfnotsent->tickets == 1 ? "ticket" : "tickets");?> / <?php echo $this->pdfnotsent->orders; ?> <?php echo ($this->pdfnotsent->orders == 1 ? "order" : "orders");?></span>
                                </div>
                            </div>
                        <?php } ?>

                        <div class="ticketmaster_salesscans_card wide">
                            <div class="ticketmaster_salesscans_card_heading">
                                <span style="margin: 5px;"><strong>Klanten met meerdere orders</strong></span>
                            </div>
                            <div class="ticketmaster_salesscans_card_content">
                                <div id="multipleorderscount" style="cursor:pointer;">
                                    <span style="margin: 5px;"><?php echo count($this->orders_per_customer); ?></span>
                                    <span class="userinfo-icon"><i id="clients-multiple-orders-arrow" class="bi bi-chevron-down"></i></span>
                                </div>
                                <div id="multipleordersdetails" style="display:none;">
                                    <hr/>
                                    <table class="table-striped" style="width:100%;max-width:350px;margin-left:auto;margin-right:auto;">
                                        <thead>
                                        <th width='5%'></th>
                                        <th width='50%'></th>
                                        <th width='15%' style="text-align:right;">orders</th>
                                        <th width='15%' style="text-align:right;">tickets</th>
                                        <th width='15%' style="text-align:right;">laatste</th>
                                        </thead>
                                        <?php $linenumber = 1; ?>
                                        <?php foreach($this->orders_per_customer as $client) { ?>
                                            <tr>
                                                <td style="text-align:right;padding-right:10px;"><?php echo $linenumber; ?>.</td>
                                                <td><?php echo htmlspecialchars($client->firstname, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td style="text-align:right;"><?php echo $client->orders; ?></td>
                                                <td style="text-align:right;"><?php echo $client->tickets; ?></td>
                                                <td style="text-align:right;font-size:0.8em;<?php echo (date("d-m", strtotime($client->orderdate)) == date("d-m") ? "color:red;" : "");?>"><em><?php echo date("d-m", strtotime($client->orderdate)) ?></em></td>
                                            </tr>
                                            <?php $linenumber = $linenumber + 1; ?>
                                        <?php } ?>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="ticketmaster_salesscans_card wide">
                            <div class="ticketmaster_salesscans_card_heading">
                                <span style="margin: 5px;"><strong>Klanten zonder orders</strong></span>
                            </div>
                            <div class="ticketmaster_salesscans_card_content">
                                <div id="clientcount" style="cursor:pointer;">
                                    <span style="margin: 5px;"><?php echo count($this->clientswithoutorder); ?></span>
                                    <span class="userinfo-icon"><i id="clients-no-orders-arrow" class="bi bi-chevron-down"></i></span>
                                </div>
                                <div id="clientdetails" style="display:none;">
                                    <hr/>
                                    <table class="table-striped" style="width:100%;max-width:300px;margin-left:auto;margin-right:auto;">
                                        <?php $linenumber = 1; ?>
                                        <?php foreach($this->clientswithoutorder as $client) { ?>
                                            <tr>
                                                <td style="text-align:right;padding-right:10px;"><?php echo $linenumber; ?>.</td>
                                                <td><?php echo htmlspecialchars($client->firstname, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                            <?php $linenumber = $linenumber + 1; ?>
                                        <?php } ?>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="ticketmaster_salesscans_card">
                            <div class="ticketmaster_salesscans_card_heading">
                                <span style="margin: 5px;"><strong>Transactiekosten</strong></span>
                            </div>
                            <div class="ticketmaster_salesscans_card_content">
                                <div style="margin: 5px;">
                                    <table style="width:99%;max-width:175px;margin-left:auto;margin-right:auto;">
                                        <tr>
                                            <td style="text-align:left">Ontvangen</td>
                                            <td style="text-align:right"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $this->transcost_received, $this->config->valuta); ?></td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid;">
                                            <td style="text-align:left">Betaald</td>
                                            <td style="text-align:right"><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $this->transcost_paid, $this->config->valuta); ?></td>
                                        </tr>
                                        <tr>
                                            <td style="text-align:left"><strong>Opbrengst</strong></td>
                                            <td style="text-align:right"><strong><?php echo (new TicketstationFunctions)->showprice($this->config->priceformat, $this->transcost_profit, $this->config->valuta); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                    <?php } ?>



                <?php } ?>

            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="ticketmaster_salesscans_card_wrapper" >
            <div class="ticketmaster_salesscans_card_wrapper_heading">
                <h3 style="margin: 10px 0px 10px 10px;"><strong><?php echo $this->ticket->eventname; ?></strong></h3>
                <h4 style="margin: 10px 0px 10px 10px;"><strong>scans <?php echo ($this->scanstats=='ticket'?$this->ticket->ticketname:'totaal');?></strong></h4>
            </div>
            <div class="ticketmaster_salesscans_card_wrapper_content" style="text-align: center;">

                <?php if ($this->scannedtotal == 0) { ?>
                    <div style="margin-bottom: 25px;">
                        <h4><strong>Er zijn nog geen tickets gescand</strong></h4>
                    </div>
                <?php } else { ?>
                    <div class="ticketmaster_salesscans_card wide">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Totaal gescand</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <span style="margin: 5px;"><?php echo $this->scannedtotal; ?> <?php echo ($this->scannedtotal == 1 ? "ticket" : "tickets");?></span>
                        </div>
                    </div>

                    <?php if ($this->isadmin) { ?>

                        <div class="ticketmaster_salesscans_card wide">
                            <div class="ticketmaster_salesscans_card_heading">
                                <span style="margin: 5px;"><strong>Scans per scanner</strong></span>
                            </div>
                            <div class="ticketmaster_salesscans_card_content">
                                <table class="table-striped" style="width:100%;max-width:225px;margin-left:auto;margin-right:auto;">
                                    <?php foreach ($this->scannedperscanner as $scanner) { ?>
                                        <tr>
                                            <td>
                                                <?php echo $scanner->name; ?>:
                                            </td>
                                            <td style="text-align:right;">
                                                <?php echo $scanner->total_scanned; ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            </div>
                        </div>

                    <?php } ?>

                    <div class="ticketmaster_salesscans_card wide">
                        <div class="ticketmaster_salesscans_card_heading">
                            <span style="margin: 5px;"><strong>Scans per uur</strong></span>
                        </div>
                        <div class="ticketmaster_salesscans_card_content">
                            <table class="table-striped" style="width:100%;max-width:250px;margin-left:auto;margin-right:auto;">
                                <?php $datetoprocess = null; ?>
                                <?php foreach ($this->scannedperhour as $scandatehour) { ?>
                                    <?php if ($scandatehour->scandate != $datetoprocess) { ?>
                                        <tr>
                                        <td style="font-weight:bold;">
                                            <?php echo date("d-m-Y", strtotime($scandatehour->scandate)); ?>
                                        </td>
                                        <?php $datetoprocess = $scandatehour->scandate; ?>
                                    <?php } else { ?>
                                        <tr>
                                        <td></td>
                                    <?php } ?>
                                    <td>
                                        <?php echo (strlen($scandatehour->scanhour)==1?'0':'');?><?php echo $scandatehour->scanhour; ?>.00 - <?php echo (strlen($scandatehour->scanhour + 1)==1?'0':'');?><?php echo ($scandatehour->scanhour + 1); ?>.00:
                                    </td>
                                    <td style="text-align:right;">
                                        <?php echo $scandatehour->total_scanned; ?>
                                    </td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>