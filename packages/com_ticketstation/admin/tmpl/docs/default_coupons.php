<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Coupons topic on the central documentation page: setting up a coupon, how the discount is
 * calculated and how uses are counted (see the Coupon helper).
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
        <h2 class="h4"><span class="fa fa-percent me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_INTRO') ?></p>

        <details id="docs-coupons-setup" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-plus-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_SETUP_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_COUPONS_DOCS_SETUP_', 4); ?>
            </div>
        </details>

        <details id="docs-coupons-how" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-calculator me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_HOW_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_COUPONS_DOCS_HOW_', 4); ?>
            </div>
        </details>

        <details id="docs-coupons-usage" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-hashtag me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_USAGE_TITLE') ?></summary>
            <div class="mt-3">
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_USAGE') ?></p>
            </div>
        </details>

        <details id="docs-coupons-gotchas" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-exclamation-circle me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_COUPONS_DOCS_GOTCHAS_TITLE') ?></summary>
            <div class="mt-3">
                <?php $bullets('COM_TICKETSTATION_COUPONS_DOCS_GOTCHA_', 4); ?>
            </div>
        </details>
    </div>
</div>
