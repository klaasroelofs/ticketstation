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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_BOXOFFICE_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');
$wa->registerAndUseStyle('searchtools', Uri::root() . 'media/templates/administrator/atum/css/system/searchtools/searchtools.css');

$filtered = $this->filters['search'] !== '' || $this->filters['paid'] || $this->filters['event'] || $this->filters['sent'];

// A count of the tickets of an order as a small badge: "2/4", coloured by how far along it is.
$progress = function (int $done, int $total, string $title, string $complete = 'bg-success') {
    if ($done === 0) {
        return '';
    }

    $class = $done >= $total ? $complete : 'bg-warning text-dark';

    return '<span class="badge ' . $class . '" title="' . $this->escape($title) . '">' . $done . '/' . $total . '</span>';
};

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">

                <div class="js-stools" role="search">
                    <div class="js-stools-container-bar">
                        <div class="btn-toolbar">

                            <div class="filter-search-bar btn-group">
                                <div class="input-group">
                                    <input type="text" name="searchbox" id="searchbox" value="<?= $this->escape($this->filters['search']); ?>" class="form-control" aria-describedby="filter_search-desc" placeholder="<?= Text::_('COM_TICKETSTATION_SEARCH'); ?>" inputmode="search" autocomplete="off" onfocus="this.select();">
                                    <div role="tooltip" id="filter_search-desc" class="filter-search-bar__description">
                                        <?= Text::_('COM_TICKETSTATION_BOXOFFICE_SEARCH_DESC'); ?>
                                    </div>
                                    <span class="filter-search-bar__label visually-hidden">
                                        <label id="filter_search-lbl" for="searchbox"><?= Text::_('JSEARCH_FILTER'); ?></label>
                                    </span>
                                    <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="<?= Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                                        <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="ordering-select">
                                <div class="js-stools-field-list"><?= $this->lists['events']; ?></div>
                            </div>

                            <div class="ordering-select">
                                <div class="js-stools-field-list"><?= $this->lists['paid']; ?></div>
                            </div>

                            <div class="ordering-select">
                                <div class="js-stools-field-list"><?= $this->lists['sent']; ?></div>
                            </div>

                            <div class="filter-search-actions btn-group">
                                <button type="button" class="filter-search-actions__button btn btn-primary js-stools-btn-clear" onclick="
                                    var form = this.form;
                                    form.searchbox.value = '';
                                    form.filter_ordering_event.value = '0';
                                    form.filter_ordering_paid.value = '0';
                                    form.filter_ordering_sent.value = '0';
                                    form.submit();">
                                    <?= Text::_('JSEARCH_FILTER_CLEAR'); ?>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <?php if ($this->summary->orders > 0) { ?>
                    <p class="text-muted small mb-2" aria-live="polite">
                        <?= Text::sprintf('COM_TICKETSTATION_BOXOFFICE_SUMMARY', (int) $this->summary->orders, (int) $this->summary->tickets, (int) $this->summary->scanned); ?>
                        <?php if ($this->filters['event']) { ?>
                            &middot; <?= Text::sprintf('COM_TICKETSTATION_BOXOFFICE_SUMMARY_EVENT', (int) $this->summary->event_tickets); ?>
                        <?php } ?>
                    </p>
                <?php } ?>

                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                            </td>
                            <th scope="col" class="w-15"><?= Text::_('COM_TICKETSTATION_ORDER'); ?></th>
                            <th scope="col"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME'); ?></th>
                            <th scope="col" class="w-5 d-none d-md-table-cell text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_TICKETS_2'); ?></th>
                            <th scope="col" class="w-8 d-none d-md-table-cell text-end"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE'); ?></th>
                            <th scope="col" class="w-8 text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS'); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TICKETS'); ?></th>
                            <th scope="col" class="w-5 d-none d-lg-table-cell text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_SCANNED'); ?></th>
                            <th scope="col" class="w-5 d-none d-xl-table-cell text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_MANUAL_CONFIRM'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$this->items) { ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <?= Text::_($filtered ? 'COM_TICKETSTATION_BOXOFFICE_NO_MATCHES' : 'COM_TICKETSTATION_BOXOFFICE_NO_ORDERS'); ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($this->items as $i => $row) {

                        $checked = HTMLHelper::_('grid.id', $i, $row->ordercode);
                        $link    = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . (int) $row->ordercode;
                        $tickets = (int) $row->o_tickets;
                        ?>

                        <tr class="row<?= $i % 2; ?>">
                            <td class="text-center"><?= $checked; ?></td>
                            <td>
                                <strong><a href="<?= $link; ?>"><?= $this->escape($row->ordercode); ?></a></strong>
                                <?php if (!empty($row->removed_auto)) { ?>
                                    <span class="badge bg-secondary" title="<?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_NOTICE'); ?>"><?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_BADGE'); ?></span>
                                <?php } ?>
                                <?php if ((int) $row->blocked_tickets > 0) { ?>
                                    <span class="text-danger ms-1" title="<?= Text::sprintf('COM_TICKETSTATION_BOXOFFICE_BLOCKED_COUNT', (int) $row->blocked_tickets, $tickets); ?>">
                                        <span class="fa fa-ban" aria-hidden="true"></span>
                                        <span class="visually-hidden"><?= Text::sprintf('COM_TICKETSTATION_BOXOFFICE_BLOCKED_COUNT', (int) $row->blocked_tickets, $tickets); ?></span>
                                    </span>
                                <?php } ?>
                                <br />
                                <small>
                                    <?= $this->escape(trim($row->firstname . ' ' . $row->name)); ?><br/>
                                    <em><?= Date::_($row->orderdate, $this->config->dateformat . ' ' . $this->config->time_format); ?></em>
                                </small>
                            </td>
                            <td>
                                <?php foreach ($row->events as $event) {
                                    $other = $this->filters['event'] && (int) $event->eventid !== $this->filters['event'];
                                    ?>
                                    <div class="<?= $other ? 'text-muted' : ''; ?>">
                                        <strong><?= $this->escape($event->eventname); ?></strong>
                                        <?php if (count($row->events) > 1) { ?>
                                            <span class="badge bg-light text-dark border"><?= (int) $event->tickets; ?>&times;</span>
                                        <?php } ?>
                                        <br /><small><?= $this->escape($event->ticketnames); ?></small>
                                    </div>
                                <?php } ?>
                                <?php if ($row->remarks !== '') { ?>
                                    <span class="badge bg-info" title="<?= Text::_('COM_TICKETSTATION_ORDERREFERENCE'); ?>"><?= $this->escape($row->remarks); ?></span>
                                <?php } ?>
                                <?php if ($row->customer_note !== '') { ?>
                                    <span class="badge bg-secondary" title="<?= $this->escape($row->customer_note); ?>"><span class="icon-comment" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_CUSTOMER_NOTE'); ?></span>
                                <?php } ?>
                                <?php if (!empty($row->coupon)) { ?>
                                    <span title="<?= $this->escape($row->coupon); ?>" class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_DISCOUNT_CAPS'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="d-none d-md-table-cell text-center">
                                <?php if ($this->filters['event'] && (int) $row->event_tickets !== $tickets) { ?>
                                    <span title="<?= Text::sprintf('COM_TICKETSTATION_BOXOFFICE_EVENT_TICKETS_OF', (int) $row->event_tickets, $tickets); ?>"><?= (int) $row->event_tickets; ?> / <?= $tickets; ?></span>
                                <?php } else { ?>
                                    <?= $tickets; ?>
                                <?php } ?>
                            </td>
                            <td class="d-none d-md-table-cell text-end text-nowrap">
                                <?= ((float) $row->orderprice == 0) ? '-' : $this->escape($this->config->valuta) . ' ' . number_format((float) $row->orderprice, 2, ',', ''); ?>
                            </td>
                            <td class="small text-center">
                                <?php if ($row->paid == 1) { ?>
                                    <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_PAID'); ?></span>
                                <?php } elseif ($row->paid == 2) { ?>
                                    <span class="badge bg-info"><?= Text::_('COM_TICKETSTATION_REFUNDED'); ?></span>
                                <?php } elseif ($row->paid == 3) { ?>
                                    <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_PENDING'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center text-nowrap">
                                <?php
                                // Created, sent and downloaded, in the order they happen.
                                $steps = [
                                    ['fa-file-pdf', (int) $row->created_tickets, 'COM_TICKETSTATION_BOXOFFICE_TICKETS_CREATED'],
                                    ['fa-paper-plane', (int) $row->sent_tickets, 'COM_TICKETSTATION_BOXOFFICE_TICKETS_SENT'],
                                ];

                                foreach ($steps as [$icon, $done, $label]) {
                                    $class = $done >= $tickets ? 'text-success' : ($done > 0 ? 'text-warning' : 'text-body-tertiary opacity-50');
                                    $title = Text::sprintf($label, $done, $tickets);
                                    ?>
                                    <span class="fa <?= $icon; ?> <?= $class; ?> mx-1" title="<?= $title; ?>" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?= $title; ?></span>
                                <?php } ?>
                                <?php if ((int) $row->downloaded === 1) { ?>
                                    <span class="fa fa-download text-success mx-1" title="<?= Text::_('COM_TICKETSTATION_BOXOFFICE_TICKETS_DOWNLOADED'); ?>" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TICKETS_DOWNLOADED'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?= $progress((int) $row->scanned_tickets, $tickets, Text::_('COM_TICKETSTATION_BOXOFFICE_SCANNED')); ?>
                            </td>
                            <td class="small d-none d-xl-table-cell text-center">
                                <?php if ($row->published == 1) { ?>
                                    <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_NO'); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?= $this->pagination->getListFooter(); ?>

            </div>
        </div>
    </div>


    <input name="option" type="hidden" value="com_ticketstation"/>
    <input name="controller" type="hidden" value="boxoffice"/>
    <input name="task" type="hidden" value=""/>
    <input name="boxchecked" type="hidden" value="0"/>
    <?= HTMLHelper::_('form.token'); ?>

</form>

<script>
    // The export downloads a file and leaves this page as it was, task included: without
    // clearing it, the next filter change would download the file again.
    document.addEventListener('DOMContentLoaded', function () {
        var submitbutton = Joomla.submitbutton;

        Joomla.submitbutton = function (task) {
            submitbutton.apply(this, arguments);

            if (task === 'boxoffice.export') {
                setTimeout(function () {
                    document.getElementById('adminForm').task.value = '';
                }, 0);
            }
        };
    });
</script>
