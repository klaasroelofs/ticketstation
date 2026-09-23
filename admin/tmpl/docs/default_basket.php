<?php

use Joomla\CMS\Language\Text;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Basket module topic on the central documentation page. The module itself
 * (mod_ticketstation_basket) ships in the Ticketstation package, next to this
 * component; the event views only keep the itemcount/updatecart AJAX calls it relies on.
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$list = function (string $prefix, int $count, string $tag = 'ul') {
    echo '<' . $tag . '>';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</' . $tag . '>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-shopping-basket me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_INTRO') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_SETUP_TITLE') ?></h3>
        <?php $list('COM_TICKETSTATION_BASKET_DOCS_SETUP_', 3, 'ol'); ?>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_MODES_TITLE') ?></h3>
        <ul>
            <li class="mb-1"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_MODE_MINI') ?></li>
            <li class="mb-1"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_MODE_FULL') ?></li>
        </ul>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_SETTINGS_TITLE') ?></h3>
        <?php $list('COM_TICKETSTATION_BASKET_DOCS_SETTINGS_', 4); ?>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_BEHAVIOUR_TITLE') ?></h3>
        <?php $list('COM_TICKETSTATION_BASKET_DOCS_BEHAVIOUR_', 4); ?>

        <div class="alert alert-info mt-3 mb-0">
            <h3 class="h6"><span class="fa fa-layer-group me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_MIXED_TITLE') ?></h3>
            <p class="mb-0"><?= Text::_('COM_TICKETSTATION_BASKET_DOCS_MIXED') ?></p>
        </div>
    </div>
</div>
