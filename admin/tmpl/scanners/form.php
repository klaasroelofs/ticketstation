<?php

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
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
$document->setTitle($text.' '.Text::_('COM_TICKETSTATION_VIEW_SCANNER_TITLE') . ' - ' . $app->get('sitename'));
$editor = Editor::getInstance()

?>

<form action = "<?php echo Route::_('index.php?option=com_ticketstation&view=Scanners&task=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_SCANNING_USER_DETAILS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SCANNING_USER') ?>">
                    <?= Text::_('COM_TICKETSTATION_SCANNING_USER') ?>
                </label>
                <div class="col-sm-9">
                    <?php echo $this->lists['users']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SCANNING_TOTALS_VISIBLE') ?>">
                    <?= Text::_('COM_TICKETSTATION_SCANNING_TOTALS_VISIBLE') ?>
                </label>
                <div class="col-sm-9">
                    <?php echo $this->lists['totals_visible']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SCANNING_MANUAL_ENTRY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SCANNING_MANUAL_ENTRY') ?>
                </label>
                <div class="col-sm-9">
                    <?php echo $this->lists['manual_entry']; ?>
                </div>
            </div>
            <?php if (!empty($this->apikey)): ?>
            <div class="row mb-3">
                <label for="" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SCANNING_API_KEY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SCANNING_API_KEY') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" value="<?= htmlspecialchars($this->apikey) ?>" readonly>
                    <small class="form-text text-muted"><?= Text::_('COM_TICKETSTATION_SCANNING_API_KEY_HELP') ?></small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_SCANNING_SELECT_EVENTS_TICKETS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <table class="table" style="max-width: 370px;table-layout: fixed;overflow: hidden;">
                    <thead>
                        <th style="width:75%;"><div style="font-size:120%;">Event</div><small class="form-text"><?= Text::_('COM_TICKETSTATION_SCANNING_WARNING_EVENT') ?></small></th>
                        <th></th>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->events); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->events[$i];

                        ?>
                        <tr>
                            <td>
                                <?php echo $row->eventname; ?> (<?php echo $row->eventcode; ?>)
                            </td>
                            <td>
                                <input type="checkbox" id="ev<?php echo $i; ?>" name="event[]" value="<?php echo $row->eventid ?>" <?php if(!empty($this->data->id)){echo (in_array($row->eventid, $this->assigned_events) ? 'checked' : '');}?>>
                            </td>
                        </tr>

                    <?php } ?>


                    <thead>
                    <th><div style="font-size:120%;">Ticket</div></th>
                    <th></th>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->tickets); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->tickets[$i];

                        ?>
                        <tr>
                            <td>
                                <?php echo $row->eventcode; ?> | <?php echo $row->ticketname; ?>
                            </td>
                            <td>
                                <input type="checkbox" id="ti<?php echo $i; ?>" name="ticket[]" value="<?php echo $row->ticketid ?>" <?php if(!empty($this->data->id)){echo (in_array($row->ticketid, $this->assigned_tickets) ? 'checked' : '');}?>>
                            </td>
                        </tr>

                    <?php } ?>

                </table>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="scanners" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="id" value="<?= isset($this->data->id)?$this->data->id:null; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>
