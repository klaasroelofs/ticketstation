<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Venues, Customers and Transactions topic on the central documentation page: the three
 * supporting screens of the control panel (VenuesModel, ClientsModel, TransactionsModel).
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

$sections = [
    'VENUES'       => ['icon' => 'fa-map-marker-alt', 'count' => 4],
    'CUSTOMERS'    => ['icon' => 'fa-users',          'count' => 3],
    'TRANSACTIONS' => ['icon' => 'fa-credit-card',    'count' => 3],
];

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-address-book me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RECORDS_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_RECORDS_DOCS_INTRO') ?></p>

        <?php foreach ($sections as $section => $meta) { ?>
            <details id="docs-records-<?= strtolower($section) ?>" class="<?= $section === array_key_last($sections) ? '' : 'mb-3 ' ?>border rounded p-3">
                <summary class="h5 mb-0"><span class="fa <?= $meta['icon'] ?> me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_RECORDS_DOCS_' . $section . '_TITLE') ?></summary>
                <div class="mt-3">
                    <?php $bullets('COM_TICKETSTATION_RECORDS_DOCS_' . $section . '_', $meta['count']); ?>
                </div>
            </details>
        <?php } ?>
    </div>
</div>
