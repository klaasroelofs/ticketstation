<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

/**
 * Variables provided by Dispatcher::getLayoutData():
 *
 * @var  \Joomla\Registry\Registry  $params  Module parameters
 * @var  \stdClass                  $module  Module object
 * @var  string                     $widget  attention, stats, upcoming or links
 *
 * The rest depends on the widget, see the default_<widget>.php layouts.
 */
?>
<div class="ts-dash ts-dash--<?php echo $widget; ?><?php echo $params->get('moduleclass_sfx') ? ' ' . htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8') : ''; ?>">
    <?php require ModuleHelper::getLayoutPath($module->module, 'default_' . $widget); ?>
</div>
