<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$csrfTokenParam = \Joomla\CMS\Session\Session::getFormToken() . '=1';
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel&' . $csrfTokenParam) ?>">
        <span class="icon-cancel" aria-hidden="true"></span> <?= Text::_('JTOOLBAR_CANCEL') ?>
    </a>
    <a class="btn btn-primary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel') ?>">
        <span class="icon-home" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT') ?>
    </a>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP1_TITLE') ?></h3>
    <div class="card-body">

        <form action="<?= Route::_('index.php?option=com_ticketstation&view=reservation') ?>" method="get" id="event-form">
            <input type="hidden" name="option" value="com_ticketstation" />
            <input type="hidden" name="view" value="reservation" />

            <div class="row mb-3">
                <label for="eventid" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_SELECTLIST_EVENT') ?></label>
                <div class="col-sm-9">
                    <select name="eventid" id="eventid" class="form-select" onchange="this.form.submit();">
                        <option value="0"><?= Text::_('COM_TICKETSTATION_SELECTLIST_EVENT') ?></option>
                        <?php foreach ($this->events as $event) : ?>
                            <option value="<?= (int) $event->eventid ?>" <?= $this->eventid == $event->eventid ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event->eventname, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($this->eventid) : ?>

            <?php if (empty($this->tickets)) : ?>
                <p><?= Text::_('COM_TICKETSTATION_RESERVATION_NO_TICKETS_FOR_EVENT') ?></p>
            <?php else : ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= Text::_('COM_TICKETSTATION_TICKETNAME') ?></th>
                            <th></th>
                            <th><?= Text::_('COM_TICKETSTATION_PRICES') ?></th>
                            <th><?= Text::_('COM_TICKETSTATION_AVAILABLE') ?></th>
                            <th><?= Text::_('COM_TICKETSTATION_RESERVATION_SEATPLAN') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->tickets as $ticket) : ?>
                        <tr>
                            <td><?= htmlspecialchars($ticket->ticketname, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form action="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=selectTicket') ?>" method="post">
                                    <input type="hidden" name="ticketid" value="<?= (int) $ticket->ticketid ?>" />
                                    <button type="submit" class="btn btn-primary" <?= $ticket->totaltickets < 1 ? 'disabled' : '' ?>>
                                        <?= Text::_('COM_TICKETSTATION_RESERVATION_SELECT_TICKET') ?>
                                    </button>
                                    <?= HTMLHelper::_( 'form.token' ); ?>
                                </form>
                            </td>
                            <td><?= TicketstationFunctions::showprice($this->config->priceformat, $ticket->ticketprice, $this->config->valuta) ?></td>
                            <td><?= (int) $ticket->totaltickets ?></td>
                            <td><?= $ticket->show_seatplans == 1 ? Text::_('JYES') : Text::_('JNO') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>
