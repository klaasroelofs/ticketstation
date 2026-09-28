<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Automatic tasks topic on the central documentation page: the housekeeping that runs when
 * the component boots (TicketstationComponent::runStartupTasks(), Ticketcleaner, Ticketstarter).
 */

use Joomla\CMS\Language\Text;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$bullets = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ul>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-robot me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_AUTOMATION_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_AUTOMATION_DOCS_INTRO') ?></p>

        <details id="docs-automation-tasks" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-cogs me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_AUTOMATION_DOCS_TASKS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_AUTOMATION_DOCS_TASK_', 5); ?>
            </div>
        </details>

        <details id="docs-automation-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_AUTOMATION_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_AUTOMATION_DOCS_GOTCHA_', 4); ?>
            </div>
        </details>
    </div>
</div>
