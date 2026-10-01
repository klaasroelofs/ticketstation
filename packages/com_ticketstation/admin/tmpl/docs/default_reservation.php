<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * New reservation topic on the central documentation page: the four-step booking tool for
 * phone and walk-in customers (ReservationController / ReservationModel).
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
        <h2 class="h4"><span class="fa fa-calendar-plus me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RESERVATION_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_RESERVATION_DOCS_INTRO') ?></p>

        <details id="docs-reservation-steps" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list-ol me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RESERVATION_DOCS_STEPS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ol', 'COM_TICKETSTATION_RESERVATION_DOCS_STEP_', 5); ?>
            </div>
        </details>

        <details id="docs-reservation-unpaid" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-clock me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RESERVATION_DOCS_UNPAID_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ul', 'COM_TICKETSTATION_RESERVATION_DOCS_UNPAID_', 3); ?>
            </div>
        </details>

        <details id="docs-reservation-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RESERVATION_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('ul', 'COM_TICKETSTATION_RESERVATION_DOCS_GOTCHA_', 5); ?>
            </div>
        </details>
    </div>
</div>
