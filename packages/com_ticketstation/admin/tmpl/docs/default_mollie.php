<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Mollie topic on the central documentation page: getting a Mollie account, the
 * settings on the Mollie screen (view=mollie) and how a payment flows through
 * site PaymentController (makepayment, IPNProcessPayment, mollie).
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
        <h2 class="h4"><span class="fa fa-credit-card me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_INTRO') ?></p>

        <details id="docs-mollie-account" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-user-plus me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_ACCOUNT_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_MOLLIE_DOCS_ACCOUNT_', 4); ?>
            </div>
        </details>

        <details id="docs-mollie-settings" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-cog me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_SETTINGS_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_SETTINGS_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_MOLLIE_DOCS_SETTINGS_', 8); ?>
            </div>
        </details>

        <details id="docs-mollie-flow" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exchange-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_FLOW_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_MOLLIE_DOCS_FLOW_', 4); ?>
            </div>
        </details>

        <details id="docs-mollie-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_MOLLIE_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_MOLLIE_DOCS_GOTCHA_', 6); ?>
            </div>
        </details>
    </div>
</div>
