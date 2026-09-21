<?php

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
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

$add_edit = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
$event_name = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_VIEW_EVENT_TITLE') : $this->item->eventname . ' (' . $this->item->eventcode . ')';
$document->setTitle($add_edit . ' ' . $event_name);

if(isset($this->item->eventid))
{

    ## Background Image (Upcoming Events frontend)
    $background_event   = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $this->item->eventid . '.jpg';
    $background_img_event = false;

    if (file_exists($background_event)) {
        $background_img = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/event'.$this->item->eventid.'.jpg';
        $background_img_event = true;
    }

}

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=event&layout=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <?= $this->form->renderField('eventname'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventcode'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventdate'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('automatic_change_state'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('startdate'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('closingdate'); ?>
            </div>
            <div class="row mb-3">
                <?php if ((isset($this->item->eventid)?$this->item->eventid:0 > 0) && ($background_img_event)) { ?>

                    <?php $remove_link_bg = 'index.php?option=com_ticketstation&controller=event&task=removeBackground&eventid='.$this->item->eventid.'&'.\Joomla\CMS\Session\Session::getFormToken().'=1'; ?>

                    <div class="control-group">
                        <div class="control-label">
                            <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_BACKGROUND_UPCOMING' ); ?></label>
                        </div>
                        <div class="controls">
                            <a href="<?= $background_img; ?>" target="blank" class="btn btn-secondary">
                                <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_BACKGROUND_UPCOMING' ); ?></a>
                            <a href="<?= $remove_link_bg; ?>" class="btn btn-secondary">
                                <i class="icon-trash"></i>  <?= Text::_( 'COM_TICKETSTATION_REMOVE_BACKGROUND_UPCOMING' ); ?></a>
                        </div>
                    </div>

                <?php } ?>
            </div>
            <div>
                <?= $this->form->renderField('backgroundupcomingfile'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventdescription'); ?>
            </div>
            <div>
                <?= $this->form->renderField('eventid'); ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation"/>
    <input type="hidden" name="controller" value="event"/>
    <input type="hidden" name="task" value=""/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>
