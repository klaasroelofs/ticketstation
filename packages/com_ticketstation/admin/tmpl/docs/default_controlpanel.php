<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Control panel topic on the central documentation page: the sales figures, the availability
 * table and the Needs attention list (ControlpanelModel).
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
        <h2 class="h4"><span class="fa fa-home me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_INTRO') ?></p>

        <details id="docs-controlpanel-figures" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-chart-line me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_FIGURES_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_CONTROLPANEL_DOCS_FIGURES_', 4); ?>
            </div>
        </details>

        <details id="docs-controlpanel-availability" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-chart-bar me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_AVAILABILITY_TITLE') ?></summary>
            <div class="mt-3">
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_AVAILABILITY') ?></p>
            </div>
        </details>

        <details id="docs-controlpanel-attention" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-bell me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_ATTENTION_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_ATTENTION_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_CONTROLPANEL_DOCS_ATTENTION_', 8); ?>
            </div>
        </details>

        <details id="docs-controlpanel-version" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-sync-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_VERSION_TITLE') ?></summary>
            <div class="mt-3">
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_CONTROLPANEL_DOCS_VERSION') ?></p>
            </div>
        </details>
    </div>
</div>
