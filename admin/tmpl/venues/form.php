<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Editor\Editor;

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
$text = empty($this->data->id) ? Text::_( 'COM_TICKETSTATION_ADD' ) : Text::_( 'COM_TICKETSTATION_EDIT' );
$document->setTitle($text.' '.Text::_('COM_TICKETSTATION_VIEW_VENUE_TITLE') . ' - ' . $app->get('sitename'));
$editor = Editor::getInstance()

?>

<form action = "<?php echo Route::_('index.php?option=com_ticketstation&view=venues&task=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_VIEW_VENUE_DETAILS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="venue" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_VENUE_NAME') ?>">
                    <?= Text::_('COM_TICKETSTATION_VENUE_NAME') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="venue" id="venue"
                           class="form-control"
                           value="<?= isset($this->data->venue)?$this->data->venue:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="street" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="street" id="street"
                           class="form-control"
                           value="<?= isset($this->data->street)?$this->data->street:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="zipcode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ZIP') ?>">
                    <?= Text::_('COM_TICKETSTATION_ZIP') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="zipcode" id="zipcode"
                           class="form-control"
                           value="<?= isset($this->data->zipcode)?$this->data->zipcode:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="city" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_CITY') ?>">
                    <?= Text::_('COM_TICKETSTATION_CITY') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="city" id="city"
                           class="form-control"
                           value="<?= isset($this->data->city)?$this->data->city:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="website" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_WEBSITE') ?>">
                    <?= Text::_('COM_TICKETSTATION_WEBSITE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="website" id="website"
                           class="form-control"
                           value="<?= isset($this->data->website)?$this->data->website:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="contact_person" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_CONTACT_PERSON') ?>">
                    <?= Text::_('COM_TICKETSTATION_CONTACT_PERSON') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="contact_person" id="contact_person"
                           class="form-control"
                           value="<?= isset($this->data->contact_person)?$this->data->contact_person:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="phonenumber" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?>">
                    <?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="phonenumber" id="phonenumber"
                           class="form-control"
                           value="<?= isset($this->data->phonenumber)?$this->data->phonenumber:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="emailaddress" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="emailaddress" id="emailaddress"
                           class="form-control"
                           value="<?= isset($this->data->emailaddress)?$this->data->emailaddress:null; ?>"/>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_VIEW_VENUE_DESCRIPTION') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <?= $editor->display( 'venuedescription',isset($this->data->venuedescription)?$this->data->venuedescription:null, '100%', '200px', '100', '10', false, 'venuedescription', 'class="form-control"' ) ;?>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="venues" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="id" value="<?= isset($this->data->id)?$this->data->id:null; ?>" />
</form>
