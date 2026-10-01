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
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$csrfTokenParam = \Joomla\CMS\Session\Session::getFormToken() . '=1';

$total = 0;

foreach ($this->summary as $row)
{
    $total += (float) $row->price + (float) $row->fees;
}

// A No/Yes switch set to Yes, with the markup of Joomla's own joomla.form.field.radio.switcher
// layout so it looks like the switches in Joomla's forms. A switch that depends on the one above
// it is indented one step per level: label, switch and description.
Factory::getApplication()->getDocument()->getWebAssetManager()->useStyle('switcher');

$switcher = function (string $name, string $label, string $description, int $level = 0): void
{
    $indent = $level ? ' style="margin-inline-start: ' . (1.5 * $level) . 'rem"' : '';
    ?>
    <div class="row mb-3" id="<?= $name ?>-row">
        <div class="col-sm-3 col-form-label" id="<?= $name ?>-lbl"><span class="d-block"<?= $indent ?>><?= Text::_($label) ?></span></div>
        <div class="col-sm-9">
            <div<?= $indent ?>>
                <fieldset id="<?= $name ?>" aria-labelledby="<?= $name ?>-lbl">
                    <div class="switcher">
                        <input type="radio" id="<?= $name ?>0" name="<?= $name ?>" value="0">
                        <label for="<?= $name ?>0"><?= Text::_('JNO') ?></label>
                        <input type="radio" id="<?= $name ?>1" name="<?= $name ?>" value="1" checked class="active">
                        <label for="<?= $name ?>1"><?= Text::_('JYES') ?></label>
                        <span class="toggle-outside"><span class="toggle-inside"></span></span>
                    </div>
                </fieldset>
                <small class="form-text"><?= Text::_($description) ?></small>
            </div>
        </div>
    </div>
    <?php
};
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

            <?php if (!AclGate::can('ticketstation.payment')) : ?>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_PAYMENT_STATUS') ?></label>
                <div class="col-sm-9">
                    <input type="hidden" name="paid" value="0" />
                    <p class="form-control-plaintext"><?= Text::_('COM_TICKETSTATION_RESERVATION_NOT_YET_PAID') ?></p>
                    <small class="form-text"><?= Text::_('COM_TICKETSTATION_RESERVATION_PAID_NOT_ALLOWED') ?></small>
                </div>
            </div>
            <?php else : ?>
                <?php $switcher('paid', 'COM_TICKETSTATION_RESERVATION_ALREADY_PAID', 'COM_TICKETSTATION_RESERVATION_ALREADY_PAID_DESC'); ?>
            <?php endif; ?>

            <?php $switcher('start_new', 'COM_TICKETSTATION_RESERVATION_START_NEW', 'COM_TICKETSTATION_RESERVATION_START_NEW_DESC'); ?>
            <?php $switcher('same_event', 'COM_TICKETSTATION_RESERVATION_SAME_EVENT', 'COM_TICKETSTATION_RESERVATION_SAME_EVENT_DESC', 1); ?>
            <?php $switcher('same_ticket', 'COM_TICKETSTATION_RESERVATION_SAME_TICKET', 'COM_TICKETSTATION_RESERVATION_SAME_TICKET_DESC', 2); ?>

            <button type="submit" class="btn btn-success"><?= Text::_('COM_TICKETSTATION_RESERVATION_COMPLETE') ?></button>
            <?= HTMLHelper::_( 'form.token' ); ?>
        </form>

    </div>
</div>

<script>
    // "For the same event" only applies to a new reservation, "For the same ticket" only to one
    // for the same event: hide each while the switch above it is off.
    (function () {
        var value = function (name) {
            var checked = document.querySelector('input[name="' + name + '"]:checked');
            return checked ? checked.value : '0';
        };
        var toggle = function () {
            var startNew = value('start_new') === '1';
            document.getElementById('same_event-row').hidden = !startNew;
            document.getElementById('same_ticket-row').hidden = !startNew || value('same_event') !== '1';
        };

        ['start_new', 'same_event'].forEach(function (name) {
            document.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
                input.addEventListener('change', toggle);
            });
        });

        toggle();
    })();
</script>
