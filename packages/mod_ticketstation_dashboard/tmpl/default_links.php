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

/**
 * @var  array  $links  The chosen quick links this user may use (DashboardData::quickLinks()): link, icon, label, newTab, add
 */

// The buttons are Joomla's own quick icons (the same markup and style as the Site and System cards),
// so they look and behave like those. An item with an "add" link gets the plus button next to it.
$buttons = [];

foreach ($links as $item) {
    $button = [
        'image' => (str_starts_with($item->icon, 'fa-solid ') ? '' : 'fa ') . $item->icon,
        'link'  => htmlspecialchars($item->link, ENT_QUOTES, 'UTF-8'),
        'name'  => $item->label,
    ];

    if ($item->newTab) {
        $button['target'] = 'blank';
    }

    if ($item->add) {
        $button['linkadd'] = htmlspecialchars($item->add, ENT_QUOTES, 'UTF-8');
    }

    $buttons[] = $button;
}
?>
<nav class="quick-icons px-3 pb-3" aria-label="<?php echo htmlspecialchars(Text::_('MOD_TICKETSTATION_DASHBOARD_WIDGET_LINKS'), ENT_QUOTES, 'UTF-8'); ?>">
    <ul class="nav flex-wrap">
        <?php echo HTMLHelper::_('icons.buttons', $buttons); ?>
    </ul>
</nav>
