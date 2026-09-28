<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Getting started topic on the central documentation page: a checklist from a fresh
 * installation to the first sale, pointing to the other topics.
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
        <h2 class="h4"><span class="fa fa-flag-checkered me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_GETTINGSTARTED_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_GETTINGSTARTED_DOCS_INTRO') ?></p>

        <details id="docs-gettingstarted-steps" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list-ol me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_GETTINGSTARTED_DOCS_STEPS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ol', 'COM_TICKETSTATION_GETTINGSTARTED_DOCS_STEP_', 9); ?>
            </div>
        </details>

        <details id="docs-gettingstarted-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_GETTINGSTARTED_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ul', 'COM_TICKETSTATION_GETTINGSTARTED_DOCS_GOTCHA_', 4); ?>
            </div>
        </details>
    </div>
</div>
