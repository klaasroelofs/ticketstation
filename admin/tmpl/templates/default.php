<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=templates'); ?>" method="POST" name="adminForm" id="adminForm" enctype="multipart/form-data">
    <?php // Main area ?>

    <div class="row">
        <?php // LEFT COLUMN (66% desktop width) ?>

        <?php

            for ($i = 0, $n = count($this->items); $i < $n; $i++ ) {

            $row        = $this->items[$i];
            $link		= 'index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . $row->mailid;

        ?>
            <div class="col col-lg-6">
                <div class="card mb-2">
                    <h3 class="card-header bg-primary text-white">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_FOR') ?>&nbsp;<i style="color: yellow;"><?= $row->alias; ?></i>
                    </h3>
                    <div class="card-body">
                        <div class="subhead mb-3 shadow-sm" style="position: relative; z-index: 100;">
                            <div class="row">
                                <div class="col-md-12">
                                    <nav aria-label="Toolbar">
                                        <div class="btn-toolbar d-flex" role="toolbar" id="toolbar">
                                            <joomla-toolbar-button id="toolbar-edit" task="">
                                                <button class="button-edit btn btn-primary" type="button" onClick="location.href='<?= $link; ?>'">
                                                    <span class="icon-edit" aria-hidden="true"></span>
                                                    Edit
                                                </button>
                                            </joomla-toolbar-button>
                                        </div>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-3 templates-title">
                            <?= Text::_('COM_TICKETSTATION_TEMPLATE_FROM_NAME') ?>
                        </div>
                        <div class="col-sm-9 templates-content">
                            <code><?= $row->from_name; ?></code>
                        </div>
                        <hr/>
                        <div class="col-sm-3 templates-title">
                            <?= Text::_('COM_TICKETSTATION_TEMPLATE_FROM_MAILADDRESS') ?>
                        </div>
                        <div class="col-sm-9 templates-content">
                            <code><?= $row->from_email; ?></code>
                        </div>
                        <hr/>
                        <div class="col-sm-3 templates-title">
                            <?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT') ?>
                        </div>
                        <div class="col-sm-9 templates-content">
                            <code><?= $row->mailsubject; ?></code>
                        </div>
                        <hr/>
                        <div class="col-sm-3 templates-title">
                            <?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILBODY') ?>
                        </div>
                        <div class="col-sm-9 templates-content">
                            <code><?= $row->mailbody; ?></code>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>


    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="templates"/>
    <input name = "task" type="hidden" value="" />
</form>