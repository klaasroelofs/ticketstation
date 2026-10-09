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
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

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

// The screen buttons: view, icon and label, per group.
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
        ['payments', 'fa-credit-card', 'COM_TICKETSTATION_PAYMENTS_CONFIG'],
        ['docs', 'fa-book', 'COM_TICKETSTATION_VIEW_DOCS_TITLE'],
    ],
];
if (WaitingList::anywhere()) {
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

    <?php // TEST MODE: one switch for the whole shop, the payment provider follows. Shown on the line of the key figures heading below; the explanation is the tooltip. ?>
    <?php
    $modeTip = $this->testMode
        ? trim(Text::_('COM_TICKETSTATION_CPANEL_TESTMODE_ON_TITLE') . '. ' . Text::_('COM_TICKETSTATION_CPANEL_TESTMODE_ON_TEXT')
            . ($this->providerTitle !== '' && !$this->testBlocked ? ' ' . Text::sprintf('COM_TICKETSTATION_CPANEL_TESTMODE_ON_PROVIDER', $this->providerTitle) : '')
            . ($this->testBlocked ? ' ' . Text::sprintf('COM_TICKETSTATION_CPANEL_TESTMODE_NO_TEST_ENVIRONMENT', $this->providerTitle) : ''))
        : Text::_('COM_TICKETSTATION_CPANEL_TESTMODE_OFF_TEXT');
    $modeTip = strip_tags($modeTip);

    // Joomla's own two-way switch: Live | Test. Changing it asks for confirmation, then saves.
    $confirms = [
        '0' => Text::_('COM_TICKETSTATION_CPANEL_TESTMODE_CONFIRM_LIVE'),
        '1' => Text::_('COM_TICKETSTATION_CPANEL_TESTMODE_CONFIRM_TEST'),
    ];
    ?>
    <?php
    // Joomla's confirmation dialog, as the delete buttons use it.
    Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('joomla.dialog');
    Text::script('WARNING');
    Text::script('JYES');
    Text::script('JNO');
    ?>
    <?php ob_start(); ?>
    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
        <?php if ($this->testBlocked) { ?>
            <small class="text-danger"><span class="fa fa-exclamation-triangle me-1" aria-hidden="true"></span><?= Text::sprintf('COM_TICKETSTATION_CPANEL_TESTMODE_NO_TEST_ENVIRONMENT', $this->escape($this->providerTitle)) ?></small>
        <?php } ?>
        <?php if ($this->canSwitchMode && ($this->testData->orders > 0 || $this->testData->customers > 0 || $this->testData->waiting > 0)) { ?>
            <form action="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel'); ?>" method="post" id="ts-testdata-form"
                class="d-inline-flex align-items-center gap-2 m-0 me-2"
                onsubmit="return tsConfirmSubmit(event, <?= $this->escape(json_encode(Text::_('COM_TICKETSTATION_TESTDATA_DELETE_CONFIRM'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)) ?>);">
                <input type="hidden" name="option" value="com_ticketstation" />
                <input type="hidden" name="controller" value="payments" />
                <input type="hidden" name="task" value="deletetestdata" />
                <?= HTMLHelper::_('form.token'); ?>
                <small class="text-muted"><?= Text::sprintf('COM_TICKETSTATION_TESTDATA_COUNTS', (int) $this->testData->orders, (int) $this->testData->customers) ?></small>
                <button type="submit" class="btn btn-sm btn-outline-danger"><span class="fa fa-trash me-1" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TESTDATA_DELETE') ?></button>
            </form>
        <?php } ?>
        <form action="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel'); ?>" method="post" id="ts-testmode-form"
            class="d-inline-flex align-items-center gap-2 m-0" title="<?= $this->escape($modeTip) ?>">
            <input type="hidden" name="option" value="com_ticketstation" />
            <input type="hidden" name="controller" value="payments" />
            <input type="hidden" name="task" value="testmode" />
            <?= HTMLHelper::_('form.token'); ?>
            <span class="d-inline-flex align-items-center gap-1 small fw-semibold text-uppercase <?= $this->testMode ? 'text-warning-emphasis' : 'text-muted'; ?>" style="letter-spacing: .04em;">
                <span class="fa fa-flask <?= $this->testMode ? 'text-warning' : ''; ?>" aria-hidden="true"></span>
                <?= Text::_('COM_TICKETSTATION_TESTMODE_SHOP_MODE') ?>
            </span>
            <?= LayoutHelper::render('joomla.form.field.radio.switcher', [
                'id'            => 'ts-testmode',
                'name'          => 'test_mode',
                'label'         => Text::_('COM_TICKETSTATION_PAYMENTS_TESTMODE'),
                'value'         => $this->testMode ? '1' : '0',
                'options'       => [
                    (object) ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_TESTMODE_LIVE')],
                    (object) ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_TESTMODE_TEST')],
                ],
                'onchange'      => 'tsTestModeChanged(event)',
                'dataAttribute' => '',
                'class'         => '',
                'readonly'      => false,
                'disabled'      => !$this->canSwitchMode,
            ]) ?>
        </form>
    </div>
    <?php $modeSwitch = ob_get_clean(); ?>
    <style>
        /* Joomla's switch is green when its second position is on. Here Live is the normal state (green)
           and Test is the one that needs attention (amber). */
        /* Joomla gives the switch a fixed width of 18rem; shrink it to the switch and its label so it lines up with the tiles. */
        #ts-testmode .switcher { width: 7rem; }
        #ts-testmode .switcher label { min-width: 2.5rem; }
        #ts-testmode .switcher .toggle-outside { background: #2f7d32; }
        #ts-testmode .switcher input ~ input:checked ~ .toggle-outside { background: #e0a100; }
    </style>
    <script>
        // The "test mode is on / off" message after switching goes away by itself; other messages (errors) stay.
        (function () {
            var texts = <?= json_encode([Text::_('COM_TICKETSTATION_TESTMODE_SWITCHED_ON'), Text::_('COM_TICKETSTATION_TESTMODE_SWITCHED_OFF')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

            function mark(root) {
                root.querySelectorAll('joomla-alert').forEach(function (alert) {
                    if (!alert.hasAttribute('auto-dismiss') && texts.some(function (text) { return alert.textContent.indexOf(text) !== -1; })) {
                        alert.setAttribute('auto-dismiss', 5000);
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var container = document.getElementById('system-message-container');

                if (!container) {
                    return;
                }

                mark(container);
                new MutationObserver(function () { mark(container); }).observe(container, {childList: true, subtree: true});
            });
        })();

        // Asks in Joomla's own confirmation dialog (Yes / No), like the delete buttons elsewhere; falls back to the browser's.
        function tsConfirm(message) {
            return import('joomla.dialog')
                .then(function (m) { return m.default.confirm(message, Joomla.Text._('WARNING', 'Warning')); })
                .catch(function () { return window.confirm(message); });
        }

        function tsConfirmSubmit(event, message) {
            event.preventDefault();
            var form = event.target;

            tsConfirm(message).then(function (ok) {
                if (ok) {
                    form.submit();
                }
            });

            return false;
        }

        function tsTestModeChanged(event) {
            var confirms = <?= json_encode($confirms, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

            tsConfirm(confirms[event.target.value]).then(function (ok) {
                if (ok) {
                    document.getElementById('ts-testmode-form').submit();
                } else {
                    // Back to the saved state.
                    window.location.reload();
                }
            });
        }
    </script>

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
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h2 class="mb-0"><span class="fa fa-chart-line text-primary me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_TITLE') ?></h2>
            <p class="text-muted"><?= Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_SUBTITLE', HTMLHelper::_('date', 'now', 'W'), HTMLHelper::_('date', 'now', 'F Y')) ?></p>
        </div>
        <?= $modeSwitch ?>
    </div>

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
                                    <td class="small d-none d-md-table-cell text-nowrap"><?= Date::display($row->startdate, $this->config->dateformat . ' ' . $this->config->time_format); ?></td>
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
                        // Online payments switched off: the Payments button looks faded and says so.
                        $off   = $view === 'payments' && !$this->paymentsOn;
                        ?>
                        <a class="ticketstation-cpanel-tile<?= $off ? ' ticketstation-cpanel-tile--off' : ''; ?>" href="index.php?option=com_ticketstation&view=<?= $view; ?>"<?= $off ? ' title="' . $this->escape(Text::_('COM_TICKETSTATION_CPANEL_MOLLIE_OFF_TITLE')) . '"' : ''; ?>>
                            <span class="ticketstation-cpanel-tile-icon" aria-hidden="true">
                                <?php // "fa fa-hotel" would hit Joomla's FA4 compat rule (a bed); fa-solid alone gives the building ?>
                                <span class="<?= str_starts_with($icon, 'fa-solid ') ? '' : 'fa '; ?><?= $icon; ?>"></span>
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
                <div class="col-12 col-xl-4">
                    <div class="text-center border border-primary border-2 rounded-3 bg-body text-body p-3">
                        <div class="fs-5 mb-1">
                            <span class="fa fa-heart text-danger" aria-hidden="true"></span>
                            <?= Text::_('COM_TICKETSTATION_ENJOYING') ?>
                        </div>
                        <?php // A review costs nothing and helps others find the extension, so it comes before the donation ?>
                        <p class="mb-3"><?= Text::_('COM_TICKETSTATION_CPANEL_REVIEW_TEXT') ?></p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="https://extensions.joomla.org/extension/calendars-a-events/ticketstation/"
                               class="btn btn-primary" target="blank" rel="noopener noreferrer">
                                <span class="fa fa-star" aria-hidden="true"></span>
                                <?= Text::_('COM_TICKETSTATION_CPANEL_REVIEW_BUTTON') ?>
                            </a>
                            <a href="https://ko-fi.com/klaasroelofs"
                               class="btn btn-success" target="blank" rel="noopener noreferrer">
                                <span class="fa fa-mug-hot" aria-hidden="true"></span>
                                <?= Text::_('COM_TICKETSTATION_DONATE') ?>
                            </a>
                        </div>
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
