<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Box Office topic on the central documentation page: the order list, the payment statuses
 * and what each action does (BoxofficeController / BoxofficeModel).
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
        <h2 class="h4"><span class="fa fa-money-bill-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_INTRO') ?></p>

        <details id="docs-boxoffice-list" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-search me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_LIST_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_LIST_', 3); ?>
            </div>
        </details>

        <details id="docs-boxoffice-status" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-tags me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_STATUS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_STATUS_', 5); ?>
            </div>
        </details>

        <details id="docs-boxoffice-actions" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_ACTIONS_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_ACTIONS_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_ACTIONS_', 4); ?>
            </div>
        </details>

        <details id="docs-boxoffice-order" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-receipt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_ORDER_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_ORDER_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_ORDER_', 5); ?>

                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_TICKETS_TITLE') ?></h3>
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_TICKETS_', 3); ?>

                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_DETAILS_TITLE') ?></h3>
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_DETAILS_', 3); ?>
            </div>
        </details>

        <details id="docs-boxoffice-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_BOXOFFICE_DOCS_GOTCHA_', 5); ?>
            </div>
        </details>
    </div>
</div>
