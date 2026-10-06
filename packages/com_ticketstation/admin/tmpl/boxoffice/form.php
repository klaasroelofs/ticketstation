<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS') . ' - ' . $app->get('sitename'));

$wa = $document->getWebAssetManager();
$wa->useScript('joomla.dialog');
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

Text::script('WARNING');

$status   = $this->status;
$valuta   = $this->escape($this->config->valuta);
$datetime = $this->config->dateformat . ' ' . $this->config->time_format;

// The coupon of the order with its terms as kept when it was applied (#__ticketstation_ordertotals),
// and the discount spread over the rows (see Coupon::refresh()), not the coupon's current settings.
$coupon        = '';
$discount      = 0.0;
$discount_text = '';

foreach ($this->data as $orderRow) {
    $discount += (float) $orderRow->discount;

    if ($coupon === '' && (string) $orderRow->coupon !== '') {
        $coupon        = $orderRow->coupon;
        $discount_text = (int) $orderRow->discount_type === 1 ? '(' . (float) $orderRow->discount_amount . '%)' : '';
    }
}

// Event and ticket dates are stored in local time, as entered.
$eventDate = function ($date) {
    return $date ? Date::display($date, $this->config->dateformat) : '';
};

// A count of the tickets of the order, e.g. "2 / 4", coloured by how far along it is.
$progress = function (int $done) use ($status) {
    $class = $done === 0 ? 'bg-secondary' : ($done >= $status->tickets ? 'bg-success' : 'bg-warning text-dark');

    return '<span class="badge ' . $class . '">' . $done . ' / ' . $status->tickets . '</span>';
};

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

<?= HTMLHelper::_('uitab.startTabSet', 'boxofficeTab', ['active' => 'overview', 'recall' => true, 'breakpoint' => 768]); ?>

<?= HTMLHelper::_('uitab.addTab', 'boxofficeTab', 'overview', Text::_('COM_TICKETSTATION_OVERVIEW')); ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_ORDER_INFORMATION') ?></h3>
            <div class="card-body">
                <table class="table mb-0">
                    <tr>
                        <th scope="row" class="w-50 fw-normal"><?= Text::_('COM_TICKETSTATION_ORDERCODE') ?></th>
                        <td><strong><?= $this->escape($this->items->ordercode); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_ORDERDATE') ?></th>
                        <td><?= Date::screen($this->items->orderdate, $datetime); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_(count($this->events) > 1 ? 'COM_TICKETSTATION_BOXOFFICE_EVENTS' : 'COM_TICKETSTATION_BOXOFFICE_EVENT'); ?></th>
                        <td>
                            <?php foreach ($this->events as $event) { ?>
                                <div>
                                    <?= $this->escape($event->eventname); ?>
                                    <small class="text-muted"><?= $eventDate($event->eventdate); ?> &middot; <?= Text::plural('COM_TICKETSTATION_BOXOFFICE_N_TICKETS', count($event->tickets)); ?></small>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></th>
                        <td>
                            <?php if ($this->transaction && (float) $this->transaction->amount > 0 && AclGate::can('ticketstation.finance')) { ?>
                                <a href="index.php?option=com_ticketstation&controller=transactions&task=edit&cid=<?= (int) $this->transaction->pid; ?>"><?= Price::format($this->orderprice, $valuta); ?></a>
                            <?php } else { ?>
                                <?= Price::format($this->orderprice, $valuta); ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS') ?></th>
                        <td>
                            <?php if ($status->paid === 1) { ?>
                                <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_PAID'); ?></span>
                            <?php } elseif ($status->paid === 2) { ?>
                                <span class="badge bg-info"><?= Text::_('COM_TICKETSTATION_REFUNDED'); ?></span>
                            <?php } elseif ($status->paid === 3) { ?>
                                <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_PENDING'); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW'); ?></span>
                            <?php } ?>
                            <?php if ($this->refunded > 0) { ?>
                                <span class="badge bg-info"><?= Text::_(Refund::isFull($this->refunded, $this->paidAmount) ? 'COM_TICKETSTATION_REFUND_BADGE_FULL' : 'COM_TICKETSTATION_REFUND_BADGE_PARTIAL'); ?>: <?= Price::format($this->refunded, $valuta); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php if ($this->paymentMethod !== '') { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_METHOD') ?></th>
                            <td><?= $this->escape($this->paymentMethod); ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_INVOICE') ?></th>
                        <td>
                            <?php if ($this->invoice) { ?>
                                <a href="<?= Uri::root() . 'administrator/components/com_ticketstation/invoices/' . $this->escape($this->invoiceFile); ?>" target="blank"><?= $this->escape($this->invoiceNumber); ?></a>
                                <?php if ((int) $this->invoice->sent === 1) { ?>
                                    <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_SENT'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_INVOICE_NOT_SENT'); ?></span>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-muted"><?= Text::_('COM_TICKETSTATION_NONE'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>

                    <?php if ($coupon !== '') { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_COUPON_CODE'); ?></th>
                            <td><span class="badge bg-warning text-dark px-2"><?= $this->escape($coupon); ?></span></td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_DISCOUNT'); ?> <?= $discount_text; ?></th>
                            <td><?= Price::format($discount, $valuta); ?></td>
                        </tr>
                    <?php } ?>

                    <?php // The service fee with the terms kept for this order (see OrderTotals). ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_SERVICE_FEE'); ?><?= OrderTotals::feeLabel($this->totals, fn ($amount) => Price::format($amount, $valuta)); ?></th>
                        <td>
                            <?php if ($this->totals->fee_type == OrderTotals::FEE_NONE) { ?>
                                <?= Text::_('COM_TICKETSTATION_NONE'); ?>
                            <?php } else { ?>
                                <?= Price::format($this->totals->fees, $valuta); ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_VAT_TOTAL'); ?></th>
                        <td><?= Price::format($this->totals->vat, $valuta); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><label for="newremark"><?= Text::_('COM_TICKETSTATION_ORDERREFERENCE') ?></label></th>
                        <td>
                            <div class="input-group">
                                <input class="form-control" type="text" name="newremark" id="newremark" maxlength="35"
                                       value="<?= $this->escape($this->remark->remarks ?? ''); ?>"
                                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); Joomla.submitbutton('updateinsertremark'); }" />
                                <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('updateinsertremark');">
                                    <?= Text::_('COM_TICKETSTATION_UPDATE_ORDERREFERENCE') ?>
                                </button>
                                <?php if (!empty($this->remark->remarks)) { ?>
                                    <button type="button" class="btn btn-outline-danger" onclick="Joomla.submitbutton('deleteremark');" title="<?= Text::_('COM_TICKETSTATION_REMOVE_ORDERREFERENCE') ?>">
                                        <span class="icon-times" aria-hidden="true"></span>
                                        <span class="visually-hidden"><?= Text::_('COM_TICKETSTATION_REMOVE_ORDERREFERENCE') ?></span>
                                    </button>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_CLIENT_INFORMATION') ?></h3>
            <div class="card-body">
                <table class="table mb-0" style="table-layout:fixed;">
                    <tr>
                        <th scope="row" class="w-50 fw-normal"><?= Text::_('COM_TICKETSTATION_NAME') ?></th>
                        <td><a href="index.php?option=com_ticketstation&controller=clients&task=edit&cid=<?= (int) $this->items->clientid; ?>"><?= $this->escape(trim($this->items->firstname . ' ' . $this->items->name)); ?></a></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?></th>
                        <td><?= $this->escape($this->items->phonenumber); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></th>
                        <td class="text-break"><small><a href="mailto:<?= $this->escape($this->items->emailaddress); ?>"><?= $this->escape($this->items->emailaddress); ?></a></small></td>
                    </tr>
                    <?php if ($this->customerNote !== '') { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_CUSTOMER_NOTE') ?></th>
                            <td style="white-space: pre-line; overflow-wrap: anywhere;"><?= $this->escape($this->customerNote); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TICKETS') ?></h3>
            <div class="card-body">
                <table class="table mb-0">
                    <tr>
                        <th scope="row" class="w-50 fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_STATUS_CREATED') ?></th>
                        <td><?= $progress($status->created); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_STATUS_SENT') ?></th>
                        <td><?= $progress($status->sent); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_DOWNLOADED') ?></th>
                        <td><?= Text::_($status->download ? 'COM_TICKETSTATION_YES' : 'COM_TICKETSTATION_NO'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_SCANNED') ?></th>
                        <td><?= $progress($status->scanned); ?></td>
                    </tr>
                    <?php if ($status->blocked > 0) { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLACKLIST') ?></th>
                            <td><span class="badge bg-danger"><?= $status->blocked; ?> / <?= $status->tickets; ?></span></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php // Refunds and chargebacks, and a way to fetch them from Mollie when a webhook was missed. ?>
<?php $canRefund = AclGate::can('ticketstation.payment'); ?>
<?php if ($this->refunds || ($canRefund && $this->providerPayment !== '' && $status->paid === 1)) { ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h3 class="mb-0 me-auto"><?= Text::_('COM_TICKETSTATION_REFUNDS') ?></h3>
            <?php if ($canRefund && $this->providerPayment !== '') { ?>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="Joomla.submitbutton('syncrefunds');">
                    <span class="fa fa-sync" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_REFUND_SYNC') ?>
                </button>
            <?php } ?>
        </div>
        <div class="card-body">
            <?php if (!$this->refunds) { ?>
                <p class="text-muted mb-0"><?= Text::_('COM_TICKETSTATION_REFUND_NONE') ?></p>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_DATE') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_TYPE') ?></th>
                                <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_REFUND_AMOUNT') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_STATUS') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_DECISION') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->refunds as $refund) {
                            $counts = Refund::counts($refund->status);
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= Date::screen($refund->created, $datetime); ?></td>
                                <td>
                                    <?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_TYPE_CHARGEBACK' : 'COM_TICKETSTATION_REFUND_TYPE_REFUND'); ?>
                                    <br /><small class="text-muted">
                                        <?= Text::_($refund->source === 'mollie' ? 'COM_TICKETSTATION_REFUND_SOURCE_MOLLIE' : ($refund->status === 'manual' ? 'COM_TICKETSTATION_REFUND_SOURCE_MANUAL' : 'COM_TICKETSTATION_REFUND_SOURCE_TICKETSTATION')); ?>
                                        <?php if ($refund->created_by_name) { ?>&middot; <?= $this->escape($refund->created_by_name); ?><?php } ?>
                                    </small>
                                    <?php if ($refund->description !== '') { ?>
                                        <br /><small><?= $this->escape($refund->description); ?></small>
                                    <?php } ?>
                                </td>
                                <td class="text-end text-nowrap<?= $counts ? '' : ' text-decoration-line-through text-muted'; ?>"><?= Price::format((float) $refund->amount, $valuta); ?></td>
                                <td>
                                    <span class="badge <?= $counts ? 'bg-info' : 'bg-danger'; ?>"><?= Text::_('COM_TICKETSTATION_REFUND_STATUS_' . strtoupper($refund->status)); ?></span>
                                    <?php if ($refund->mollie_id) { ?>
                                        <br /><small class="text-muted"><code><?= $this->escape($refund->mollie_id); ?></code></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ((int) $refund->attention === Refund::ATTENTION_DECISION) { ?>
                                        <?php if ($canRefund) { ?>
                                            <a class="btn btn-sm btn-warning" href="index.php?option=com_ticketstation&controller=boxoffice&task=refundform&cid=<?= (int) $this->items->ordercode; ?>&refund=<?= (int) $refund->id; ?>">
                                                <span class="fa fa-gavel" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_REFUND_DECIDE') ?>
                                            </a>
                                        <?php } else { ?>
                                            <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_REFUND_DECISION_NEEDED') ?></span>
                                        <?php } ?>
                                    <?php } elseif ((int) $refund->attention === Refund::ATTENTION_FAILED) { ?>
                                        <span class="badge bg-danger"><?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_REVERSED_NOTICE' : 'COM_TICKETSTATION_REFUND_FAILED_NOTICE') ?></span>
                                        <?php if ($canRefund) { ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                                    onclick="document.adminForm.refund_id.value = '<?= (int) $refund->id; ?>'; Joomla.submitbutton('acknowledgerefund');">
                                                <?= Text::_('COM_TICKETSTATION_REFUND_ACKNOWLEDGE') ?>
                                            </button>
                                        <?php } ?>
                                    <?php } elseif ($refund->decided) { ?>
                                        <small><?= Date::screen($refund->decided, $datetime); ?><?php if ($refund->decided_by_name) { ?><br /><?= $this->escape($refund->decided_by_name); ?><?php } ?></small>
                                        <?php if (Refund::isWaiting($refund)) { ?>
                                            <br /><span class="badge bg-warning text-dark" title="<?= $this->escape(Text::_('COM_TICKETSTATION_REFUND_WAITING_DESC')); ?>"><span class="fa fa-hourglass-half" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_REFUND_WAITING'); ?></span>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.addTab', 'boxofficeTab', 'tickets', Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER') . ' (' . $status->tickets . ')'); ?>

<div class="card">
    <div class="card-body">

        <div class="d-flex flex-wrap gap-2 mb-3" role="toolbar" aria-label="<?= Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER'); ?>">
            <div class="btn-group" role="group">
                <joomla-toolbar-button task="markasscanned" list-selection>
                    <button class="btn btn-outline-primary" type="button">
                        <span class="fa fa-qrcode" aria-hidden="true"></span>
                        <?= Text::_('COM_TICKETSTATION_BOXOFFICE_MARK_SCANNED'); ?>
                    </button>
                </joomla-toolbar-button>
                <joomla-toolbar-button task="resetscanstate" list-selection>
                    <button class="btn btn-outline-primary" type="button">
                        <span class="fa fa-undo" aria-hidden="true"></span>
                        <?= Text::_('COM_TICKETSTATION_BOXOFFICE_MARK_NOT_SCANNED'); ?>
                    </button>
                </joomla-toolbar-button>
            </div>

            <div class="btn-group" role="group">
                <joomla-toolbar-button task="blocked" list-selection>
                    <button class="btn btn-outline-primary" type="button">
                        <span class="fa fa-lock" aria-hidden="true"></span>
                        <?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLOCK'); ?>
                    </button>
                </joomla-toolbar-button>
                <joomla-toolbar-button task="unlock" list-selection>
                    <button class="btn btn-outline-primary" type="button">
                        <span class="fa fa-unlock" aria-hidden="true"></span>
                        <?= Text::_('COM_TICKETSTATION_BOXOFFICE_UNBLOCK'); ?>
                    </button>
                </joomla-toolbar-button>
            </div>

            <joomla-toolbar-button task="renewcodes" list-selection
                                   confirm-message="<?= $this->escape(Text::_('COM_TICKETSTATION_BOXOFFICE_RENEW_CODES_CONFIRM')); ?>">
                <button class="btn btn-outline-primary" type="button">
                    <span class="fa fa-sync" aria-hidden="true"></span>
                    <?= Text::_('COM_TICKETSTATION_BOXOFFICE_RENEW_CODES'); ?>
                </button>
            </joomla-toolbar-button>

            <?php if (AclGate::can('ticketstation.order.delete')) { ?>
            <joomla-toolbar-button task="removeSingleOrder" list-selection class="ms-auto"
                                   confirm-message="<?= $this->escape(Text::_('COM_TICKETSTATION_BOXOFFICE_REMOVE_TICKET_CONFIRM')); ?>">
                <button class="btn btn-danger" type="button">
                    <span class="fa fa-trash" aria-hidden="true"></span>
                    <?= Text::_('COM_TICKETSTATION_BOXOFFICE_REMOVE_TICKET'); ?>
                </button>
            </joomla-toolbar-button>
            <?php } ?>
        </div>

        <?php
        $i = 0;

        foreach ($this->events as $eventid => $event) { ?>

            <h4 class="h5 mt-3 mb-2">
                <?= $this->escape($event->eventname); ?>
                <small class="text-muted fw-normal"><?= $eventDate($event->eventdate); ?> &middot; <?= Text::plural('COM_TICKETSTATION_BOXOFFICE_N_TICKETS', count($event->tickets)); ?></small>
            </h4>

            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <td class="w-1 text-center">
                            <input class="form-check-input ts-check-event" type="checkbox" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" aria-label="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>">
                        </td>
                        <th scope="col" class="w-10"><?= Text::_('COM_TICKETSTATION_TICKET_ID'); ?></th>
                        <th scope="col"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_TICKET'); ?></th>
                        <th scope="col" class="w-20 text-center"><?= Text::_('COM_TICKETSTATION_SCANNED'); ?></th>
                        <th scope="col" class="w-10 text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLACKLIST'); ?></th>
                        <th scope="col" class="w-20 d-none d-lg-table-cell text-center"><?= Text::_('COM_TICKETSTATION_QRCODE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($event->tickets as $row) { ?>
                    <tr>
                        <td class="text-center"><?= HTMLHelper::_('grid.id', $i++, $row->orderid); ?></td>
                        <td><?= (int) $row->orderid; ?></td>
                        <td>
                            <?= $this->escape($row->ticketname); ?>
                            <?php if ($row->seat_sector != 0 && $row->seatid) { ?>
                                <br /><small><?= Text::_('COM_TICKETSTATION_SEAT'); ?>: <?= $this->escape($row->row_name . $row->seatid); ?></small>
                            <?php } ?>
                            <?php if ((int) $row->refund_state !== Refund::TICKET_UNCHANGED) { ?>
                                <br /><span class="badge <?= (int) $row->refund_state === Refund::TICKET_VALID ? 'bg-info' : 'bg-danger'; ?>"><?= Text::_('COM_TICKETSTATION_REFUND_STATE_' . (int) $row->refund_state); ?></span>
                            <?php } ?>
                            <?php if (isset($this->waitingTreatments[(int) $row->orderid])) { ?>
                                <br /><span class="badge bg-warning text-dark" title="<?= $this->escape(Text::_('COM_TICKETSTATION_REFUND_WAITING_DESC')); ?>">
                                    <span class="fa fa-hourglass-half" aria-hidden="true"></span>
                                    <?= Text::sprintf('COM_TICKETSTATION_REFUND_WAITING_TICKET', Text::_('COM_TICKETSTATION_REFUND_STATE_' . $this->waitingTreatments[(int) $row->orderid])); ?>
                                </span>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row->scanned == 0) { ?>
                                <span class="text-muted">&ndash;</span>
                            <?php } else { ?>
                                <span class="badge bg-success"><?= $row->scandate ? Date::display($row->scandate, $this->config->dateformat . ' H:i') : Text::_('COM_TICKETSTATION_YES'); ?></span>
                                <?php if ($row->scanner_name) { ?>
                                    <br /><small class="text-muted"><?= $this->escape($row->scanner_name); ?></small>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row->blacklisted == 1) { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                            <?php } else { ?>
                                <span class="text-muted">&ndash;</span>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-center">
                            <?php if ($row->barcode != '0') { ?>
                                <?php if ($row->scanned == 0) { ?>
                                    <details class="ts-qrcode">
                                        <summary class="badge bg-secondary"><?= $this->escape($row->barcode); ?></summary>
                                        <img src="<?= Uri::root(true) . '/administrator/components/com_ticketstation/tickets/qrcodes/' . $this->escape($row->barcode) . '.png'; ?>" alt="<?= Text::_('COM_TICKETSTATION_QRCODE'); ?>" class="mt-2" style="max-width: 150px;">
                                    </details>
                                <?php } else { ?>
                                    <span class="badge bg-secondary"><?= $this->escape($row->barcode); ?></span>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>

    </div>
</div>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.addTab', 'boxofficeTab', 'history', Text::_('COM_TICKETSTATION_HISTORY') . ($this->history ? ' (' . count($this->history) . ')' : '')); ?>

<div class="card">
    <div class="card-body">
        <?= LayoutHelper::render('history', ['history' => $this->history ?? []], JPATH_ADMINISTRATOR . '/components/com_ticketstation/tmpl/boxoffice/layouts'); ?>
    </div>
</div>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.endTabSet'); ?>

<input name="ordercode" type="hidden" value="<?= (int) $this->items->ordercode; ?>" />
<input name="option" type="hidden" value="com_ticketstation" />
<input name="task" type="hidden" value="" />
<input name="boxchecked" type="hidden" value="0"/>
<input name="refund_id" type="hidden" value="0"/>
<input name="controller" type="hidden" value="boxoffice"/>
<?= HTMLHelper::_('form.token'); ?>

</form>

<script>
    // Ticks or clears every ticket of one event, keeping the count Joomla's list buttons use.
    document.querySelectorAll('.ts-check-event').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            toggle.closest('table').querySelectorAll('input[name="cid[]"]').forEach(function (box) {
                if (box.checked !== toggle.checked) {
                    box.checked = toggle.checked;
                    Joomla.isChecked(box.checked);
                }
            });
        });
    });
</script>
