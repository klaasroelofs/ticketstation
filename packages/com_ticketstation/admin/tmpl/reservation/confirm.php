<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

$csrfTokenParam = \Joomla\CMS\Session\Session::getFormToken() . '=1';

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$total = 0;

foreach ($this->summary as $row)
{
    $total += (float) $row->price + (float) $row->fees;
}
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel&' . $csrfTokenParam) ?>">
        <span class="icon-cancel" aria-hidden="true"></span> <?= Text::_('JTOOLBAR_CANCEL') ?>
    </a>
    <a class="btn btn-primary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel') ?>">
        <span class="icon-home" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT') ?>
    </a>
    <a class="btn btn-secondary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=reservation&layout=customer') ?>">
        <span class="icon-arrow-left" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_RESERVATION_BACK') ?>
    </a>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_CUSTOMER_TITLE') ?></h3>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= Text::_('COM_TICKETSTATION_RESERVATION_FIRSTNAME') ?></dt>
            <dd class="col-sm-9"><?= htmlspecialchars($this->customerData['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-3"><?= Text::_('COM_TICKETSTATION_RESERVATION_LASTNAME') ?></dt>
            <dd class="col-sm-9"><?= htmlspecialchars($this->customerData['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-3"><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></dt>
            <dd class="col-sm-9"><?= htmlspecialchars($this->customerData['emailaddress'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-3"><?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?></dt>
            <dd class="col-sm-9 mb-0"><?= htmlspecialchars($this->customerData['phonenumber'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </div>
</div>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP4_TITLE') ?></h3>
    <div class="card-body">

        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?= Text::_('COM_TICKETSTATION_EVENT') ?></th>
                    <th><?= Text::_('COM_TICKETSTATION_TICKETNAME') ?></th>
                    <th><?= Text::_('COM_TICKETSTATION_RESERVATION_SEAT') ?></th>
                    <th><?= Text::_('COM_TICKETSTATION_PRICES') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->summary as $row) : ?>
                <?php $seat = $row->seat_sector ? ($row->seat_row_name !== '' ? $row->seat_row_name . $row->seat_number : $row->seat_number) : ''; ?>
                <tr>
                    <td><?= htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($seat, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= TicketstationFunctions::showprice($this->config->priceformat, $row->price + $row->fees, $this->config->valuta) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3"><?= Text::_('COM_TICKETSTATION_ORDER_TOTAL') ?></th>
                    <th><?= TicketstationFunctions::showprice($this->config->priceformat, $total, $this->config->valuta) ?></th>
                </tr>
            </tfoot>
        </table>

        <form action="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=complete') ?>" method="post">

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_PAYMENT_STATUS') ?></label>
                <div class="col-sm-9">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="paid" id="paid1" value="1" checked>
                        <label class="form-check-label" for="paid1"><?= Text::_('COM_TICKETSTATION_RESERVATION_ALREADY_PAID') ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="paid" id="paid0" value="0">
                        <label class="form-check-label" for="paid0"><?= Text::_('COM_TICKETSTATION_RESERVATION_NOT_YET_PAID') ?></label>
                    </div>
                    <small class="form-text"><?= Text::_('COM_TICKETSTATION_RESERVATION_ALREADY_PAID_DESC') ?></small>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-9 offset-sm-3">
                    <input type="hidden" name="start_new" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="start_new" id="start_new" value="1" checked>
                        <label class="form-check-label" for="start_new"><?= Text::_('COM_TICKETSTATION_RESERVATION_START_NEW') ?></label>
                    </div>
                    <small class="form-text"><?= Text::_('COM_TICKETSTATION_RESERVATION_START_NEW_DESC') ?></small>
                </div>
            </div>

            <button type="submit" class="btn btn-success"><?= Text::_('COM_TICKETSTATION_RESERVATION_COMPLETE') ?></button>
            <?= HTMLHelper::_( 'form.token' ); ?>
        </form>

    </div>
</div>
