<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * The seat plan editor. This is only the frame: assets/js/seateditor.js draws the chart and
 * the side panel from the layout in the script options (see SeatplanLayout::forEditor()).
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app = Factory::getApplication();
$app->getDocument()->setTitle(Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_CHART') . ' - ' . $app->get('sitename'));

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=seatplans'); ?>" method="post" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="seatplans" />
    <input type="hidden" name="task" value="" />
    <?= HTMLHelper::_('form.token'); ?>
</form>

<div id="ts-seateditor" class="ts-se">
    <noscript><div class="alert alert-warning"><?= Text::_('COM_TICKETSTATION_SE_NEEDS_JS'); ?></div></noscript>
</div>
