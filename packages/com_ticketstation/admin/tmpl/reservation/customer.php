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
use Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$backLayout = ($this->ticket && $this->ticket->show_seatplans == 1) ? 'seatplan' : 'quantity';
$csrfTokenParam = \Joomla\CMS\Session\Session::getFormToken() . '=1';
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel&' . $csrfTokenParam) ?>">
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
        <?= LayoutHelper::render('lines', ['lines' => $this->lines, 'config' => $this->config, 'total' => true, 'return' => 'customer'], __DIR__ . '/layouts') ?>
    </div>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP3_TITLE') ?></h3>
    <div class="card-body">

        <form action="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=saveCustomer') ?>" method="post">

            <div class="row mb-3">
                <label for="name" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_NAME') ?></label>
                <div class="col-sm-9">
                    <input type="text" name="name" id="name" class="form-control" autocomplete="name" required
                           value="<?= htmlspecialchars($this->customerData['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
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
