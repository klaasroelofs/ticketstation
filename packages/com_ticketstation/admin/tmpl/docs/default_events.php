<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Events & tickets topic on the central documentation page, including the
 * parent/child ticket relationship (Ticket::parent, see TicketModel).
 */

use Joomla\CMS\Language\Text;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$steps = function (string $prefix, int $count) {
    echo '<ol>';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ol>';
};

$gotchas = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ul>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-calendar-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_INTRO') ?></p>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list-ol me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_WALKTHROUGH_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_EVENTS_DOCS_WALKTHROUGH_', 4); ?>
            </div>
        </details>

        <details class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-sitemap me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_PARENTCHILD_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_PARENTCHILD_WHAT') ?></p>
                <p><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_PARENTCHILD_PURPOSE') ?></p>

                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_EVENTS_DOCS_PARENTCHILD_GOTCHAS_TITLE') ?></h3>
                <?php $gotchas('COM_TICKETSTATION_EVENTS_DOCS_PARENTCHILD_GOTCHA_', 5); ?>
            </div>
        </details>
    </div>
</div>
