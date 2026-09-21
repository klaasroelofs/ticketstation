<?php

use Joomla\CMS\Factory;
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

$ghost   = $this->ghost;
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

$reason_key = $ghost->reason === 'unfinished'
    ? 'COM_TICKETSTATION_ORDER_REMOVED_AUTO_REASON_UNFINISHED'
    : 'COM_TICKETSTATION_ORDER_REMOVED_AUTO_REASON_PENDING';

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

<div class="alert alert-secondary">
    <span class="fa fa-broom" aria-hidden="true"></span>
    <?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_NOTICE') ?>
    <br/>
    <small>
        <?= Text::_($reason_key) ?>
        &middot;
        <?= Date::_($ghost->removed_at, 'd-m-Y H:i') ?>
    </small>
</div>

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
                        <td><?= $ghost->ordercode; ?></td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_ORDERDATE') ?></td>
                        <td><?= Date::_($ghost->orderdate, 'd-m-Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME') ?></td>
                        <td><?= htmlspecialchars($ghost->eventname ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></td>
                        <td><?= $this->config->valuta; ?> <?= number_format($ghost->total, 2, ',', ''); ?></td>
                    </tr>
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS') ?></td>
                        <td>
                            <?php if ($ghost->paid == 1) { ?>
                                <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_PAID'); ?></span>
                            <?php } elseif ($ghost->paid == 2) { ?>
                                <span class="badge bg-info"><?= Text::_('COM_TICKETSTATION_REFUNDED'); ?></span>
                            <?php } elseif ($ghost->paid == 3) { ?>
                                <span class="badge bg-warning"><?= Text::_('COM_TICKETSTATION_PENDING'); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-lg-6">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CLIENT_INFORMATION') ?>
                </h3>
                <table class="table" style="overflow:hidden;table-layout:fixed;">
                    <tr>
                        <td style="width:50%"><?= Text::_('COM_TICKETSTATION_NAME') ?></td>
                        <td>
                            <?php if ($ghost->client) { ?>
                                <a href="index.php?option=com_ticketstation&controller=clients&task=edit&cid=<?= $ghost->client->clientid; ?>"><?= htmlspecialchars($ghost->client->firstname . ' ' . $ghost->client->name); ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></td>
                        <td>
                            <?php if ($ghost->client) { ?>
                                <small><a href="mailto:<?= $ghost->client->emailaddress; ?>"><?= htmlspecialchars($ghost->client->emailaddress); ?></a></small>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<div class="tab-pane" id="ts-tab-tickets" role="tabpanel" aria-labelledby="ts-tab-tickets-lbl">

<div class="card">
    <div class="card-body">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER') ?>
        </h3>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_TICKET_ID') ?></th>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME') ?></th>
                    <th scope="col" class="text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></th>
                    <th scope="col" class="text-center"><?= Text::_('COM_TICKETSTATION_SCANNED') ?></th>
                    <th scope="col" class="text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLACKLIST') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ghost->lines as $line) { ?>
                    <tr>
                        <td><?= $line->orderid; ?></td>
                        <td>
                            <?= htmlspecialchars($line->ticketname ?? ''); ?>
                            <?php if (!empty($line->seatid)) { ?>
                                - <?= Text::_('COM_TICKETSTATION_SEAT') ?>: <?= htmlspecialchars($line->row_name . $line->seatid); ?>
                            <?php } ?>
                        </td>
                        <td class="text-center"><?= $this->config->valuta; ?> <?= number_format((float) $line->price, 2, ',', ''); ?></td>
                        <td class="text-center">
                            <?php if ($line->scanned == 1) { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_NO'); ?></span>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <?php if ($line->blacklisted == 1) { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
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
                    $day = Date::_($entry->created, 'l d F Y');

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

<input name="option" type="hidden" value="com_ticketstation" />
<input name="task" type="hidden" value="" />
<input name="boxchecked" type="hidden" value="0" />

</form>

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

    });

</script>
