<?php

use Joomla\CMS\Language\Text;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Configuration screen topic on the central documentation page. Mirrors the
 * tabs of admin/tmpl/configuration/default.php.
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$tabs = [
    'GENERAL'   => 'fa-sliders-h',
    'DISPLAY'   => 'fa-eye',
    'ORDERS'    => 'fa-receipt',
    'CHECKOUT'  => 'fa-cash-register',
    'DOCUMENTS' => 'fa-file-alt',
    'COMPANY'   => 'fa-building',
];

$list = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . '_' . $i) . '</li>';
    }
    echo '</ul>';
};

// Number of bullet strings defined per tab (see language file).
$counts = [
    'GENERAL'   => 6,
    'DISPLAY'   => 3,
    'ORDERS'    => 2,
    'CHECKOUT'  => 2,
    'DOCUMENTS' => 6,
    'COMPANY'   => 3,
];

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-cog me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONFIG_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_CONFIG_DOCS_INTRO') ?></p>

        <?php foreach ($tabs as $tab => $icon) { ?>
            <details class="mb-3 border rounded p-3">
                <summary class="h5 mb-0"><span class="fa <?= $icon ?> me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CONFIG_DOCS_TAB_' . $tab) ?></summary>
                <div class="mt-3">
                    <?php $list('COM_TICKETSTATION_CONFIG_DOCS_TAB_' . $tab, $counts[$tab]); ?>
                </div>
            </details>
        <?php } ?>
    </div>
</div>
