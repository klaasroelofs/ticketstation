<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Apple Wallet / Google Wallet topic on the central documentation page: what customers get,
 * setting up both wallets (Configuration > Wallet, Helper\Wallet, WalletApple, WalletGoogle),
 * the look of the passes and the limits of version 1 (site WalletController).
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
        <h2 class="h4"><span class="fa fa-wallet me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_INTRO') ?></p>

        <details id="docs-wallet-customer" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-mobile-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_CUSTOMER_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_WALLET_DOCS_CUSTOMER_', 5); ?>
            </div>
        </details>

        <details id="docs-wallet-apple" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fab fa-apple me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_APPLE_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_APPLE_INTRO') ?></p>
                <?php $steps('COM_TICKETSTATION_WALLET_DOCS_APPLE_', 5); ?>
                <p><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_APPLE_P12') ?></p>
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_APPLE_RENEW') ?></p>
            </div>
        </details>

        <details id="docs-wallet-google" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fab fa-google me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_GOOGLE_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_GOOGLE_INTRO') ?></p>
                <?php $steps('COM_TICKETSTATION_WALLET_DOCS_GOOGLE_', 8); ?>
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_GOOGLE_DEMO') ?></p>
            </div>
        </details>

        <details id="docs-wallet-live" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-sync-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_LIVE_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_WALLET_DOCS_LIVE_', 5); ?>
            </div>
        </details>

        <details id="docs-wallet-look" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-palette me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_LOOK_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_WALLET_DOCS_LOOK_', 3); ?>
            </div>
        </details>

        <details id="docs-wallet-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_WALLET_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_WALLET_DOCS_GOTCHA_', 7); ?>
            </div>
        </details>
    </div>
</div>
