<?php

use Joomla\CMS\Language\Text;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$backLayout = ($this->ticket && $this->ticket->show_seatplans == 1) ? 'seatplan' : 'quantity';
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel') ?>">
        <span class="icon-cancel" aria-hidden="true"></span> <?= Text::_('JTOOLBAR_CANCEL') ?>
    </a>
    <a class="btn btn-primary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel') ?>">
        <span class="icon-home" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT') ?>
    </a>
    <a class="btn btn-secondary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=reservation&layout=' . $backLayout) ?>">
        <span class="icon-arrow-left" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_RESERVATION_BACK') ?>
    </a>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_CART_TITLE') ?></h3>
    <div class="card-body">
        <ul>
            <?php foreach ($this->summary as $row) : ?>
                <?php $seat = $row->seat_sector ? ($row->seat_row_name !== '' ? $row->seat_row_name . $row->seat_number : $row->seat_number) : null; ?>
                <li>
                    <?= htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8') ?>
                    &mdash; <?= htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($seat !== null) : ?>
                        &mdash; <?= Text::_('COM_TICKETSTATION_RESERVATION_SEAT') ?> <?= htmlspecialchars($seat, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p><?= count($this->summary) ?> <?= Text::_('COM_TICKETSTATION_RESERVATION_TICKETS_IN_CART') ?></p>
    </div>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP3_TITLE') ?></h3>
    <div class="card-body">

        <form action="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=saveCustomer') ?>" method="post">

            <div class="row mb-3">
                <label for="firstname" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_FIRSTNAME') ?></label>
                <div class="col-sm-9">
                    <input type="text" name="firstname" id="firstname" class="form-control" autocomplete="given-name"
                           value="<?= htmlspecialchars($this->customerData['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                </div>
            </div>

            <div class="row mb-3">
                <label for="lastname" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_LASTNAME') ?></label>
                <div class="col-sm-9">
                    <input type="text" name="lastname" id="lastname" class="form-control" autocomplete="family-name" required
                           value="<?= htmlspecialchars($this->customerData['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                </div>
            </div>

            <div class="row mb-3">
                <label for="emailaddress" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></label>
                <div class="col-sm-9">
                    <input type="email" name="emailaddress" id="emailaddress" class="form-control" autocomplete="email" required
                           value="<?= htmlspecialchars($this->customerData['emailaddress'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                </div>
            </div>

            <div class="row mb-3">
                <label for="phonenumber" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?></label>
                <div class="col-sm-9">
                    <input type="text" name="phonenumber" id="phonenumber" class="form-control" autocomplete="tel"
                           value="<?= htmlspecialchars($this->customerData['phonenumber'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><?= Text::_('COM_TICKETSTATION_RESERVATION_CONTINUE') ?></button>
            <?= HTMLHelper::_( 'form.token' ); ?>
        </form>

    </div>
</div>
