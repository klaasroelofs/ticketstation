<?php

use Joomla\CMS\Language\Text;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Waiting list topic on the central documentation page. Seated events are
 * intentionally out of scope for this feature - see [[waitinglist-feature-status]].
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$steps = function (string $prefix, int $count) {
    echo '<ol>';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ol>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-hourglass-half me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_TITLE') ?></h2>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_ENABLE_TITLE') ?></h3>
        <p><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_ENABLE') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_SCREEN_TITLE') ?></h3>
        <p><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_SCREEN') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_FLOW_TITLE') ?></h3>
        <?php $steps('COM_TICKETSTATION_WAITINGLIST_DOCS_FLOW_', 4); ?>

        <div class="alert alert-warning mt-3 mb-0">
            <h3 class="h6"><span class="fa fa-chair me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_SEATED_TITLE') ?></h3>
            <p class="mb-0"><?= Text::_('COM_TICKETSTATION_WAITINGLIST_DOCS_SEATED') ?></p>
        </div>
    </div>
</div>
