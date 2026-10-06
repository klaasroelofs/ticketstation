<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Boxoffice;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CustomerNote;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Invoice;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MolliePaymentMethods;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Tickets;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\ticketcreator;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

/**
 * Ticketstation Boxoffice Admin View
 */
class HtmlView extends BaseHtmlView
{

	/**
     * Display the Ticketstation Events view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'form')
        {
            $this->_displayForm($tpl);
            return;
        }

        if ($this->getLayout() == 'refund')
        {
            $this->displayRefund($tpl);
            return;
        }

        $model  = $this->getModel();
        $config = $this->get('config');

        $this->addListToolbar();

        $filters = [
            'search' => (string) $model->getState('filter.search'),
            'paid'   => (int) $model->getState('filter.paid'),
            'event'  => (int) $model->getState('filter.event'),
            'sent'   => (int) $model->getState('filter.sent'),
        ];

        // The events, the most recent first, with their date so events with the same name
        // (a series) can be told apart. Event dates are stored in local time, as entered.
        $events = [HTMLHelper::_('select.option', '0', Text::_('COM_TICKETSTATION_SELECTLIST_EVENT'))];

        foreach ($model->getEventOptions() as $event)
        {
            $date     = $event->eventdate ? Date::display($event->eventdate, $config->dateformat) : '';
            $events[] = HTMLHelper::_('select.option', $event->eventid, $event->eventname . ($date !== '' ? ' (' . $date . ')' : ''));
        }

        $lists['events'] = HTMLHelper::_('select.genericlist', $events, 'filter_ordering_event',
            'class="form-select" onchange="this.form.submit();" aria-label="' . Text::_('COM_TICKETSTATION_SELECTLIST_EVENT') . '"',
            'value', 'text', $filters['event']);

        $paid = [
            HTMLHelper::_('select.option', '0', Text::_('COM_TICKETSTATION_SELECTLIST_PAYMENT_STATUS')),
            HTMLHelper::_('select.option', '1', Text::_('COM_TICKETSTATION_PAID')),
            HTMLHelper::_('select.option', '2', Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW')),
            HTMLHelper::_('select.option', '3', Text::_('COM_TICKETSTATION_REFUNDED')),
            HTMLHelper::_('select.option', '4', Text::_('COM_TICKETSTATION_PENDING')),
            HTMLHelper::_('select.option', '5', Text::_('COM_TICKETSTATION_REFUND_FILTER_ATTENTION')),
        ];

        $lists['paid'] = HTMLHelper::_('select.genericlist', $paid, 'filter_ordering_paid',
            'class="form-select" onchange="this.form.submit();" aria-label="' . Text::_('COM_TICKETSTATION_SELECTLIST_PAYMENT_STATUS') . '"',
            'value', 'text', $filters['paid']);

        $sent = [
            HTMLHelper::_('select.option', '0', Text::_('COM_TICKETSTATION_BOXOFFICE_SELECTLIST_SENT')),
            HTMLHelper::_('select.option', '1', Text::_('COM_TICKETSTATION_SENT_TICKETS')),
            HTMLHelper::_('select.option', '2', Text::_('COM_TICKETSTATION_UNSENT_TICKETS')),
        ];

        $lists['sent'] = HTMLHelper::_('select.genericlist', $sent, 'filter_ordering_sent',
            'class="form-select" onchange="this.form.submit();" aria-label="' . Text::_('COM_TICKETSTATION_BOXOFFICE_SELECTLIST_SENT') . '"',
            'value', 'text', $filters['sent']);

        // Refunds a colleague made in the Mollie Dashboard, before the webhook reports them.
        Refund::pollMollie();

        $this->items      = $this->get('list');
        $this->pagination = $this->get('Pagination');
        $this->summary    = $model->getSummary();
        $this->config     = $config;
        $this->filters    = $filters;
        $this->lists      = $lists;

        parent::display($tpl);

    }

    /**
     * The toolbar of the order list. The actions on the ticked orders are grouped: the
     * payment, the tickets, and deleting.
     */
    private function addListToolbar()
    {
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_BOXOFFICE_TITLE'), 'fa fa-money-bill-alt');

        $toolbar = Toolbar::getInstance('toolbar');

        if (AclGate::can('ticketstation.reserve'))
        {
            $toolbar->linkButton('reservation', 'COM_TICKETSTATION_VIEW_RESERVATION_TITLE')
                ->url('index.php?option=com_ticketstation&view=reservation')
                ->icon('icon-new')
                ->buttonClass('btn btn-success');
        }

        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        /** @var Toolbar $childBar */
        $childBar = $dropdown->getChildToolbar();

        $childBar->divider(Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT'));

        $payment = AclGate::can('ticketstation.payment');

        if ($payment)
        {
            $childBar->standardButton('full_process', 'COM_TICKETSTATION_TOOLBAR_FULL_PROCESS', 'boxoffice.full_process')
                ->icon('fa fa-cube')
                ->listCheck(true);

            $childBar->standardButton('allpayments', 'COM_TICKETSTATION_BOXOFFICE_MARK_PAID', 'boxoffice.allpayments')
                ->icon('fa fa-thumbs-up')
                ->listCheck(true);
        }

        // A payment request can't be paid while online payments are off.
        if (Shop::paymentsOn())
        {
            $childBar->standardButton('resendpayment', 'COM_TICKETSTATION_RESEND_PAYMENT', 'boxoffice.resendpayment')
                ->icon('fa fa-share')
                ->listCheck(true);
        }

        $childBar->divider(Text::_('COM_TICKETSTATION_BOXOFFICE_TICKETS'));

        $childBar->standardButton('sendingticket', 'COM_TICKETSTATION_BOXOFFICE_SEND_TICKETS', 'boxoffice.sendingticket')
            ->icon('fa fa-paper-plane')
            ->listCheck(true);

        if (AclGate::can('ticketstation.order.delete'))
        {
            $childBar->divider();

            $childBar->delete('boxoffice.remove')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        $toolbar->standardButton('export', 'COM_TICKETSTATION_BOXOFFICE_EXPORT', 'boxoffice.export')
            ->icon('fa fa-file-csv')
            ->listCheck(false);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('boxoffice');
    }

    function _displayForm($tpl = null)
    {
        $model = $this->getModel();

        // The order was auto-removed by the ticketcleaner: the row itself is really gone
        // (so front-end availability/reports are unaffected), only a History snapshot
        // remains. Show a read-only summary instead of the normal, interactive order form -
        // none of its actions (resend, mark paid, scan, ...) apply to a deleted order.
        if ($model->isGhostOrder())
        {
            ToolBarHelper::title(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS'), 'fa fa-money-bill-alt');
            ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
            Docs::toolbarButton('boxoffice-order');

            $this->config  = $this->get('config');
            $this->history = $this->get('history');
            $this->ghost   = $model->getGhostOverview();

            $this->setLayout('ghost');
            parent::display($tpl);

            return;
        }

        $config      = $this->get('config');
        $items       = $this->get('client');
        $ordercode   = (int) ($items->ordercode ?? 0);
        $refunds     = $model->getRefunds();

        // Mollie doesn't call the webhook when a refund is cancelled, so a refund whose tickets
        // wait for it is looked up at Mollie when the order is opened. The tickets change here
        // when Mollie has processed it in the meantime.
        $waiting = array_filter($refunds, static fn ($refund) => Refund::isWaiting($refund) && $refund->mollie_id);

        if ($waiting)
        {
            try
            {
                Refund::sync($ordercode);
                $refunds = $model->getRefunds();
            }
            catch (\Throwable $e)
            {
                // Mollie can't be reached: the order shows what is known.
            }
        }

        $data        = $this->get('data');
        $transaction = $model->getTransaction();
        $paidAmount  = Refund::paidAmount($ordercode);
        $refunded    = Refund::refundedAmount($ordercode);

        // What the tickets of the order have been through, for the toolbar and the Overview.
        $status = (object) [
            'ordercode'  => $ordercode,
            'refundable' => $refunded < $paidAmount - 0.004,
            'paid'     => (int) ($items->paid ?? 0),
            'tickets'  => count($data),
            'created'  => count(array_filter($data, static fn ($row) => (int) $row->pdfcreated === 1)),
            'sent'     => count(array_filter($data, static fn ($row) => (int) $row->pdfsent === 1)),
            'scanned'  => count(array_filter($data, static fn ($row) => (int) $row->scanned === 1)),
            'blocked'  => count(array_filter($data, static fn ($row) => (int) $row->blacklisted === 1)),
            'download' => (int) max(array_merge([0], array_map(static fn ($row) => (int) $row->downloaded, $data))),
            'pdf'      => $this->ticketFileExists($items->ordercode ?? 0, array_values(array_filter($data, static fn ($row) => (int) $row->refund_state < Refund::TICKET_INVALID))),
        ];

        $this->addFormToolbar($status);

        // The order date is when the order was started: its first row (the list shows the same).
        if ($data)
        {
            $items->orderdate = min(array_map(static fn ($row) => (string) $row->orderdate, $data));
        }

        ## GENERATE QR CODES IF NEEDED
        foreach ($data as $row)
        {
            $qrcode = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/qrcodes/' . $row->barcode . '.png';

            //checken of QR-code al bestaat, zo niet aanmaken
            if ((!file_exists($qrcode)) && ($row->barcode != '0'))
            {
                (new ticketcreator($row->orderid))->get_qr_image_with_logo($row->barcode, '250', $qrcode);
            }
        }

        // The tickets per event, in the order the events take place (see getData()).
        $events = [];

        foreach ($data as $row)
        {
            $eventid = (int) $row->eventid;

            if (!isset($events[$eventid]))
            {
                $events[$eventid] = (object) [
                    'eventname' => $row->eventname,
                    'eventdate' => $row->eventdate,
                    'tickets'   => [],
                ];
            }

            $events[$eventid]->tickets[] = $row;
        }

        ## Orderprice: what was paid, or else what the customer pays (tickets after the discount,
        ## plus the service fee).
        $this->totals = OrderTotals::get($items->ordercode ?? 0);

        if ($transaction && (float) $transaction->amount > 0)
        {
            $orderprice = (float) $transaction->amount;
        }
        else
        {
            $orderprice = $this->totals->total;
        }

        $invoice = $model->getInvoice();

        $this->data          = $data;
        $this->events        = $events;
        $this->status        = $status;
        $this->remark        = $this->get('remark');
        $this->customerNote  = (new CustomerNote)->get($items->ordercode ?? 0);
        $this->items         = $items;
        $this->config        = $config;
        $this->orderprice    = $orderprice;
        $this->transaction   = $transaction;
        $this->paymentMethod = $transaction && $transaction->type !== '' ? MolliePaymentMethods::label(strtolower($transaction->type)) : '';
        $this->invoice       = $invoice;
        $this->invoiceNumber = $invoice ? (new Invoice)->getInvoiceNumber($invoice->invoiceid, $config->invoice_prefix) : '';
        $this->invoiceFile   = $invoice ? (new Invoice)->getPdfFilename($invoice->invoiceid) : '';
        $this->history       = $this->get('history');
        $this->refunds       = $refunds;

        // orderid => the treatment that waits until Mollie has processed its refund.
        $this->waitingTreatments = [];

        foreach ($refunds as $refund)
        {
            if (Refund::isWaiting($refund))
            {
                $this->waitingTreatments = array_replace($this->waitingTreatments, array_map('intval', (array) json_decode($refund->treatments, true)));
            }
        }
        $this->refunded      = $refunded;
        $this->paidAmount    = $paidAmount;
        $this->molliePayment = Refund::molliePaymentId($ordercode);

        parent::display($tpl);

    }

    /**
     * The refund screen: a new refund of the order, or the decision about the tickets for a
     * refund or chargeback Mollie reported (&refund=<id>, only while it waits for one).
     */
    private function displayRefund($tpl = null)
    {
        $model = $this->getModel();
        $input = Factory::getApplication()->getInput();

        $items     = $this->get('client');
        $ordercode = (int) ($items->ordercode ?? 0);
        $refund    = Refund::get($input->getInt('refund', 0));

        if ($refund && ((int) $refund->ordercode !== $ordercode || (int) $refund->attention !== Refund::ATTENTION_DECISION))
        {
            $refund = null;
        }

        $data       = $this->get('data');
        $totals     = OrderTotals::get($ordercode);
        $paidAmount = Refund::paidAmount($ordercode);
        $refunded   = Refund::refundedAmount($ordercode);

        // In a decision, the tickets that together match the refunded amount are proposed.
        $proposal = $refund ? Refund::propose($data, (float) $refund->amount, (float) $totals->fees, round($paidAmount - $refunded + (float) $refund->amount, 2)) : [];

        ToolBarHelper::title(Text::_($refund ? 'COM_TICKETSTATION_REFUND_DECIDE_TITLE' : 'COM_TICKETSTATION_REFUND_TITLE'), 'fa fa-reply');

        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('saverefund', $refund ? 'COM_TICKETSTATION_REFUND_SAVE_DECISION' : 'COM_TICKETSTATION_REFUND_SAVE', 'saverefund')
            ->icon($refund ? 'icon-save' : 'fa fa-reply')
            ->buttonClass('btn btn-success');
        $toolbar->linkButton('back', 'JTOOLBAR_CANCEL')
            ->url('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode)
            ->icon('icon-times')
            ->buttonClass('btn btn-danger');
        Docs::toolbarButton('boxoffice-refund');

        $this->items         = $items;
        $this->data          = $data;
        $this->config        = $this->get('config');
        $this->totals        = $totals;
        $this->refund        = $refund;
        $this->proposal      = $proposal;
        $this->paidAmount    = $paidAmount;
        $this->refunded      = $refunded;
        $this->remaining     = max(0.0, round($paidAmount - $refunded, 2));
        $this->molliePayment = Refund::molliePaymentId($ordercode);
        $this->waitingTreatments = [];

        foreach (Refund::forOrder($ordercode) as $other)
        {
            if (Refund::isWaiting($other))
            {
                $this->waitingTreatments = array_replace($this->waitingTreatments, array_map('intval', (array) json_decode($other->treatments, true)));
            }
        }

        parent::display($tpl);
    }

    /**
     * The toolbar of an order. Only the actions that apply to the order's current state are
     * offered: a paid order can't be marked as paid again, tickets can only be downloaded or
     * resent once they exist.
     *
     * @param   object  $status  See _displayForm().
     */
    private function addFormToolbar($status)
    {
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS'), 'fa fa-money-bill-alt');

        $toolbar = Toolbar::getInstance('toolbar');

        // Payment. Without the right to register payments only a payment request remains, and
        // only for an order that isn't paid and while online payments are on.
        $payment = AclGate::can('ticketstation.payment');
        $request = in_array($status->paid, [0, 3], true) && Shop::paymentsOn();

        if ($payment || $request)
        {
            $dropdown = $toolbar->dropdownButton('payment-group')
                ->text('COM_TICKETSTATION_BOXOFFICE_PAYMENT')
                ->toggleSplit(false)
                ->icon('fa fa-credit-card')
                ->buttonClass('btn btn-action');

            $childBar = $dropdown->getChildToolbar();

            if ($payment && $status->paid !== 1)
            {
                $childBar->standardButton('completeorder', 'COM_TICKETSTATION_TOOLBAR_FULL_PROCESS', 'completeorder')
                    ->icon('fa fa-cube');
                $childBar->standardButton('payment', 'COM_TICKETSTATION_BOXOFFICE_MARK_PAID', 'payment')
                    ->icon('fa fa-thumbs-up');
            }

            if ($request)
            {
                $childBar->standardButton('sendpaymentrequest', 'COM_TICKETSTATION_RESEND_PAYMENT', 'sendpaymentrequest')
                    ->icon('fa fa-share');
            }

            if ($payment && $status->paid !== 0)
            {
                $childBar->standardButton('nopayment', 'COM_TICKETSTATION_BOXOFFICE_MARK_UNPAID', 'nopayment')
                    ->icon('fa fa-thumbs-down');
            }

            if ($payment && $status->paid === 1 && $status->refundable)
            {
                $childBar->linkButton('refundform', 'COM_TICKETSTATION_REFUND_BUTTON')
                    ->url('index.php?option=com_ticketstation&controller=boxoffice&task=refundform&cid=' . (int) $status->ordercode)
                    ->icon('fa fa-reply');
            }
        }

        // Tickets
        $dropdown = $toolbar->dropdownButton('tickets-group')
            ->text('COM_TICKETSTATION_BOXOFFICE_TICKETS')
            ->toggleSplit(false)
            ->icon('fa fa-ticket-alt')
            ->buttonClass('btn btn-action');

        $childBar = $dropdown->getChildToolbar();

        if ($status->pdf)
        {
            $childBar->standardButton('sendticketcopy', 'COM_TICKETSTATION_TOOLBAR_RESEND_TICKETS', 'sendticketcopy')
                ->icon('fa fa-paper-plane');
            $childBar->standardButton('downloadtickets', 'COM_TICKETSTATION_TOOLBAR_DOWNLOAD_TICKETS', 'downloadtickets')
                ->icon('fa fa-download');
        }

        // The tickets keep their QR codes, so this is safe for tickets already sent.
        $childBar->standardButton('processticket', 'COM_TICKETSTATION_TOOLBAR_CREATE_TICKETS', 'processticket')
            ->icon('fa fa-file-pdf');

        ToolbarHelper::custom('sendinvoice', 'file-alt', '', Text::_('COM_TICKETSTATION_TOOLBAR_SEND_INVOICE'), false, false);
        ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        Docs::toolbarButton('boxoffice-order');
    }

    /**
     * Whether the order's ticket file exists, as downloadtickets and sendticketcopy use it:
     * the combined file of the order, or the file of its single ticket.
     */
    private function ticketFileExists($ordercode, array $data)
    {
        if (file_exists(Tickets::combinedPath($ordercode)))
        {
            return true;
        }

        return isset($data[0]) && file_exists(Tickets::singlePath($ordercode));
    }

}
