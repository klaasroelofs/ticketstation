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
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CPANEL_BROWSER_TITLE') . ' - ' . $app->get('sitename'));
$document->getWebAssetManager()->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

// Danger items (Mollie, tickets not sent) go in the alert at the top, the rest in the Needs attention card.
$urgent    = array_filter($this->attention, static fn($item) => $item->level === 'danger');
$attention = array_filter($this->attention, static fn($item) => $item->level !== 'danger');

// One badge per screen button, summing the attention items that link to that screen.
$levelRank = ['secondary' => 1, 'warning' => 2, 'danger' => 3];
$badges    = [];
foreach ($this->attention as $item) {
    $badge = $badges[$item->view] ?? (object) ['count' => 0, 'level' => 'secondary', 'titles' => []];
    $badge->count   += $item->count;
    $badge->titles[] = Text::plural($item->key, $item->count);
    if ($levelRank[$item->level] > $levelRank[$badge->level]) {
        $badge->level = $item->level;
    }
    $badges[$item->view] = $badge;
}

// The screen buttons: view, icon ('mollie' for the Mollie logo) and label, per group.
$tileGroups = [
    'COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT' => [
        ['boxoffice', 'fa-money-bill-alt', 'COM_TICKETSTATION_BOXOFFICE'],
        ['clients', 'fa-users', 'COM_TICKETSTATION_CUSTOMERS'],
        ['transactions', 'fa-credit-card', 'COM_TICKETSTATION_TRANSACTIONS'],
        ['invoices', 'fa-file-invoice', 'COM_TICKETSTATION_INVOICES'],
        ['coupons', 'fa-percent', 'COM_TICKETSTATION_COUPONS'],
        ['reservation', 'fa-calendar-plus', 'COM_TICKETSTATION_RESERVATION_CPANEL_BUTTON'],
    ],
    'COM_TICKETSTATION_CPANEL_HEADER_TICKETMANAGEMENT' => [
        ['tickets', 'fa-ticket-alt', 'COM_TICKETSTATION_TICKETS'],
        ['events', 'fa-calendar-alt', 'COM_TICKETSTATION_EVENTS'],
        ['venues', 'fa-solid fa-hotel', 'COM_TICKETSTATION_VENUES'],
        ['seatplans', 'fa-chair', 'COM_TICKETSTATION_SEATPLANS'],
    ],
    'COM_TICKETSTATION_CPANEL_HEADER_CONFIGURATION' => [
        ['scanners', 'fa-qrcode', 'COM_TICKETSTATION_TICKETSCANNING'],
        ['templates', 'fa-envelope', 'COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'],
        ['configuration', 'fa-cog', 'COM_TICKETSTATION_CONFIGURATION'],
        ['mollie', 'mollie', 'COM_TICKETSTATION_MOLLIE_CONFIG'],
        ['docs', 'fa-book', 'COM_TICKETSTATION_VIEW_DOCS_TITLE'],
    ],
];
if ($this->config->show_waitinglist == 1) {
    $tileGroups['COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT'][] = ['waitinglist', 'fa-hourglass-half', 'COM_TICKETSTATION_WAITINGLIST'];
}

// Only the screens this user may open (see AclGate).
foreach ($tileGroups as $groupKey => $tiles) {
    $tileGroups[$groupKey] = array_filter($tiles, static fn($tile) => AclGate::canOpen($tile[0]));
}
$tileGroups = array_filter($tileGroups);

$stats     = $this->stats;
$weekDiff  = $stats['week']->tickets - $stats['prev_week']->tickets;
$stepsDone = count(array_filter($this->setupSteps, static fn($step) => $step->done));
$maxDaily  = max(1, max(array_column($this->dailySales, 'tickets')));
$sum28     = array_sum(array_column($this->dailySales, 'tickets'));
?>

<div class="container ticketstation-cpanel">

    <?php // URGENT ITEMS ?>
    <?php if ($urgent) { ?>
        <div class="alert alert-danger" role="alert">
            <h2 class="alert-heading h5"><span class="fa fa-exclamation-triangle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CPANEL_URGENT_HEADER') ?></h2>
            <ul class="mb-0">
                <?php foreach ($urgent as $item) { ?>
                    <li><a href="<?= $item->link; ?>" class="alert-link"><?= Text::plural($item->key, $item->count); ?></a></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <?php // GETTING STARTED, until every step is done ?>
    <?php if ($stepsDone < count($this->setupSteps)) { ?>
        <div class="card mb-4 ticketstation-cpanel-start">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
                    <h2 class="h4 mb-1"><span class="fa fa-rocket text-primary me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CPANEL_START_TITLE') ?></h2>
                    <span class="small text-muted"><?= Text::sprintf('COM_TICKETSTATION_CPANEL_START_PROGRESS', $stepsDone, count($this->setupSteps)) ?></span>
                </div>
                <p class="text-muted"><?= Text::_('COM_TICKETSTATION_CPANEL_START_INTRO') ?></p>
                <ol class="ticketstation-cpanel-steps list-unstyled mb-0">
                    <?php foreach ($this->setupSteps as $number => $step) { ?>
                        <li class="<?= $step->done ? 'is-done' : ''; ?>">
                            <span class="ticketstation-cpanel-step-marker" aria-hidden="true">
                                <?php if ($step->done) { ?><span class="fa fa-check"></span><?php } else { echo $number + 1; } ?>
                            </span>
                            <?php if ($step->done) { ?>
                                <span><?= Text::_($step->key) ?></span>
                                <span class="visually-hidden"><?= Text::_('COM_TICKETSTATION_CPANEL_START_DONE') ?></span>
                            <?php } else { ?>
                                <a href="<?= $step->link; ?>"><?= Text::_($step->key) ?></a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            </div>
        </div>
    <?php } ?>

    <?php // KEY FIGURES ?>
    <h2 class="mb-0"><span class="fa fa-chart-line text-primary me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_TITLE') ?></h2>
    <p class="text-muted"><?= Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_SUBTITLE', HTMLHelper::_('date', 'now', 'W'), HTMLHelper::_('date', 'now', 'F Y')) ?></p>

    <div class="row">
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="ticketstation-cpanel-kpi-icon" aria-hidden="true"><span class="fa fa-ticket-alt"></span></span>
                    <div>
                        <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_SOLD_WEEK') ?></div>
                        <div class="fs-2 fw-bold lh-sm"><?= $stats['week']->tickets; ?></div>
                        <div class="small text-muted">
                            <?php if ($weekDiff != 0) { ?>
                                <span class="fw-semibold <?= $weekDiff > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <span class="fa <?= $weekDiff > 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>" aria-hidden="true"></span>
                                    <?= sprintf('%+d', $weekDiff); ?>
                                </span> &middot;
                            <?php } ?>
                            <?= Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_PREV_WEEK', $stats['prev_week']->tickets); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="ticketstation-cpanel-kpi-icon" aria-hidden="true"><span class="fa fa-coins"></span></span>
                    <div>
                        <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_WEEK') ?></div>
                        <div class="fs-2 fw-bold lh-sm"><?= Price::_($stats['week']->revenue); ?></div>
                        <div class="small text-muted"><?= $stats['week']->fees > 0
                            ? Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_ORDERS_EXCL_FEES', $stats['week']->orders, Price::_($stats['week']->fees))
                            : Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_ORDERS', $stats['week']->orders); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="ticketstation-cpanel-kpi-icon" aria-hidden="true"><span class="fa fa-wallet"></span></span>
                    <div>
                        <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_MONTH') ?></div>
                        <div class="fs-2 fw-bold lh-sm"><?= Price::_($stats['month']->revenue); ?></div>
                        <div class="small text-muted"><?= $stats['month']->fees > 0
                            ? Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_TICKETS_EXCL_FEES', $stats['month']->tickets, Price::_($stats['month']->fees))
                            : Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_TICKETS', $stats['month']->tickets); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="ticketstation-cpanel-kpi-icon" aria-hidden="true"><span class="fa fa-store"></span></span>
                    <div>
                        <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_ON_SALE') ?></div>
                        <div class="d-flex gap-4">
                            <div>
                                <div class="fs-2 fw-bold lh-sm"><?= $stats['on_sale_events']; ?></div>
                                <div class="small text-muted"><?= Text::plural('COM_TICKETSTATION_CPANEL_STATS_EVENTS_LABEL', $stats['on_sale_events']); ?></div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold lh-sm"><?= $stats['on_sale_tickets']; ?></div>
                                <div class="small text-muted"><?= Text::plural('COM_TICKETSTATION_CPANEL_STATS_TICKETS_LABEL', $stats['on_sale_tickets']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8">
            <?php // SALES PER DAY ?>
            <div class="card mb-3">
                <h3 class="card-header d-flex justify-content-between align-items-baseline">
                    <span><?= Text::_('COM_TICKETSTATION_CPANEL_CHART_HEADER') ?></span>
                    <span class="small fw-normal text-muted"><?= Text::plural('COM_TICKETSTATION_CPANEL_CHART_TOTAL', $sum28) ?></span>
                </h3>
                <div class="card-body">
                    <?php $barWidth = 10; $chartHeight = 60; ?>
                    <svg class="ticketstation-cpanel-chart" viewBox="0 0 <?= count($this->dailySales) * $barWidth; ?> <?= $chartHeight; ?>" preserveAspectRatio="none" role="img" aria-label="<?= $this->escape(Text::plural('COM_TICKETSTATION_CPANEL_CHART_ARIA', $sum28)) ?>">
                        <?php foreach ($this->dailySales as $index => $day) {
                            $height = $day->tickets > 0 ? max(2, round($day->tickets / $maxDaily * $chartHeight, 1)) : 1;
                            $label  = HTMLHelper::_('date', $day->date, Text::_('DATE_FORMAT_LC1'), null);
                            ?>
                            <rect class="<?= $day->tickets > 0 ? 'is-sale' : 'is-empty'; ?>" x="<?= $index * $barWidth + 1; ?>" y="<?= $chartHeight - $height; ?>" width="<?= $barWidth - 2; ?>" height="<?= $height; ?>">
                                <title><?= $this->escape($label . ': ' . Text::plural('COM_TICKETSTATION_CPANEL_CHART_BAR', $day->tickets) . ' · ' . html_entity_decode(strip_tags(Price::_($day->revenue)), ENT_QUOTES, 'UTF-8')) ?></title>
                            </rect>
                        <?php } ?>
                    </svg>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span><?= HTMLHelper::_('date', $this->dailySales[0]->date, 'j M', null) ?></span>
                        <span><?= Text::_('COM_TICKETSTATION_CPANEL_CHART_TODAY') ?></span>
                    </div>
                </div>
            </div>

            <?php // AVAILABILITY ?>
            <div class="card mb-3">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_HEADER') ?>
                </h3>
                <div class="card-body">
                    <?php if (empty($this->availability)) { ?>
                        <p class="text-muted mb-0"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_NONE') ?></p>
                    <?php } else { ?>
                        <table class="table table-sm align-middle mb-2">
                            <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_TICKET') ?></th>
                                <th scope="col" class="d-none d-md-table-cell"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_DATE') ?></th>
                                <th scope="col" class="w-25"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_SOLD') ?></th>
                                <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_LEFT') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this->availability as $row) {
                                $percentageLeft = 100 - $row->percentage_sold;
                                if ($row->available <= 0) {
                                    $barClass  = 'bg-danger';
                                    $leftClass = 'text-danger';
                                } elseif ($percentageLeft < 11) {
                                    $barClass  = 'bg-warning';
                                    $leftClass = 'text-warning';
                                } else {
                                    $barClass  = 'bg-primary';
                                    $leftClass = '';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a href="index.php?option=com_ticketstation&controller=tickets&task=edit&cid=<?= (int) $row->ticketid; ?>"><?= $row->eventname; ?> - <?= $row->ticketname; ?></a>
                                        <?php if ($row->show_seatplans == 1) { ?>
                                            <span class="fa fa-chair text-muted small" title="<?= Text::_('COM_TICKETSTATION_SEATPLANS') ?>"></span>
                                        <?php } ?>
                                    </td>
                                    <td class="small d-none d-md-table-cell text-nowrap"><?= date($this->config->dateformat . ' ' . $this->config->time_format, strtotime($row->startdate)); ?></td>
                                    <td>
                                        <div class="progress" role="progressbar" aria-valuenow="<?= $row->percentage_sold; ?>" aria-valuemin="0" aria-valuemax="100"
                                             title="<?= $row->sold; ?> / <?= $row->total; ?>">
                                            <div class="progress-bar <?= $barClass; ?>" style="width: <?= $row->percentage_sold; ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?= $row->sold; ?> / <?= $row->total; ?></span>
                                    </td>
                                    <td class="text-end text-nowrap <?= $leftClass; ?>">
                                        <?= $row->available <= 0 ? Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_SOLD_OUT') : $row->available; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <a href="index.php?option=com_ticketstation&view=tickets" class="small"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_ALL') ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php // NEEDS ATTENTION ?>
        <div class="col-12 col-lg-4">
            <div class="card mb-3">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_ATTENTION_HEADER') ?>
                </h3>
                <div class="card-body">
                    <?php if (empty($attention)) { ?>
                        <p class="text-muted mb-0">
                            <span class="fa fa-check-circle text-success" aria-hidden="true"></span>
                            <?= Text::_($urgent ? 'COM_TICKETSTATION_CPANEL_ATTENTION_NONE_ELSE' : 'COM_TICKETSTATION_CPANEL_ATTENTION_NONE') ?>
                        </p>
                    <?php } else { ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($attention as $item) { ?>
                                <li class="mb-2 d-flex">
                                    <span class="fa fa-fw <?= $item->icon; ?> text-<?= $item->level; ?> me-2 mt-1" aria-hidden="true"></span>
                                    <a href="<?= $item->link; ?>"><?= Text::plural($item->key, $item->count); ?></a>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php // SCREEN BUTTONS ?>
    <h2 class="visually-hidden"><?= Text::_('COM_TICKETSTATION_CPANEL_MENU') ?></h2>
    <?php foreach ($tileGroups as $groupKey => $tiles) { ?>
        <div class="card mb-3">
            <h3 class="card-header">
                <?= Text::_($groupKey) ?>
            </h3>
            <div class="card-body">
                <div class="ticketstation-cpanel-tiles">
                    <?php foreach ($tiles as [$view, $icon, $labelKey]) {
                        $badge = $badges[$view] ?? null;
                        // Online payments switched off: the Mollie button looks faded and says so.
                        $off   = $view === 'mollie' && ($this->mollie->enabled ?? '1') != '1';
                        ?>
                        <a class="ticketstation-cpanel-tile<?= $off ? ' ticketstation-cpanel-tile--off' : ''; ?>" href="index.php?option=com_ticketstation&view=<?= $view; ?>"<?= $off ? ' title="' . $this->escape(Text::_('COM_TICKETSTATION_CPANEL_MOLLIE_OFF_TITLE')) . '"' : ''; ?>>
                            <span class="ticketstation-cpanel-tile-icon" aria-hidden="true">
                                <?php if ($icon === 'mollie') { ?>
                                    <?php // Black monogram on the light theme, white one on the dark theme ?>
                                    <img class="ticketstation-theme-light" src="components/com_ticketstation/assets/images/MollieMonogram23-Circle.png" alt="">
                                    <img class="ticketstation-theme-dark" src="components/com_ticketstation/assets/images/MollieMonogram23-CircleWhite.png" alt="">
                                <?php } else { ?>
                                    <?php // "fa fa-hotel" would hit Joomla's FA4 compat rule (a bed); fa-solid alone gives the building ?>
                                    <span class="<?= str_starts_with($icon, 'fa-solid ') ? '' : 'fa '; ?><?= $icon; ?>"></span>
                                <?php } ?>
                            </span>
                            <span class="ticketstation-cpanel-tile-label"><?= Text::_($labelKey) ?></span>
                            <?php if ($off) { ?>
                                <span class="ticketstation-cpanel-tile-state"><?= Text::_('COM_TICKETSTATION_CPANEL_MOLLIE_OFF') ?></span>
                            <?php } ?>
                            <?php if ($badge) { ?>
                                <span class="ticketstation-cpanel-tile-badge badge rounded-pill text-bg-<?= $badge->level; ?>" title="<?= $this->escape(implode("\n", $badge->titles)); ?>">
                                    <?= $badge->count; ?>
                                    <span class="visually-hidden"><?= $this->escape(implode('; ', $badge->titles)); ?></span>
                                </span>
                            <?php } ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php // FOOTER: version, review and support, copyright ?>
    <?php // Colours follow the logo: "Ticket" and "for Joomla!" in brand blue, "station" in dark navy ?>
    <?php $productName = '<b class="text-nowrap"><span class="ticketstation-brand-blue">Ticket</span><span class="ticketstation-brand-navy">station</span> <span class="ticketstation-brand-blue">for Joomla!</span></b>'; ?>
    <footer class="card mt-4 small">
        <div class="card-body">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-md-5 col-xl-3 text-center">
                    <?php // White box so the dark-blue wordmark stays readable in the dark admin theme too ?>
                    <div class="p-2 rounded border" style="background: #fff;">
                        <img src="components/com_ticketstation/assets/images/logo_ticketstation_for_joomla.png" alt="Ticketstation for Joomla!" class="img-fluid" style="max-height: 48px;">
                    </div>
                    <div class="mt-2">
                        <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_VERSION') ?> <strong><?= $this->data['version']; ?></strong>
                        &middot; <?= $this->data['creationDate']; ?>
                    </div>
                    <?php if ($this->update) { ?>
                        <?php $updateLabel = Text::sprintf('COM_TICKETSTATION_VIEW_CPANEL_UPDATE_AVAILABLE', $this->escape($this->update)); ?>
                        <?php if ($app->getIdentity()->authorise('core.manage', 'com_installer')) { ?>
                            <a href="index.php?option=com_installer&view=update" class="badge text-bg-warning mt-1">
                                <span class="fa fa-arrow-circle-up" aria-hidden="true"></span> <?= $updateLabel ?>
                            </a>
                        <?php } else { ?>
                            <span class="badge text-bg-warning mt-1">
                                <span class="fa fa-arrow-circle-up" aria-hidden="true"></span> <?= $updateLabel ?>
                            </span>
                        <?php } ?>
                    <?php } ?>
                </div>
                <div class="col-12 col-md-7 col-xl-5 text-muted">
                    <p class="mb-2"><?= Text::sprintf('COM_TICKETSTATION_CPANEL_FOOTER_ABOUT', $productName); ?></p>
                    <p class="mb-0"><?= Text::_('COM_TICKETSTATION_CPANEL_FOOTER_SUPPORT'); ?></p>
                </div>
                <div class="col-12 col-xl-4 text-center">
                    <div class="mb-1"><?= Text::_('COM_TICKETSTATION_ENJOYING') ?></div>
                    <?php // A review costs nothing and helps others find the extension, so it comes before the donation ?>
                    <p class="text-muted mb-2"><?= Text::_('COM_TICKETSTATION_CPANEL_REVIEW_TEXT') ?></p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="https://extensions.joomla.org/extension/calendars-a-events/ticketstation/"
                           class="btn btn-sm btn-primary" target="blank" rel="noopener noreferrer">
                            <span class="fa fa-star" aria-hidden="true"></span>
                            <?= Text::_('COM_TICKETSTATION_CPANEL_REVIEW_BUTTON') ?>
                        </a>
                        <a href="https://ko-fi.com/klaasroelofs"
                           class="btn btn-sm btn-outline-success" target="blank" rel="noopener noreferrer">
                            <span class="fa fa-mug-hot" aria-hidden="true"></span>
                            <?= Text::_('COM_TICKETSTATION_DONATE') ?>
                        </a>
                    </div>
                </div>
            </div>
            <p class="text-muted border-top pt-3 mt-3 mb-0">
                Copyright 2022-<?= date('Y') ?> <a href="https://github.com/klaasroelofs" target="blank" rel="noopener"><?php echo $this->data['author']; ?></a> Overloon. <?= Text::_('COM_TICKETSTATION_CPANEL_FOOTER_RIGHTS'); ?>
                <?= Text::sprintf('COM_TICKETSTATION_CPANEL_FOOTER_LICENSE', $productName, '<a href="https://www.gnu.org/licenses/gpl-3.0.html" target="blank" rel="noopener noreferrer">GNU General Public License</a>'); ?>
            </p>
        </div>
    </footer>
</div>
