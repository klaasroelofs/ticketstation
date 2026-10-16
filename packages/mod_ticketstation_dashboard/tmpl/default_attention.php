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

/**
 * @var  array  $items  The attention items of DashboardData::attention(): key, count, link, icon, level
 */

$urgent = array_filter($items, static fn($item) => $item->level === 'danger');
$other  = array_filter($items, static fn($item) => $item->level !== 'danger');
?>
<?php if (!$items) { ?>
    <p class="mb-0 text-muted">
        <span class="fa fa-check-circle text-success me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_TICKETSTATION_CPANEL_ATTENTION_NONE'); ?>
    </p>
<?php } else { ?>
    <ul class="list-unstyled mb-0">
        <?php foreach (array_merge($urgent, $other) as $item) { ?>
            <li class="d-flex mb-2">
                <span class="fa fa-fw <?php echo htmlspecialchars($item->icon, ENT_QUOTES, 'UTF-8'); ?> text-<?php echo $item->level; ?> me-2 mt-1" aria-hidden="true"></span>
                <a href="<?php echo htmlspecialchars($item->link, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $item->level === 'danger' ? ' class="fw-semibold"' : ''; ?>><?php echo Text::plural($item->key, $item->count); ?></a>
            </li>
        <?php } ?>
    </ul>
<?php } ?>
