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
 * @var  array      $rows    DashboardData::availability(): the next tickets with total, available, sold
 * @var  \stdClass  $config  The Ticketstation configuration row (date and time format)
 */

$esc = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$rows) { ?>
    <p class="mb-0 text-muted"><?php echo Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_NONE'); ?></p>
<?php } else { ?>
    <ul class="list-unstyled mb-2">
        <?php foreach ($rows as $row) {
            $left = 100 - $row->percentage_sold;

            if ($row->available <= 0) {
                $barClass  = 'bg-danger';
                $leftClass = 'text-danger';
            } elseif ($left < 11) {
                $barClass  = 'bg-warning';
                $leftClass = 'text-warning';
            } else {
                $barClass  = 'bg-primary';
                $leftClass = '';
            }
            ?>
            <li class="mb-3">
                <div class="d-flex justify-content-between align-items-baseline gap-2">
                    <a href="index.php?option=com_ticketstation&amp;controller=tickets&amp;task=edit&amp;cid=<?php echo (int) $row->ticketid; ?>"><?php echo $esc($row->eventname . ' - ' . $row->ticketname); ?></a>
                    <span class="text-nowrap small <?php echo $leftClass; ?>">
                        <?php echo $row->available <= 0 ? Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_SOLD_OUT') : Text::sprintf('MOD_TICKETSTATION_DASHBOARD_LEFT', $row->available); ?>
                    </span>
                </div>
                <div class="small text-muted"><?php echo Date::display($row->startdate, $config->dateformat . ' ' . $config->time_format); ?></div>
                <div class="progress mt-1" role="progressbar" aria-valuenow="<?php echo (int) $row->percentage_sold; ?>" aria-valuemin="0" aria-valuemax="100"
                     title="<?php echo (int) $row->sold; ?> / <?php echo (int) $row->total; ?>">
                    <div class="progress-bar <?php echo $barClass; ?>" style="width: <?php echo (int) $row->percentage_sold; ?>%"></div>
                </div>
            </li>
        <?php } ?>
    </ul>
    <a href="index.php?option=com_ticketstation&amp;view=tickets" class="small"><?php echo Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_ALL'); ?></a>
<?php } ?>
