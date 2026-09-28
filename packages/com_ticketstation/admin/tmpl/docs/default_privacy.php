<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Personal data topic on the central documentation page: what is stored about customers,
 * how long it is kept and how to remove it.
 */

use Joomla\CMS\Language\Text;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$list = function (string $tag, string $prefix, int $count) {
    echo '<' . $tag . ' class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</' . $tag . '>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-user-shield me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_INTRO') ?></p>

        <details id="docs-privacy-stored" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-database me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_STORED_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ul', 'COM_TICKETSTATION_PRIVACY_DOCS_STORED_', 5); ?>
            </div>
        </details>

        <details id="docs-privacy-retention" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-clock me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_RETENTION_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ul', 'COM_TICKETSTATION_PRIVACY_DOCS_RETENTION_', 2); ?>
            </div>
        </details>

        <details id="docs-privacy-remove" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-user-times me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_REMOVE_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ol', 'COM_TICKETSTATION_PRIVACY_DOCS_REMOVE_', 3); ?>
                <p class="mt-3 mb-0"><?= Text::_('COM_TICKETSTATION_PRIVACY_DOCS_REMOVE_4') ?></p>
            </div>
        </details>
    </div>
</div>
