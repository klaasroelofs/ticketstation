<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Editor\Editor;
use Ticketstation\Component\Ticketstation\Administrator\Helper\YesNoSwitch;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$text = empty($this->data->id) ? Text::_( 'COM_TICKETSTATION_ADD' ) : Text::_( 'COM_TICKETSTATION_EDIT' );
$document->setTitle($text.' '.Text::_('COM_TICKETSTATION_VIEW_SCANNER_TITLE') . ' - ' . $app->get('sitename'));
$editor = Editor::getInstance();

// Saving needs a user (see HtmlView); data-cancel-task keeps Cancel free of that check.
HTMLHelper::_('behavior.formvalidator');

?>

<form action = "<?php echo Route::_('index.php?option=com_ticketstation&view=Scanners&task=edit'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate" data-cancel-task="cancel" enctype="multipart/form-data">

    <div class="card">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_SCANNING_USER_DETAILS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="userid" class="col-sm-3 col-form-label"
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
            <style>
                /* Joomla gives the switch a fixed width of 18rem; shrink it so it fits the list. */
                #ts-scanner-list .switcher { width: 7rem; }
                #ts-scanner-list .switcher label { min-width: 2.5rem; }
            </style>
            <div class="row mb-3">
                <table class="table" id="ts-scanner-list" style="max-width: 560px;">
                    <thead>
                        <th><div style="font-size:120%;"><?= Text::_('COM_TICKETSTATION_EVENT'); ?></div><small class="form-text"><?= Text::_('COM_TICKETSTATION_SCANNING_WARNING_EVENT') ?></small></th>
                        <th style="width: 8rem;"></th>
                    </thead>
                    <?php foreach ($this->events as $row) { ?>
                        <tr>
                            <td>
                                <?= $this->escape($row->eventname); ?> (<?= $this->escape($row->eventcode); ?>)
                            </td>
                            <td>
                                <?= YesNoSwitch::render('ev' . (int) $row->eventid, 'event[' . (int) $row->eventid . ']', $row->eventname, in_array((int) $row->eventid, $this->assigned_events, true)); ?>
                            </td>
                        </tr>
                    <?php } ?>

                    <thead>
                    <th><div style="font-size:120%;"><?= Text::_('COM_TICKETSTATION_TICKET'); ?></div><small class="form-text"><?= Text::_('COM_TICKETSTATION_SCANNING_NOTE_PARENT_TICKET') ?></small></th>
                    <th></th>
                    </thead>
                    <?php foreach ($this->tickets as $row) { ?>
                        <?php
                        $ticketid = (int) $row->ticketid;
                        $onNow    = in_array($ticketid, $this->assigned_tickets, true);

                        // A child ticket follows its parent: it has no switch, only a note that shows while its parent is on.
                        // A child that was assigned on its own before, without its parent, keeps its switch so that
                        // setting isn't dropped unseen.
                        $parentOn      = $row->child && in_array((int) $row->parent, $this->assigned_tickets, true);
                        $followsParent = $row->child && (!$onNow || $parentOn);
                        ?>
                        <tr>
                            <?php // A child ticket sits indented below its parent, as in the Tickets list ?>
                            <td<?= $row->child ? ' class="ps-4"' : ''; ?>>
                                <?php if ($row->child) { ?>
                                    &ndash; <?= $this->escape($row->ticketname); ?>
                                <?php } else { ?>
                                    <?= $this->escape($row->eventcode); ?> | <?= $row->parentname ? $this->escape($row->parentname) . ' &ndash; ' : ''; ?><?= $this->escape($row->ticketname); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($followsParent) { ?>
                                    <span class="badge bg-success ts-child-included" data-parent="ti<?= (int) $row->parent; ?>"<?= $parentOn ? '' : ' hidden'; ?>><?= Text::_('COM_TICKETSTATION_SCANNING_CHILD_INCLUDED'); ?></span>
                                <?php } else { ?>
                                    <?= YesNoSwitch::render('ti' . $ticketid, 'ticket[' . $ticketid . ']', $row->ticketname, $onNow); ?>
                                    <?php if ($row->child) { ?>
                                        <small class="form-text d-block"><?= Text::_('COM_TICKETSTATION_SCANNING_CHILD_SEPARATE'); ?></small>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                        </tr>

                    <?php } ?>

                </table>
            </div>
            <script>
                // The note of a child ticket shows while its parent is switched on
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.ts-child-included').forEach(function (note) {
                        var parent = document.getElementById(note.dataset.parent);

                        if (parent) {
                            parent.addEventListener('change', function () {
                                var checked = parent.querySelector('input:checked');
                                note.hidden = !checked || checked.value !== '1';
                            });
                        }
                    });
                });
            </script>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="scanners" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="id" value="<?= isset($this->data->id)?$this->data->id:null; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>
