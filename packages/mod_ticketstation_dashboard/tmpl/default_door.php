<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

/**
 * @var  array      $events  DashboardData::doorToday(): eventid, eventname, start, sold, scanned, left, percentage, lastscan
 * @var  \stdClass  $config  The Ticketstation configuration row (time format)
 */

$esc = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<ul class="list-unstyled mb-0" data-ts-door>
    <?php foreach ($events as $event) { ?>
        <li class="ts-door mb-3" data-event="<?php echo (int) $event->eventid; ?>">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <strong><?php echo $esc($event->eventname); ?></strong>
                <span class="small text-muted text-nowrap"><?php echo Date::display($event->start, $config->time_format); ?></span>
            </div>
            <div class="progress mt-1" role="progressbar" aria-valuenow="<?php echo (int) $event->percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success ts-door-bar" style="width: <?php echo (int) $event->percentage; ?>%"></div>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-x-3 small mt-1">
                <span>
                    <strong class="ts-door-scanned"><?php echo (int) $event->scanned; ?></strong> / <span class="ts-door-sold"><?php echo (int) $event->sold; ?></span>
                    <?php echo Text::_('MOD_TICKETSTATION_DASHBOARD_DOOR_SCANNED'); ?>
                    (<span class="ts-door-percentage"><?php echo (int) $event->percentage; ?></span>%)
                </span>
                <span><strong class="ts-door-left"><?php echo (int) $event->left; ?></strong> <?php echo Text::_('MOD_TICKETSTATION_DASHBOARD_DOOR_TO_COME'); ?></span>
            </div>
            <div class="small text-muted ts-door-lastscan-row"<?php echo $event->lastscan ? '' : ' hidden'; ?>>
                <?php echo Text::_('MOD_TICKETSTATION_DASHBOARD_DOOR_LAST_SCAN'); ?>
                <span class="ts-door-lastscan"><?php echo $event->lastscan ? Date::display($event->lastscan, $config->time_format) : ''; ?></span>
            </div>
        </li>
    <?php } ?>
</ul>
