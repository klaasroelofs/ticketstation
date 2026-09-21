<?php

use Joomla\CMS\Language\Text;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel') ?>">
        <span class="icon-cancel" aria-hidden="true"></span> <?= Text::_('JTOOLBAR_CANCEL') ?>
    </a>
    <a class="btn btn-primary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel') ?>">
        <span class="icon-home" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT') ?>
    </a>
    <a class="btn btn-secondary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=reservation') ?>">
        <span class="icon-arrow-left" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_RESERVATION_BACK') ?>
    </a>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP2_TITLE') ?></h3>
    <div class="card-body">

        <p>
            <strong><?= htmlspecialchars($this->ticket->ticketname, ENT_QUOTES, 'UTF-8') ?></strong>
            &mdash; <?= TicketstationFunctions::showprice($this->config->priceformat, $this->ticket->ticketprice, $this->config->valuta) ?>
            &mdash; <?= (int) $this->ticket->totaltickets ?> <?= Text::_('COM_TICKETSTATION_AVAILABLE') ?>
        </p>

        <form action="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=addQuantity') ?>" method="post">
            <div class="row mb-3">
                <label for="amount" class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_AMOUNT') ?></label>
                <div class="col-sm-9">
                    <input type="number" name="amount" id="amount" class="form-control" style="max-width:150px;"
                           min="1" max="<?= (int) $this->ticket->totaltickets ?>" value="1" required />
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= Text::_('COM_TICKETSTATION_RESERVATION_CONTINUE') ?></button>
            <?= HTMLHelper::_( 'form.token' ); ?>
        </form>

    </div>
</div>
