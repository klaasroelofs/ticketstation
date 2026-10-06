<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Central documentation hub: a shell page that loads one sub-template per topic
 * (default_<topic>.php). Add a new topic by dropping a default_<topic>.php file
 * here and adding it to a group in $groups below - no other wiring needed.
 *
 * Every subsection (<details>) in a topic carries an id "docs-<topic>-<name>" so it can
 * be linked to directly; the admin screens' Help buttons (Helper\Docs) use these anchors.
 * Search, the sidebar, deep links and the expand/print buttons live in assets/js/docs.js.
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_DOCS_TITLE') . ' - ' . $app->get('sitename'));

$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('com_ticketstation.docs', Uri::base() . 'components/com_ticketstation/assets/css/docs.css');
$wa->registerAndUseScript('com_ticketstation.docs', Uri::base() . 'components/com_ticketstation/assets/js/docs.js', [], ['defer' => true], ['core']);

foreach (['SEARCH_RESULTS', 'SEARCH_RESULTS_ONE_TOPIC', 'SEARCH_RESULT_ONE', 'SEARCH_NONE', 'COPY_LINK', 'LINK_COPIED'] as $key) {
    Text::script('COM_TICKETSTATION_DOCS_' . $key);
}

$groups = [
    'BASICS' => [
        'gettingstarted' => 'fa-flag-checkered',
        'controlpanel'   => 'fa-home',
        'configuration'  => 'fa-cog',
        'permissions'    => 'fa-user-lock',
    ],
    'SALES' => [
        'events'       => 'fa-calendar-alt',
        'seating'      => 'fa-chair',
        'ticketlayout' => 'fa-ticket-alt',
        'coupons'      => 'fa-percent',
        'waitinglist'  => 'fa-hourglass-half',
        'basket'       => 'fa-shopping-basket',
    ],
    'ORDERS' => [
        'boxoffice'   => 'fa-money-bill-alt',
        'reservation' => 'fa-calendar-plus',
        'payments'    => 'fa-credit-card',
        'invoicing'   => 'fa-file-invoice',
        'templates'   => 'fa-envelope',
        'wallet'      => 'fa-wallet',
    ],
    'EVENTDAY' => [
        'scanning' => 'fa-qrcode',
    ],
    'DATA' => [
        'records'    => 'fa-address-book',
        'automation' => 'fa-robot',
        'privacy'    => 'fa-user-shield',
    ],
];

$topicTitle = fn (string $slug) => Text::_('COM_TICKETSTATION_DOCS_NAV_' . strtoupper($slug));
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=docs'); ?>" method="post" name="adminForm" id="adminForm">
<div class="ticketstation-docs" id="ts-docs-top">
    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4">
        <?php // White box so the dark-blue wordmark stays readable in the dark admin theme too ?>
        <div class="flex-shrink-0 p-3 rounded text-center" style="background: #fff;">
            <img src="components/com_ticketstation/assets/images/logo_ticketstation_for_joomla.png" alt="Ticketstation for Joomla!" class="img-fluid" style="max-height: 60px;">
        </div>
        <p class="lead mb-0"><?= Text::_('COM_TICKETSTATION_DOCS_INTRO') ?></p>
    </div>

    <div class="ts-docs-toolbar card mb-4">
        <div class="card-body d-flex flex-column flex-md-row gap-2 align-items-md-center">
            <div class="ts-docs-search flex-grow-1" role="search">
                <label for="ts-docs-search" class="visually-hidden"><?= Text::_('COM_TICKETSTATION_DOCS_SEARCH_LABEL') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><span class="fa fa-search" aria-hidden="true"></span></span>
                    <input type="search" id="ts-docs-search" class="form-control" autocomplete="off" spellcheck="false"
                           placeholder="<?= $this->escape(Text::_('COM_TICKETSTATION_DOCS_SEARCH_PLACEHOLDER')) ?>"
                           aria-describedby="ts-docs-search-status" aria-keyshortcuts="/">
                    <button type="button" class="btn btn-outline-secondary" id="ts-docs-search-clear" hidden>
                        <span class="fa fa-times" aria-hidden="true"></span><span class="visually-hidden"><?= Text::_('COM_TICKETSTATION_DOCS_SEARCH_CLEAR') ?></span>
                    </button>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-docs-expand="1">
                    <span class="fa fa-plus-square me-1" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_DOCS_EXPAND_ALL') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-docs-expand="0">
                    <span class="fa fa-minus-square me-1" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_DOCS_COLLAPSE_ALL') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-docs-print>
                    <span class="fa fa-print me-1" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_DOCS_PRINT') ?>
                </button>
            </div>
        </div>
        <div class="card-footer small text-body-secondary py-2" id="ts-docs-search-status" aria-live="polite"><?= Text::_('COM_TICKETSTATION_DOCS_SEARCH_HINT') ?></div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-xl-2 d-none d-lg-block">
            <nav class="ts-docs-sidebar" aria-label="<?= $this->escape(Text::_('COM_TICKETSTATION_DOCS_TOPICS')) ?>">
                <?php foreach ($groups as $group => $topics) { ?>
                    <div data-docs-navgroup>
                        <div class="ts-docs-group-label"><?= Text::_('COM_TICKETSTATION_DOCS_GROUP_' . $group) ?></div>
                        <ul class="list-unstyled mb-3">
                            <?php foreach ($topics as $slug => $icon) { ?>
                                <li data-docs-nav="<?= $slug ?>">
                                    <a class="ts-docs-navlink" href="#docs-<?= $slug ?>">
                                        <span class="fa <?= $icon ?> fa-fw me-1" aria-hidden="true"></span>
                                        <span class="flex-grow-1"><?= $topicTitle($slug) ?></span>
                                        <span class="badge rounded-pill ts-docs-count" hidden></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>
            </nav>
        </div>

        <div class="col-lg-9 col-xl-10">
            <div class="d-lg-none mb-4">
                <label for="ts-docs-jump" class="visually-hidden"><?= Text::_('COM_TICKETSTATION_DOCS_JUMP') ?></label>
                <select id="ts-docs-jump" class="form-select">
                    <option value=""><?= Text::_('COM_TICKETSTATION_DOCS_JUMP') ?>…</option>
                    <?php foreach ($groups as $group => $topics) { ?>
                        <optgroup label="<?= Text::_('COM_TICKETSTATION_DOCS_GROUP_' . $group) ?>">
                            <?php foreach ($topics as $slug => $icon) { ?>
                                <option value="docs-<?= $slug ?>"><?= $topicTitle($slug) ?></option>
                            <?php } ?>
                        </optgroup>
                    <?php } ?>
                </select>
            </div>

            <div class="alert alert-info" id="ts-docs-noresults" hidden></div>

            <?php foreach ($groups as $group => $topics) { ?>
                <div class="ts-docs-group" data-docs-group="<?= strtolower($group) ?>">
                    <div class="ts-docs-group-label ts-docs-group-heading"><?= Text::_('COM_TICKETSTATION_DOCS_GROUP_' . $group) ?></div>
                    <?php foreach ($topics as $slug => $icon) { ?>
                        <section id="docs-<?= $slug ?>" class="ts-docs-topic mb-4" data-docs-topic="<?= $slug ?>">
                            <?php echo $this->loadTemplate($slug); ?>
                        </section>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <a href="#ts-docs-top" class="btn btn-primary ts-docs-backtotop" id="ts-docs-backtotop" hidden>
        <span class="fa fa-arrow-up" aria-hidden="true"></span><span class="visually-hidden"><?= Text::_('COM_TICKETSTATION_DOCS_BACK_TO_TOP') ?></span>
    </a>
</div>

<input name="option" type="hidden" value="com_ticketstation" />
<input name="task" type="hidden" value="" />
<input name="boxchecked" type="hidden" value="0" />
<input name="controller" type="hidden" value="docs" />
</form>
