<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Online payments topic on the central documentation page: the Payments screen, setting up Mollie and Stripe,
 * building your own payment plugin and how a payment flows through
 * site PaymentController (makepayment, webhook, return) and the Payment namespace (PaymentService, MollieProvider).
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
        <h2 class="h4"><span class="fa fa-credit-card me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_INTRO') ?></p>

        <details id="docs-payments-screen" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-sliders-h me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_SCREEN_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_SCREEN_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_SCREEN_', 4); ?>
            </div>
        </details>

        <details id="docs-payments-mollie-account" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-user-plus me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_MOLLIE_ACCOUNT_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_PAYMENTS_DOCS_MOLLIE_ACCOUNT_', 4); ?>
            </div>
        </details>

        <details id="docs-payments-mollie-settings" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-cog me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_MOLLIE_SETTINGS_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_MOLLIE_SETTINGS_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_MOLLIE_SETTINGS_', 6); ?>
            </div>
        </details>

        <details id="docs-payments-stripe-account" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-user-plus me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_ACCOUNT_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_ACCOUNT_INTRO') ?></p>
                <?php $steps('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_ACCOUNT_', 7); ?>
            </div>
        </details>

        <details id="docs-payments-stripe-settings" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-cog me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_SETTINGS_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_SETTINGS_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_SETTINGS_', 6); ?>
                <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_NOTES_TITLE') ?></h3>
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_STRIPE_NOTES_', 2); ?>
            </div>
        </details>

        <details id="docs-payments-build" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-plug me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_BUILD_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_BUILD_INTRO') ?></p>
                <?php $steps('COM_TICKETSTATION_PAYMENTS_DOCS_BUILD_', 8); ?>
            </div>
        </details>

        <details id="docs-payments-off" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-power-off me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_OFF_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_OFF_INTRO') ?></p>
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_OFF_', 5); ?>
            </div>
        </details>

        <details id="docs-payments-flow"class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exchange-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_FLOW_TITLE') ?></summary>
            <div class="mt-3">
                <?php $steps('COM_TICKETSTATION_PAYMENTS_DOCS_FLOW_', 4); ?>
            </div>
        </details>

        <details id="docs-payments-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PAYMENTS_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_PAYMENTS_DOCS_GOTCHA_', 8); ?>
            </div>
        </details>
    </div>
</div>
