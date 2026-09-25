<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Invoicing topic on the central documentation page (see PaymentAPI::sendTickets()
 * and Invoice::generatePdf()).
 */

use Joomla\CMS\Language\Text;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$list = function (string $prefix, int $count) {
    echo '<ul class="mb-0">';
    for ($i = 1; $i <= $count; $i++) {
        echo '<li class="mb-1">' . Text::_($prefix . $i) . '</li>';
    }
    echo '</ul>';
};

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-file-invoice me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_TITLE') ?></h2>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_WHEN_TITLE') ?></h3>
        <p><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_WHEN') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_VIEW_TITLE') ?></h3>
        <p><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_VIEW') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_CONTENT_TITLE') ?></h3>
        <p><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_CONTENT') ?></p>

        <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_INVOICING_DOCS_SETTINGS_TITLE') ?></h3>
        <?php $list('COM_TICKETSTATION_INVOICING_DOCS_SETTINGS_', 4); ?>
    </div>
</div>
