<?php

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');

?>

<style>
    .icon-ticket-alt:before {
        content: "\f3ff";
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=seatplans'); ?>" method="post" name="adminForm" id="adminForm" class="form-inline">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <!--<pre><?php print_r($this->items);?></pre>-->
                <table class="table itemList">
                    <thead>
                    <tr>
                        <td class="w-1 text-center">
                            <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="Check All Items" onclick="Joomla.checkAll(this)" data-original-title="Check All">
                        </td>
                        <th scope="col" class="w-1 text-center"><?php echo Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                        <th scope="col" class="w-15"><?php echo Text::_( 'COM_TICKETSTATION_TICKETNAME' ); ?></th>
                        <th scope="col" class="w-10"></th>
                        <th scope="col" class="w-10"></th>
                    </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $published 	= HTMLHelper::_('grid.published', $row, $i );
                        $checked    = HTMLHelper::_('grid.id', $i, $row->ticketid );
                        $link_settings = 'index.php?option=com_ticketstation&controller=seatplans&task=editsettings&cid=' . $row->ticketid;
                        $link_seatchart = 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid=' . $row->ticketid;

                        ?>
                        <tr class="row<?= $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $row->ticketid
                                ];
                                echo (new PublishedButton)->render((int) $row->published, $i, $options);
                                ?>
                            </td>
                            <td>
                                <strong><?= $row->eventname; ?></strong><br />
                                <?= $row->ticketname; ?> <small>(<?= $row->ticketcode; ?>)</small>
                            </td>
                            <td class="text-center">
                                <a href="<?= $link_settings; ?>" title="settings" class="btn btn-primary">
                                    <?= Text::_( 'COM_TICKETSTATION_SEATPLAN_SETTINGS' ); ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="<?= $link_seatchart; ?>" title="settings" class="btn btn-primary">
                                    <?= Text::_( 'COM_TICKETSTATION_SEATPLAN_CHART' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php }  ?>
                </table>
            </div>
        </div>
    </div>


    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="seatplans"/>
    <input name = "task" type="hidden" value="" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>