<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS') . ' - ' . $app->get('sitename'));

$wa = $document->getWebAssetManager();
$wa->useScript('jquery');
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

if (isset($this->data[0]->coupon_type)) {
    if ($this->data[0]->coupon_type == 1) {
        $ticket_amount = count($this->data);
        $discount = (($this->data[0]->ticketprice / 100) * $this->data[0]->coupon_discount) * $ticket_amount;
        $discount_text = '(' . $this->data[0]->coupon_discount . '%)';
    } else {
        $ticket_amount = count($this->data);
        $discount = $this->data[0]->coupon_discount;
        $discount_text = '';
    }
}


$history = $this->history ?? [];

$history_icons = [
    'order_created'          => ['fa-plus-circle', 'primary'],
    'transaction_created'    => ['fa-credit-card', 'secondary'],
    'payment_initiated'      => ['fa-credit-card', 'info'],
    'order_paid'             => ['fa-check-circle', 'success'],
    'order_status_pending'   => ['fa-clock', 'warning'],
    'order_status_refunded'  => ['fa-reply', 'info'],
    'payment_failed'         => ['fa-times-circle', 'danger'],
    'payment_cancelled'      => ['fa-ban', 'secondary'],
    'payment_expired'        => ['fa-hourglass-end', 'secondary'],
    'tickets_generated'      => ['fa-ticket-alt', 'secondary'],
    'tickets_sent'           => ['fa-paper-plane', 'info'],
    'ticket_copy_sent'       => ['fa-paper-plane', 'info'],
    'confirmation_sent'      => ['fa-envelope', 'info'],
    'payment_reminder_sent'  => ['fa-bell', 'warning'],
    'ticket_scanned'         => ['fa-qrcode', 'success'],
    'ticket_scan_reset'      => ['fa-qrcode', 'secondary'],
    'ticket_blacklisted'     => ['fa-ban', 'danger'],
    'ticket_unblocked'       => ['fa-check', 'success'],
    'ticket_removed'         => ['fa-trash', 'danger'],
    'order_removed'          => ['fa-trash', 'danger'],
    'order_removed_auto'     => ['fa-broom', 'secondary'],
    'order_published'        => ['fa-eye', 'success'],
    'order_unpublished'      => ['fa-eye-slash', 'secondary'],
    'remark_updated'         => ['fa-comment', 'secondary'],
    'remark_removed'         => ['fa-comment-slash', 'secondary'],
    'invoice_created'        => ['fa-euro-sign', 'secondary'],
    'invoice_sent'           => ['fa-euro-sign', 'info'],
];

?>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link ts-tab-link active" href="#ts-tab-overview" id="ts-tab-overview-lbl" role="tab" aria-controls="ts-tab-overview" aria-selected="true">
            <?= Text::_('COM_TICKETSTATION_OVERVIEW') ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link ts-tab-link" href="#ts-tab-tickets" id="ts-tab-tickets-lbl" role="tab" aria-controls="ts-tab-tickets" aria-selected="false">
            <?= Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER') ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link ts-tab-link" href="#ts-tab-history" id="ts-tab-history-lbl" role="tab" aria-controls="ts-tab-history" aria-selected="false">
            <?= Text::_('COM_TICKETSTATION_HISTORY') ?>
            <?php if (count($history)) { ?>
                <span class="badge bg-secondary rounded-pill"><?= count($history); ?></span>
            <?php } ?>
        </a>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane active" id="ts-tab-overview" role="tabpanel" aria-labelledby="ts-tab-overview-lbl">

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_ORDER_INFORMATION') ?>
                </h3>
                <table class="table">
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_ORDERCODE') ?></td>
                        <td>
                            <?= $this->items->ordercode; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_ORDERDATE') ?></td>
                        <td><?= Date::_($this->items->orderdate, 'd-m-Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></td>
                        <td>
                            <?php if ($this->data[0]->transaction_amount > 0) { ?>
                                <a href="index.php?option=com_ticketstation&controller=transactions&task=edit&cid=<?= $this->data[0]->pid; ?>"><?= $this->config->valuta; ?> <?= number_format($this->orderprice, 2, ',', ''); ?></a>
                            <?php } else { ?>
                                <?= $this->config->valuta; ?> <?= number_format($this->orderprice, 2, ',', ''); ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS') ?></td>
                        <td>
                            <?php if ($this->items->paid == 1){ ?>
                                <span class="badge bg-success"><?= Text::_( 'COM_TICKETSTATION_PAID' ); ?></span>
                            <?php } elseif ($this->data[0]->paid == 2) { ?>
                                <span class="badge bg-info" ><?= Text::_( 'COM_TICKETSTATION_REFUNDED' ); ?></span>
                            <?php } elseif($this->data[0]->paid == 3) { ?>
                                <span class="badge bg-warning"><?= Text::_( 'COM_TICKETSTATION_PENDING' ); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-danger" ><?= Text::_( 'COM_TICKETSTATION_UNPAID_OVERVIEW' ); ?></span>
                            <?php } ?>
                        </td>
                    </tr>

                    <?php if(isset($this->data[0]->coupon_type)) { ?>
                        <tr>
                            <td><?= Text::_( 'COM_TICKETSTATION_COUPON_CODE' ); ?></td>
                            <td><?php if ($this->data[0]->coupon != '') { ?>
                                    <span class="badge bg-warning" style="padding-left: 10px; padding-right: 10px;"><?= $this->data[0]->coupon; ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-danger" style="padding-left: 10px; padding-right: 10px;"><?= Text::_( 'COM_TICKETSTATION_NO' ); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php if ($this->data[0]->coupon != '') { ?>
                            <tr>
                                <td><?= Text::_( 'COM_TICKETSTATION_DISCOUNT' ); ?> <?= $discount_text; ?></td>
                                <td><div> <?= $this->config->valuta; ?> <?= number_format($discount, 2, ',', ''); ?></div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>

                </table>
            </div>
            <div class="col-lg-6">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CLIENT_INFORMATION') ?>
                </h3>
                <table class="table" style="overflow:hidden;table-layout:fixed;">
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_NAME') ?></td>
                        <td><a href="index.php?option=com_ticketstation&controller=clients&task=edit&cid=<?= $this->items->clientid; ?>"><?= $this->items->firstname; ?> <?= $this->items->name; ?></a></td>
                    </tr>
                    <tr>
                        <td><?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?></td>
                        <td><?= $this->items->phonenumber; ?></td>
                    </tr>
                    <tr>
                        <td><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></td>
                        <td><small><a href="mailto:<?= $this->items->emailaddress; ?>"><?= $this->items->emailaddress; ?></a></small></td>
                    </tr>
                    <tr>
                        <form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm1" enctype="multipart/form-data">
                            <td><?= Text::_('COM_TICKETSTATION_ORDERREFERENCE') ?></td>
                            <td>
                                <input class="form-control" type="text" name="newremark" id="newremark" size="40" style="width: 95%;margin-bottom:0px;" maxlength="35" value="<?= isset($this->remark->remarks) ? $this->remark->remarks : ''; ?>" />
                            </td>
                    </tr>
                </table>
                <div style="float:right;margin-right: 30px;">
                    <input name="submit" type="submit" class="btn btn-primary" value="<?= Text::_('COM_TICKETSTATION_UPDATE_ORDERREFERENCE') ?>" />

                    <input name = "cid" type="hidden" style="text-align:center" value="<?= $this->items->ordercode; ?>" size="10" READONLY  class="input-medium" />
                    <input name = "option" type="hidden" value="com_ticketstation" />
                    <input name = "task" type="hidden" value="updateinsertremark" />
                    <input name = "controller" type="hidden" value="boxoffice"/>
                    <?= HTMLHelper::_( 'form.token' ); ?>
                    </form>
                </div>
                <div style="float:right;margin-right: 10px;">
                    <form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm2" enctype="multipart/form-data">

                        <input name="submit" type="submit" class="btn btn-primary" value="<?= Text::_('COM_TICKETSTATION_REMOVE_ORDERREFERENCE') ?>" />

                        <input name ="cid" type="hidden" style="text-align:center" value="<?= $this->items->ordercode; ?>" size="10" READONLY  class="input-medium" />
                        <input name = "option" type="hidden" value="com_ticketstation" />
                        <input name = "task" type="hidden" value="deleteremark" />
                        <input name = "controller" type="hidden" value="boxoffice"/>
                        <?= HTMLHelper::_( 'form.token' ); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
<div class="tab-pane" id="ts-tab-tickets" role="tabpanel" aria-labelledby="ts-tab-tickets-lbl">

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER') ?>
                </h3>
                <form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
                    <div class="subhead mb-3 shadow-sm" style="position: relative; z-index: 100; box-shadow: none !important; background-image: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <nav aria-label="<?= Text::_('JTOOLBAR'); ?>">
                                    <div class="btn-toolbar d-flex" role="toolbar" id="toolbar">
                                        <joomla-toolbar-button id="toolbar-eye-close" task="resetscanstate">
                                            <button class="button-eye-close btn btn-primary" type="button">
                                                <span class="icon-eye-close" aria-hidden="true"></span>
                                                <?= Text::_('COM_TICKETSTATION_BOXOFFICE_MARK_NOT_SCANNED'); ?></button>
                                        </joomla-toolbar-button>

                                        <joomla-toolbar-button id="toolbar-eye-open" task="markasscanned">
                                            <button class="button-eye-open btn btn-primary" type="button">
                                                <span class="icon-eye-open" aria-hidden="true"></span>
                                                <?= Text::_('COM_TICKETSTATION_BOXOFFICE_MARK_SCANNED'); ?></button>
                                        </joomla-toolbar-button>

                                        <joomla-toolbar-button id="toolbar-lock" task="blocked">
                                            <button class="button-lock btn btn-primary" type="button">
                                                <span class="icon-lock" aria-hidden="true"></span>
                                                <?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLOCK'); ?></button>
                                        </joomla-toolbar-button>

                                        <joomla-toolbar-button id="toolbar-unlock" task="unlock">
                                            <button class="button-unlock btn btn-primary" type="button">
                                                <span class="icon-unlock" aria-hidden="true"></span>
                                                <?= Text::_('COM_TICKETSTATION_BOXOFFICE_UNBLOCK'); ?></button>
                                        </joomla-toolbar-button>

                                        <joomla-toolbar-button id="toolbar-trash" task="removeSingleOrder">
                                            <button class="button-trash btn btn-primary" type="button">
                                                <span class="icon-trash" aria-hidden="true"></span>
                                                <?= Text::_('COM_TICKETSTATION_BOXOFFICE_REMOVE_TICKET'); ?></button>
                                        </joomla-toolbar-button>
                                    </div>
                                </nav>

                            </div>
                        </div>
                    </div>


                    <table class="table">
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                                </td>
                                <th scope="col" class="w-3"><?= Text::_( 'COM_TICKETSTATION_TICKET_ID' ); ?></th>
                                <th scope="col" class="w-10"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME' ); ?></th>
                                <th scope="col" class="w-3 text-center"><?= Text::_( 'COM_TICKETSTATION_SCANNED' ); ?></th>
                                <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_BLACKLIST' ); ?></th>
                                <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_QRCODE' ); ?></th>
                            </tr>
                        </thead>
                        <?php

                        for ($i = 0, $n = count($this->data); $i < $n; $i++ ) {

                            ## Give give $row the this->item[$i]
                            $row        = $this->data[$i];
                            $published 	= HTMLHelper::_('grid.published', $row, $i );
                            $checked    = HTMLHelper::_('grid.id', $i, $row->orderid );

                            if ($row->seat_sector == 0) {
                                $title = $row->eventname . ' - ' . $row->ticketname;
                            } else {
                                $title = $row->eventname . ' - ' . $row->ticketname  . ' - ' . Text::_('COM_TICKETSTATION_SEAT') . ': ' . $row->row_name . $row->seatid;
                            }
                            
                            ?>
                            <tr>
                                <td align="center"><?= $checked; ?></td>
                                <td><?= $row->orderid; ?></td>
                                <td><?= $title; ?></td>
                                <td class="w-3 text-center">
                                    <?php if ($row->scanned == 0) { ?>
                                        <span class="badge bg-success"><?= Text::_( 'COM_TICKETSTATION_NO' ); ?></span>
                                    <?php } else { ?>
                                        <span title="<?= $row->scanner_name;?>"  class="badge bg-danger"><?= date('H:i', strtotime($row->scandate));?></span>
                                    <?php } ?>
                                </td>
                                <td class="w-3 d-none d-lg-table-cell text-center">
                                    <?php if ($row->blacklisted == 1) { ?>
                                        <span class="badge bg-danger"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                    <?php } else { ?>
                                        <span class="badge bg-success"><?= Text::_( 'COM_TICKETSTATION_NO' ); ?></span>
                                    <?php } ?>
                                </td>
                                <td class="w-3 d-none d-lg-table-cell text-center">
                                    <?php if ($row->barcode != '0') { ?>
                                        <div class="qrcode_number">
                                            <span id="qrcode_<?= $row->orderid; ?>" style="margin-bottom:5px;" class="badge bg-secondary"><?= $row->barcode; ?></span>
                                        </div>
                                        <?php if ($row->scanned == 0) { ?>
                                            <div id="qrcode_<?= $row->orderid; ?>_image" class="qrcode_image" style="display: none; margin: 0 auto;">
                                                <img id="qrcode_<?= $row->orderid; ?>_img" class="qrcode_img scale_down" src="/administrator/components/com_ticketstation/tickets/qrcodes/<?= $row->barcode; ?>.png" style="max-width: 100px;">
                                            </div>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                            </tr>

                        <?php } ?>


                    </table>

                    <input name = "ordercode" type="hidden" style="text-align:center" value="<?= $this->items->ordercode; ?>" size="10" READONLY  class="input-medium" />
                    <input name = "option" type="hidden" value="com_ticketstation" />
                    <input name = "task" type="hidden" value="" />
                    <input name = "boxchecked" type="hidden" value="0"/>
                    <input name = "controller" type="hidden" value="boxoffice"/>
                    <?= HTMLHelper::_( 'form.token' ); ?>

                </form>



            </div>
        </div>
    </div>
</div>

</div>
<div class="tab-pane" id="ts-tab-history" role="tabpanel" aria-labelledby="ts-tab-history-lbl">

<div class="card">
    <div class="card-body">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_HISTORY') ?>
        </h3>

        <?php if (empty($history)) { ?>
            <p class="text-muted"><?= Text::_('COM_TICKETSTATION_HISTORY_EMPTY') ?></p>
        <?php } else { ?>

            <div class="ts-history">
                <?php
                $current_day = null;

                foreach (array_reverse($history) as $entry) {

                    // $entry->created is stored in UTC (History::log()); convert to the site/user timezone for display.
                    $day = Date::_($entry->created, 'l d F Y', true);

                    if ($day !== $current_day) {
                        $current_day = $day;
                        ?>
                        <div class="ts-history-day"><?= $day; ?></div>
                        <?php
                    }

                    [$icon, $color] = $history_icons[$entry->event_type] ?? ['fa-circle', 'secondary'];
                    ?>
                    <div class="ts-history-row">
                        <div class="ts-history-time"><?= Date::_($entry->created, 'H:i'); ?></div>
                        <div class="ts-history-icon text-<?= $color; ?>"><span class="fa <?= $icon; ?>" aria-hidden="true"></span></div>
                        <div class="ts-history-message">
                            <?= htmlspecialchars($entry->message); ?>
                            <?php if ($entry->actor) { ?>
                                <span class="ts-history-actor"><?= Text::_('COM_TICKETSTATION_HISTORY_BY') ?> <?= htmlspecialchars($entry->actor); ?></span>
                            <?php } ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

        <?php } ?>

    </div>
</div>

</div>
</div>

<style>

    .ts-history-day {
        font-weight: 600;
        margin: 1.25rem 0 0.5rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid var(--border-color, #dee2e6);
    }

    .ts-history-day:first-child {
        margin-top: 0;
    }

    .ts-history-row {
        display: flex;
        align-items: baseline;
        gap: 0.75rem;
        padding: 0.35rem 0;
        border-bottom: 1px solid rgba(0,0,0,.05);
    }

    .ts-history-time {
        flex: 0 0 3.5rem;
        color: #6c757d;
        font-variant-numeric: tabular-nums;
    }

    .ts-history-icon {
        flex: 0 0 1.25rem;
        text-align: center;
    }

    .ts-history-message {
        flex: 1 1 auto;
    }

    .ts-history-actor {
        color: #6c757d;
        font-size: 0.85em;
        margin-left: 0.5rem;
    }

</style>

<script type="text/javascript">

    jQuery(window).ready(function() {

        jQuery('.ts-tab-link').click(function(e) {

            e.preventDefault();

            var target = jQuery(this).attr('href');

            jQuery('.ts-tab-link').removeClass('active').attr('aria-selected', 'false');
            jQuery(this).addClass('active').attr('aria-selected', 'true');

            jQuery('.tab-pane').removeClass('active');
            jQuery(target).addClass('active');

        });

        jQuery('.qrcode_number').click(function() {

            jQuery('.qrcode_image').not(this).slideUp();

            jQuery(this).next('.qrcode_image').stop().slideToggle();

        });

        jQuery('.qrcode_img').click(function() {

            jQuery('.qrcode_img').not(this).toggleClass("scale_up scale_down");

            jQuery(this).toggleClass("scale_up scale_down");

        });

    });

</script>


