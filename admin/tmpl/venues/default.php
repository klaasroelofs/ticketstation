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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_VENUES_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=Venues'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="Check All Items" onclick="Joomla.checkAll(this)" data-original-title="Check All">
                            </td>
                            <th scope="col" class="w-1 text-center"><?php echo Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_VENUE_NAME' ); ?></th>
                            <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_ADDRESS' ); ?></th>
                            <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_ZIPCITY' ); ?></th>
                            <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_WEBSITE' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $published 	= HTMLHelper::_('grid.published', $row, $i );
                        $checked    = HTMLHelper::_('grid.id', $i, $row->id );
                        $link		= 'index.php?option=com_ticketstation&controller=venues&task=edit&cid=' . $row->id;

                        ?>
                        <tr class="row<?php echo $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $row->id
                                ];
                                echo (new PublishedButton)->render((int) $row->published, $i, $options);
                                ?>
                            </td>
                            <td><a href="<?php echo $link; ?>"><?php echo $row->venue; ?></a></td>
                            <td class="small d-none d-lg-table-cell"><?php echo $row->street; ?></td>
                            <td class="small d-none d-md-table-cell"><?php echo $row->zipcode; ?> <?php echo $row->city; ?></td>
                            <td class="small d-none d-lg-table-cell"><a target="blank" href="https://<?php echo $row->website; ?>"><?php echo $row->website; ?></a></td>
                        </tr>
                    <?php }  ?>
                </table>
            </div>
        </div>
    </div>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="venues"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "limitstart" type="hidden" value="<?php echo $this->pagination->limitstart; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>

<table width="100%" border="0" align="center" cellpadding="1" cellspacing="1">
    <tr>
        <td>
            <div align="center"><?php echo $this->pagination->getPagesLinks(); ?></div>
        </td>
    </tr>
</table>