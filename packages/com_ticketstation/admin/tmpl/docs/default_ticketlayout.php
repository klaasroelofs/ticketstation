<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticket layout topic on the central documentation page: the "Ticket layout" tab of the
 * ticket form, as rendered by ticketcreator::doPDF(), TicketPreviewCreator and
 * DefaultTicketLayout.
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

$list = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ul>';
};

## Field label (as on the ticket form) => what gets printed
$fields = [
    'COM_TICKETSTATION_EVENTNAME'        => 'EVENTNAME',
    'COM_TICKETSTATION_TICKETNAME'       => 'TICKETNAME',
    'COM_TICKETSTATION_FREETEXT_1'       => 'FREETEXT',
    'COM_TICKETSTATION_TICKETDATE'       => 'TICKETDATE',
    'COM_TICKETSTATION_TICKETPRICE'      => 'TICKETPRICE',
    'COM_TICKETSTATION_ORDERDATE'        => 'ORDERDATE',
    'COM_TICKETSTATION_CLIENT'           => 'CLIENT',
    'COM_TICKETSTATION_ORDERTICKETINDEX' => 'ORDERTICKETINDEX',
    'COM_TICKETSTATION_ORDERNUMBER'      => 'ORDERNUMBER',
    'COM_TICKETSTATION_SEATNUMBER'       => 'SEATNUMBER',
    'COM_TICKETSTATION_ORDERREFERENCE'   => 'ORDERREFERENCE',
    'COM_TICKETSTATION_QRCODE'           => 'QRCODE',
];

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-ticket-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_INTRO') ?></p>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list-ol me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_SETUP_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_TICKETLAYOUT_DOCS_SETUP_', 5); ?>
            </div>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-ruler-combined me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_FORMAT_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('COM_TICKETSTATION_TICKETLAYOUT_DOCS_FORMAT_', 5); ?>
            </div>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-th-list me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_FIELDS_TITLE') ?></summary>
            <div class="mt-3">
                <div class="table-responsive">
                    <table class="table table-sm align-top mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_COL_FIELD') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_COL_PRINTS') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $label => $key) { ?>
                                <tr>
                                    <th scope="row" class="text-nowrap"><?= Text::_($label) ?></th>
                                    <td><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_FIELD_' . $key) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_TICKETLAYOUT_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $list('COM_TICKETSTATION_TICKETLAYOUT_DOCS_GOTCHA_', 7); ?>
            </div>
        </details>
    </div>
</div>
