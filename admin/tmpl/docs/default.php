<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * Central documentation hub: a shell page that loads one sub-template per topic
 * (default_<topic>.php). Add a new topic by dropping a default_<topic>.php file
 * here and adding it to $topics below - no other wiring needed.
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_DOCS_TITLE') . ' - ' . $app->get('sitename'));

$topics = [
    'events'        => ['icon' => 'fa-calendar-alt',   'title' => 'COM_TICKETSTATION_DOCS_NAV_EVENTS'],
    'configuration' => ['icon' => 'fa-cog',             'title' => 'COM_TICKETSTATION_DOCS_NAV_CONFIGURATION'],
    'scanning'      => ['icon' => 'fa-qrcode',          'title' => 'COM_TICKETSTATION_DOCS_NAV_SCANNING'],
    'invoicing'     => ['icon' => 'fa-file-invoice',    'title' => 'COM_TICKETSTATION_DOCS_NAV_INVOICING'],
    'waitinglist'   => ['icon' => 'fa-hourglass-half',  'title' => 'COM_TICKETSTATION_DOCS_NAV_WAITINGLIST'],
];
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=docs'); ?>" method="post" name="adminForm" id="adminForm">
<div class="ticketstation-docs">
    <p class="lead"><?= Text::_('COM_TICKETSTATION_DOCS_INTRO') ?></p>

    <nav class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-row flex-wrap gap-2">
                <?php foreach ($topics as $slug => $topic) { ?>
                    <a class="btn btn-outline-primary btn-sm" href="#docs-<?= $slug ?>">
                        <span class="fa <?= $topic['icon'] ?> me-1" aria-hidden="true"></span><?= Text::_($topic['title']) ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </nav>

    <?php foreach ($topics as $slug => $topic) { ?>
        <section id="docs-<?= $slug ?>" class="mb-4">
            <?php echo $this->loadTemplate($slug); ?>
        </section>
    <?php } ?>
</div>

<input name="option" type="hidden" value="com_ticketstation" />
<input name="task" type="hidden" value="" />
<input name="boxchecked" type="hidden" value="0" />
<input name="controller" type="hidden" value="docs" />
</form>
