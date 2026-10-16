<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

/**
 * @var  array  $stats       DashboardData::stats(): week, prev_week, month, on_sale_events, on_sale_tickets
 * @var  array  $dailySales  DashboardData::dailySales(): date, tickets, revenue per day
 */

$weekDiff = $stats['week']->tickets - $stats['prev_week']->tickets;
$maxDaily = max(1, max(array_column($dailySales, 'tickets')));
$sum28    = array_sum(array_column($dailySales, 'tickets'));
$barWidth = 10;
$height   = 60;
?>
<div class="ts-dash-kpis">
    <div class="ts-dash-kpi">
        <div class="text-muted small"><?php echo Text::_('COM_TICKETSTATION_CPANEL_STATS_SOLD_WEEK'); ?></div>
        <div class="ts-dash-kpi-value"><?php echo $stats['week']->tickets; ?></div>
        <div class="small text-muted">
            <?php if ($weekDiff != 0) { ?>
                <span class="fw-semibold <?php echo $weekDiff > 0 ? 'text-success' : 'text-danger'; ?>">
                    <span class="fa <?php echo $weekDiff > 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>" aria-hidden="true"></span>
                    <?php echo sprintf('%+d', $weekDiff); ?>
                </span> &middot;
            <?php } ?>
            <?php echo Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_PREV_WEEK', $stats['prev_week']->tickets); ?>
        </div>
    </div>
    <div class="ts-dash-kpi">
        <div class="text-muted small"><?php echo Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_WEEK'); ?></div>
        <div class="ts-dash-kpi-value"><?php echo Price::_($stats['week']->revenue); ?></div>
        <div class="small text-muted"><?php echo Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_ORDERS', $stats['week']->orders); ?></div>
    </div>
    <div class="ts-dash-kpi">
        <div class="text-muted small"><?php echo Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_MONTH'); ?></div>
        <div class="ts-dash-kpi-value"><?php echo Price::_($stats['month']->revenue); ?></div>
        <div class="small text-muted"><?php echo Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_TICKETS', $stats['month']->tickets); ?></div>
    </div>
    <div class="ts-dash-kpi">
        <div class="text-muted small"><?php echo Text::_('COM_TICKETSTATION_CPANEL_STATS_ON_SALE'); ?></div>
        <div class="ts-dash-kpi-value"><?php echo $stats['on_sale_tickets']; ?></div>
        <div class="small text-muted"><?php echo Text::plural('COM_TICKETSTATION_CPANEL_STATS_TICKETS_LABEL', $stats['on_sale_tickets']); ?>
            &middot; <?php echo $stats['on_sale_events']; ?> <?php echo Text::plural('COM_TICKETSTATION_CPANEL_STATS_EVENTS_LABEL', $stats['on_sale_events']); ?></div>
    </div>
</div>

<div class="mt-3">
    <div class="d-flex justify-content-between align-items-baseline small text-muted mb-1">
        <span><?php echo Text::_('COM_TICKETSTATION_CPANEL_CHART_HEADER'); ?></span>
        <span><?php echo Text::plural('COM_TICKETSTATION_CPANEL_CHART_TOTAL', $sum28); ?></span>
    </div>
    <svg class="ts-dash-chart" viewBox="0 0 <?php echo count($dailySales) * $barWidth; ?> <?php echo $height; ?>" preserveAspectRatio="none" role="img" aria-label="<?php echo htmlspecialchars(Text::plural('COM_TICKETSTATION_CPANEL_CHART_ARIA', $sum28), ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($dailySales as $index => $day) {
            $barHeight = $day->tickets > 0 ? max(2, round($day->tickets / $maxDaily * $height, 1)) : 1;
            $label     = HTMLHelper::_('date', $day->date, Text::_('DATE_FORMAT_LC1'), null);
            ?>
            <rect class="<?php echo $day->tickets > 0 ? 'is-sale' : 'is-empty'; ?>" x="<?php echo $index * $barWidth + 1; ?>" y="<?php echo $height - $barHeight; ?>" width="<?php echo $barWidth - 2; ?>" height="<?php echo $barHeight; ?>">
                <title><?php echo htmlspecialchars($label . ': ' . Text::plural('COM_TICKETSTATION_CPANEL_CHART_BAR', $day->tickets) . ' · ' . html_entity_decode(strip_tags(Price::_($day->revenue)), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></title>
            </rect>
        <?php } ?>
    </svg>
    <div class="d-flex justify-content-between small text-muted mt-1">
        <span><?php echo HTMLHelper::_('date', $dailySales[0]->date, 'j M', null); ?></span>
        <span><?php echo Text::_('COM_TICKETSTATION_CPANEL_CHART_TODAY'); ?></span>
    </div>
</div>
