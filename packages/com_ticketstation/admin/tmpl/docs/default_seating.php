<?php

use Joomla\CMS\Language\Text;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Seated tickets topic on the central documentation page: seat plan settings,
 * the three Multi Seat set-ups (seatplansettings.multi_seat, see
 * SeatplansController and site OrderseatedController::makeReservation()), the
 * seat chart editor and the customer's seat-picking flow.
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

$bullets = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ul>';
};

$modes = ['SINGLE', 'TIERS', 'SECTIONS'];

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-chair me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_INTRO') ?></p>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list-ol me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_SETUP_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_SEATING_DOCS_SETUP_', 5); ?>
            </div>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-layer-group me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MULTISEAT_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MULTISEAT_INTRO') ?></p>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-top">
                        <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_COL_SETUP') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_COL_TICKETS') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_COL_PRICE') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_COL_STOCK') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modes as $mode) { ?>
                                <tr>
                                    <th scope="row"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MODE_' . $mode) ?></th>
                                    <td><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MODE_' . $mode . '_TICKETS') ?></td>
                                    <td><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MODE_' . $mode . '_PRICE') ?></td>
                                    <td><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MODE_' . $mode . '_STOCK') ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MULTISEAT_CHOOSE') ?></p>
            </div>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-pencil-ruler me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_EDITOR_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_EDITOR_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_SEATING_DOCS_EDITOR_', 6); ?>

                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MONITOR_TITLE') ?></h3>
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_MONITOR') ?></p>
            </div>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-hand-pointer me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_FLOW_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_SEATING_DOCS_FLOW_', 5); ?>

                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_RELEASE_TITLE') ?></h3>
                <?php $bullets('COM_TICKETSTATION_SEATING_DOCS_RELEASE_', 4); ?>
            </div>
        </details>

        <details class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SEATING_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_SEATING_DOCS_GOTCHA_', 7); ?>
            </div>
        </details>
    </div>
</div>
