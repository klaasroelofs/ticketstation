<?php

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
 *
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();

$document->setTitle(Text::_( 'COM_TICKETSTATION_EDIT').' '.Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_SETTINGS') . ' - ' . $app->get('sitename'));

## Seatchart Image

$background_img_seatchart = false;

$seatchart_png = Uri::root() . 'administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->item->ticketid.'.png';
$image_png = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts/seatchart' . $this->item->ticketid .'.png';
$seatchart_jpg = Uri::root() . 'administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->item->ticketid.'.jpg';
$image_jpg = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts/seatchart' . $this->item->ticketid . '.jpg';

if (file_exists($image_png)) {
    $background_img = $seatchart_png;
    $background_img_seatchart = true;
} elseif (file_exists($image_jpg)) {
    $background_img = $seatchart_jpg;
    $background_img_seatchart = true;
}


?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=seatplansettings&layout=default'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <?php if ($background_img_seatchart) { ?>

                    <?php $remove_link_bg = 'index.php?option=com_ticketstation&controller=seatplansettings&task=removeBackground&ticketid='.$this->item->ticketid; ?>

                    <div class="control-group">
                        <div class="control-label">
                            <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_BACKGROUND_UPCOMING' ); ?></label>
                        </div>
                        <div class="controls">
                            <a href="<?= $background_img; ?>" target="blank" class="btn btn-secondary">
                                <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_SEATCHART_IMAGE' ); ?></a>
                            <a href="<?= $remove_link_bg; ?>" class="btn btn-secondary">
                                <i class="icon-trash"></i>  <?= Text::_( 'COM_TICKETSTATION_REMOVE_SEATCHART_IMAGE' ); ?></a>
                        </div>
                    </div>

                <?php } ?>
            </div>

            <div class="row mb-3">
                <?= $this->form->renderFieldset('general'); ?>
            </div>

        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="seatplansettings" />
    <input type="hidden" name="task" value="" />

</form>


