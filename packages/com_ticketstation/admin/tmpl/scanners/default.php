<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_SCANNERS_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=Scanners'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                            </td>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_SCANNING_USER' ); ?></th>
                            <th scope="col" class="d-lg-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_SCANNING_COUNT_EVENTS_TICKETS' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $checked    = HTMLHelper::_('grid.id', $i, $row->id );
                        $link		= 'index.php?option=com_ticketstation&controller=scanners&task=edit&cid=' . $row->id;
                        $assigned   = count(Scanner::idList($row->tickets)) + count(Scanner::idList($row->events));

                        ?>
                        <tr class="row<?php echo $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td><div align="left"><a href="<?php echo $link; ?>"><?php echo $this->escape($row->name); ?></a></div></td>
                            <td>
                                <div align="left">
                                    <?php if ($assigned > 0): ?>
                                        <span class="label label-success"><?php echo $assigned; ?></span>
                                    <?php else: ?>
                                        <span class="label label-important"><?php echo Text::_('JNONE'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php }  ?>
                </table>

                <?php // load the pagination. ?>
                <?php echo $this->pagination->getListFooter(); ?>

                <p class="mt-3">
                    <a class="btn btn-outline-secondary btn-sm" href="index.php?option=com_ticketstation&view=docs#docs-scanning">
                        <span class="fa fa-book me-1" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_SCANNING_DOCS_LINK'); ?>
                    </a>
                </p>

            </div>
        </div>
    </div>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="scanners"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "limitstart" type="hidden" value="<?php echo $this->pagination->limitstart; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>