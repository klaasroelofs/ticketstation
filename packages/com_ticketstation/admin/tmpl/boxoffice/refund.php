<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * The refund screen of an order (see HtmlView::displayRefund()): a new refund with the amount
 * and what happens to each ticket, or, for a refund or chargeback Mollie reported, only the
 * decision about the tickets.
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app = Factory::getApplication();
$app->getDocument()->setTitle(Text::_($this->refund ? 'COM_TICKETSTATION_REFUND_DECIDE_TITLE' : 'COM_TICKETSTATION_REFUND_TITLE') . ' - ' . $app->get('sitename'));
$app->getDocument()->getWebAssetManager()->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

$valuta   = $this->escape($this->config->valuta);
$money    = static fn ($amount) => Price::format((float) $amount, '');
$refund   = $this->refund;
$datetime = $this->config->dateformat . ' ' . $this->config->time_format;

$treatments = [
    Refund::TICKET_UNCHANGED => 'COM_TICKETSTATION_REFUND_TICKET_UNCHANGED',
    Refund::TICKET_VALID     => 'COM_TICKETSTATION_REFUND_TICKET_VALID',
    Refund::TICKET_INVALID   => 'COM_TICKETSTATION_REFUND_TICKET_INVALID',
    Refund::TICKET_RELEASED  => 'COM_TICKETSTATION_REFUND_TICKET_RELEASED',
];

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_ORDER_INFORMATION') ?></h3>
            <div class="card-body">
                <table class="table mb-0">
                    <tr>
                        <th scope="row" class="w-50 fw-normal"><?= Text::_('COM_TICKETSTATION_ORDERCODE') ?></th>
                        <td><a href="index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=<?= (int) $this->items->ordercode; ?>"><strong><?= $this->escape($this->items->ordercode); ?></strong></a></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_NAME') ?></th>
                        <td><?= $this->escape(trim($this->items->name)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_REFUND_PAID') ?></th>
                        <td><?= Price::format($this->paidAmount, $valuta); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_REFUND_REFUNDED') ?></th>
                        <td><?= Price::format($this->refunded, $valuta); ?></td>
                    </tr>
                    <?php if (!$refund) { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_REFUND_REMAINING') ?></th>
                            <td><strong><?= Price::format($this->remaining, $valuta); ?></strong></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_SERVICE_FEE') ?></th>
                        <td><?= Price::format($this->totals->fees, $valuta); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <?php if ($refund) { ?>
                <h3 class="card-header"><?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_REPORTED_CHARGEBACK' : 'COM_TICKETSTATION_REFUND_REPORTED_REFUND') ?></h3>
                <div class="card-body">
                    <p><?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_DECIDE_CHARGEBACK_INTRO' : 'COM_TICKETSTATION_REFUND_DECIDE_INTRO') ?></p>
                    <table class="table mb-0">
                        <tr>
                            <th scope="row" class="w-50 fw-normal"><?= Text::_('COM_TICKETSTATION_REFUND_AMOUNT') ?></th>
                            <td><strong><?= Price::format($refund->amount, $valuta); ?></strong></td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_DATE') ?></th>
                            <td><?= Date::screen($refund->created, $datetime); ?></td>
                        </tr>
                        <?php if ($refund->description !== '') { ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_REASON' : 'COM_TICKETSTATION_REFUND_DESCRIPTION') ?></th>
                                <td><?= $this->escape($refund->description); ?></td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_REFUND_MOLLIE_ID') ?></th>
                            <td><code><?= $this->escape((string) $refund->provider_refund_id); ?></code></td>
                        </tr>
                    </table>
                    <?php if ($this->proposal) { ?>
                        <div class="alert alert-info mt-3 mb-0"><?= Text::_('COM_TICKETSTATION_REFUND_PROPOSAL') ?></div>
                    <?php } else { ?>
                        <div class="alert alert-warning mt-3 mb-0"><?= Text::_('COM_TICKETSTATION_REFUND_NO_PROPOSAL') ?></div>
                    <?php } ?>
                    <input type="hidden" name="refund_id" value="<?= (int) $refund->id; ?>" />
                </div>
            <?php } else { ?>
                <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_REFUND_TITLE') ?></h3>
                <div class="card-body">
                    <p>
                        <?= Text::_($this->providerPayment !== '' ? 'COM_TICKETSTATION_REFUND_INTRO_MOLLIE' : 'COM_TICKETSTATION_REFUND_INTRO_MANUAL') ?>
                    </p>
                    <div class="mb-3">
                        <label for="ts-refund-amount" class="form-label"><?= Text::_('COM_TICKETSTATION_REFUND_AMOUNT') ?></label>
                        <div class="input-group" style="max-width: 16rem;">
                            <span class="input-group-text"><?= $valuta; ?></span>
                            <input type="text" inputmode="decimal" class="form-control" name="amount" id="ts-refund-amount" value="" autocomplete="off" required
                                   data-max="<?= number_format($this->remaining, 2, '.', ''); ?>">
                        </div>
                        <div class="form-text"><?= Text::sprintf('COM_TICKETSTATION_REFUND_AMOUNT_DESC', $valuta . ' ' . $money($this->remaining)); ?></div>
                    </div>
                    <?php if ((float) $this->totals->fees > 0) { ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="ts-refund-fee" data-fee="<?= number_format((float) $this->totals->fees, 2, '.', ''); ?>">
                            <label class="form-check-label" for="ts-refund-fee"><?= Text::sprintf('COM_TICKETSTATION_REFUND_INCLUDE_FEE', $valuta . ' ' . $money($this->totals->fees)); ?></label>
                        </div>
                    <?php } ?>
                    <div class="mb-0">
                        <label for="ts-refund-description" class="form-label"><?= Text::_('COM_TICKETSTATION_REFUND_DESCRIPTION') ?></label>
                        <input type="text" class="form-control" name="description" id="ts-refund-description" maxlength="140"
                               placeholder="<?= $this->escape(Text::sprintf('COM_TICKETSTATION_REFUND_DEFAULT_DESCRIPTION', $this->items->ordercode)); ?>">
                        <?php if ($this->providerPayment !== '') { ?>
                            <div class="form-text"><?= Text::_('COM_TICKETSTATION_REFUND_DESCRIPTION_DESC') ?></div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_REFUND_TICKETS') ?></h3>
    <div class="card-body">
        <p><?= Text::_('COM_TICKETSTATION_REFUND_TICKETS_INTRO') ?></p>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <label for="ts-refund-all" class="mb-0"><?= Text::_('COM_TICKETSTATION_REFUND_SET_ALL') ?></label>
            <select id="ts-refund-all" class="form-select w-auto">
                <option value=""><?= Text::_('COM_TICKETSTATION_REFUND_SET_ALL_CHOOSE') ?></option>
                <?php foreach ($treatments as $value => $label) { ?>
                    <option value="<?= $value; ?>"><?= Text::_($label); ?></option>
                <?php } ?>
            </select>
        </div>

        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th scope="col" class="w-10"><?= Text::_('COM_TICKETSTATION_TICKET_ID'); ?></th>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_TICKET'); ?></th>
                    <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_REFUND_TICKET_PRICE'); ?></th>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_TREATMENT'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->data as $row) {
                $state    = (int) $row->refund_state;
                $price    = round((float) $row->price - (float) $row->discount, 2);
                $selected = in_array((int) $row->orderid, $this->proposal, true) ? Refund::TICKET_RELEASED : Refund::TICKET_UNCHANGED;
                ?>
                <tr>
                    <td><?= (int) $row->orderid; ?></td>
                    <td>
                        <?= $this->escape($row->eventname); ?> &middot; <?= $this->escape($row->ticketname); ?>
                        <?php if ($row->seat_sector != 0 && $row->seatid) { ?>
                            <br /><small><?= Text::_('COM_TICKETSTATION_SEAT'); ?>: <?= $this->escape($row->row_name . $row->seatid); ?></small>
                        <?php } ?>
                        <?php if ((int) $row->scanned === 1) { ?>
                            <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_SCANNED'); ?></span>
                        <?php } ?>
                        <?php if ($state !== Refund::TICKET_UNCHANGED) { ?>
                            <br /><span class="badge bg-info"><?= Text::_('COM_TICKETSTATION_REFUND_STATE_' . $state); ?></span>
                        <?php } ?>
                        <?php if (isset($this->waitingTreatments[(int) $row->orderid])) { ?>
                            <br /><span class="badge bg-warning text-dark"><span class="fa fa-hourglass-half" aria-hidden="true"></span>
                                <?= Text::sprintf('COM_TICKETSTATION_REFUND_WAITING_TICKET', Text::_('COM_TICKETSTATION_REFUND_STATE_' . $this->waitingTreatments[(int) $row->orderid])); ?></span>
                        <?php } ?>
                    </td>
                    <td class="text-end text-nowrap"><?= Price::format($price, $valuta); ?></td>
                    <td>
                        <?php if ($state === Refund::TICKET_RELEASED) { ?>
                            <span class="text-muted"><?= Text::_('COM_TICKETSTATION_REFUND_RELEASED_FINAL'); ?></span>
                        <?php } else { ?>
                            <select name="treatment[<?= (int) $row->orderid; ?>]" class="form-select form-select-sm ts-refund-treatment"
                                    data-price="<?= number_format($price, 2, '.', ''); ?>" data-refunded="<?= $state !== Refund::TICKET_UNCHANGED || isset($this->waitingTreatments[(int) $row->orderid]) ? 1 : 0; ?>"
                                    aria-label="<?= Text::_('COM_TICKETSTATION_REFUND_TREATMENT'); ?> <?= (int) $row->orderid; ?>">
                                <?php foreach ($treatments as $value => $label) { ?>
                                    <option value="<?= $value; ?>" <?= $value === $selected ? 'selected' : ''; ?>><?= Text::_($value === Refund::TICKET_UNCHANGED && $state !== Refund::TICKET_UNCHANGED ? 'COM_TICKETSTATION_REFUND_TICKET_KEEP' : $label); ?></option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php // What each choice does, next to the choices. ?>
        <ul class="small text-muted mb-0">
            <li><?= Text::_('COM_TICKETSTATION_REFUND_TICKET_VALID_DESC') ?></li>
            <li><?= Text::_('COM_TICKETSTATION_REFUND_TICKET_INVALID_DESC') ?></li>
            <li><?= Text::_('COM_TICKETSTATION_REFUND_TICKET_RELEASED_DESC') ?></li>
        </ul>
    </div>
</div>

<input name="ordercode" type="hidden" value="<?= (int) $this->items->ordercode; ?>" />
<input name="cid[]" type="hidden" value="<?= (int) $this->items->ordercode; ?>" />
<input name="option" type="hidden" value="com_ticketstation" />
<input name="task" type="hidden" value="" />
<input name="controller" type="hidden" value="boxoffice"/>
<?= HTMLHelper::_('form.token'); ?>

</form>

<script>
    (function () {
        var selects = document.querySelectorAll('.ts-refund-treatment');
        var all     = document.getElementById('ts-refund-all');
        var amount  = document.getElementById('ts-refund-amount');
        var fee     = document.getElementById('ts-refund-fee');
        var touched = false;

        // The amount follows the tickets that are refunded now (not those refunded before),
        // plus the service fee when ticked, until the admin types an amount of their own.
        function suggest() {
            if (!amount || touched) {
                return;
            }

            var total = 0;

            selects.forEach(function (select) {
                if (select.value !== '0' && select.dataset.refunded === '0') {
                    total += parseFloat(select.dataset.price);
                }
            });

            if (fee && fee.checked) {
                total += parseFloat(fee.dataset.fee);
            }

            total = Math.min(total, parseFloat(amount.dataset.max));
            amount.value = total > 0 ? total.toFixed(2).replace('.', ',') : '';
        }

        all.addEventListener('change', function () {
            if (all.value === '') {
                return;
            }

            selects.forEach(function (select) {
                select.value = all.value;
            });

            all.value = '';
            suggest();
        });

        selects.forEach(function (select) {
            select.addEventListener('change', suggest);
        });

        if (fee) {
            fee.addEventListener('change', suggest);
        }

        if (amount) {
            amount.addEventListener('input', function () {
                touched = amount.value.trim() !== '';
            });
        }

        Joomla.submitbutton = function (task) {
            if (task === 'saverefund' && amount && amount.value.trim() === '') {
                amount.focus();
                amount.reportValidity();
                return;
            }

            Joomla.submitform(task, document.getElementById('adminForm'));
        };
    })();
</script>
