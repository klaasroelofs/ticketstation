<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Customer portal topic on the central documentation page: switching it on (Configuration >
 * Customer portal), how customers get in, what they see and the limits of the first version
 * (Helper\Portal, site PortalController).
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

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-user-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_INTRO') ?></p>

        <details id="docs-portal-setup" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-toggle-on me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_SETUP_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_PORTAL_DOCS_SETUP_', 4); ?>
            </div>
        </details>

        <details id="docs-portal-access" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-key me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_ACCESS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_PORTAL_DOCS_ACCESS_', 6); ?>
            </div>
        </details>

        <details id="docs-portal-what" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-list me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_WHAT_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_PORTAL_DOCS_WHAT_', 6); ?>
            </div>
        </details>

        <details id="docs-portal-limits" class="mb-0 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-info-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PORTAL_DOCS_LIMITS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_PORTAL_DOCS_LIMITS_', 2); ?>
            </div>
        </details>
    </div>
</div>
