<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CPANEL_BROWSER_TITLE') . ' - ' . $app->get('sitename'));
$document->addStyleSheet( '/administrator/components/com_ticketstation/assets/css/ticketstation.css' );

## Load dark theme only for J5!
if (version_compare(JVERSION, '4.999.999', 'gt')) {
    $document->addStyleSheet('/administrator/components/com_ticketstation/assets/css/j5dark.css');
}

?>

<?php // Main area ?>
<div class="container">
    <div class="row">
        <?php // LEFT COLUMN (66% desktop width) ?>
        <div class="col col-12 col-lg-8">
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=boxoffice">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-money-bill-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_BOXOFFICE') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=clients">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-users"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_CUSTOMERS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=transactions">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-credit-card"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TRANSACTIONS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=invoices">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-file-invoice"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_INVOICES') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=coupons">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-percent"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_COUPONS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=reservation">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-calendar-plus"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_RESERVATION_CPANEL_BUTTON') ?></span>
                        </a>

                        <?php if ($this->config->show_waitinglist == 1) { ?>
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=waitinglist">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-hourglass-half"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_WAITINGLIST') ?></span>
                        </a>
                        <?php } ?>

                    </div>
                </div>

            </div>
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_TICKETMANAGEMENT') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=tickets">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-ticket-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TICKETS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=events">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-calendar-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_EVENTS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=venues">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fas fa-hotel"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_VENUES') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=seatplans">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-chair"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_SEATPLANS') ?></span>
                        </a>

                    </div>
                </div>

            </div>

            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_CONFIGURATION') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=scanners">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-qrcode"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TICKETSCANNING') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=templates">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-envelope"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=configuration">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-cog"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_CONFIGURATION') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=mollie">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <img width="26" src="components/com_ticketstation/assets/images/MollieMonogram23-CircleWhite.png">
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_MOLLIE_CONFIG') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=docs">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-book"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_VIEW_DOCS_TITLE') ?></span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <?php // RIGHT COLUMN (33% desktop width) ?>
        <div class="col-12 col-lg-4">
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_COMPONENT_INFO') ?>
                </h3>
                <div class="card-body">
                    <table class="table itemList">
                        <tr>
                            <td>
                                <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_VERSION') ?>
                            </td>
                            <td>
                                <?php echo $this->data['version']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_RELEASEDATE') ?>
                            </td>
                            <td>
                                <?php echo $this->data['creationDate']; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="card mb-2">
                <div class="card-body">
                    <div style="margin-bottom: 10px; text-align: center;"><?= Text::_('COM_TICKETSTATION_ENJOYING') ?></div>
                    <div style="text-align: center;">
                        <a
                                href="https://www.paypal.com/donate/?business=TSVSU67MCBM8W&no_recurring=1&item_name=Thank+you+for+appreciating+Ticketstation%21&currency_code=EUR"
                                class="btn btn-outline-success mb-2" target="blank">
                            <span class="fa fa-donate"></span>
                            <?= Text::_('COM_TICKETSTATION_DONATE') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php // STATISTICS (full width) ?>
    <?php
    $stats     = $this->stats;
    $weekDiff  = $stats['week']->tickets - $stats['prev_week']->tickets;
    ?>
    <div class="row mt-4">
        <div class="col">
            <h2 class="mb-0"><span class="fa fa-chart-line text-primary me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_TITLE') ?></h2>
            <p class="text-muted"><?= Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_SUBTITLE', HTMLHelper::_('date', 'now', 'W'), HTMLHelper::_('date', 'now', 'F Y')) ?></p>
        </div>
    </div>
    <div class="row">
        <div class="col-6 col-lg-3 mb-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_SOLD_WEEK') ?></div>
                    <div class="fs-2 fw-bold"><?= $stats['week']->tickets; ?></div>
                    <div class="small <?= $weekDiff > 0 ? 'text-success' : ($weekDiff < 0 ? 'text-danger' : 'text-muted'); ?>">
                        <?= Text::sprintf('COM_TICKETSTATION_CPANEL_STATS_PREV_WEEK', $stats['prev_week']->tickets); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_WEEK') ?></div>
                    <div class="fs-2 fw-bold"><?= Price::_($stats['week']->revenue); ?></div>
                    <div class="small text-muted"><?= Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_ORDERS', $stats['week']->orders); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_REVENUE_MONTH') ?></div>
                    <div class="fs-2 fw-bold"><?= Price::_($stats['month']->revenue); ?></div>
                    <div class="small text-muted"><?= Text::plural('COM_TICKETSTATION_CPANEL_STATS_N_TICKETS', $stats['month']->tickets); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_ON_SALE') ?></div>
                    <div class="fs-2 fw-bold"><?= $stats['on_sale_events']; ?> / <?= $stats['on_sale_tickets']; ?></div>
                    <div class="small text-muted"><?= Text::_('COM_TICKETSTATION_CPANEL_STATS_EVENTS_TICKETS') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_HEADER') ?>
                </h3>
                <div class="card-body">
                    <?php if (empty($this->availability)) { ?>
                        <p class="text-muted mb-0"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_NONE') ?></p>
                    <?php } else { ?>
                        <table class="table table-sm align-middle mb-2">
                            <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_TICKET') ?></th>
                                <th scope="col" class="d-none d-md-table-cell"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_DATE') ?></th>
                                <th scope="col" class="w-25"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_SOLD') ?></th>
                                <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_LEFT') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this->availability as $row) {
                                $percentageLeft = 100 - $row->percentage_sold;
                                if ($row->available <= 0) {
                                    $barClass  = 'bg-danger';
                                    $leftClass = 'text-danger';
                                } elseif ($percentageLeft < 11) {
                                    $barClass  = 'bg-warning';
                                    $leftClass = 'text-warning';
                                } else {
                                    $barClass  = 'bg-primary';
                                    $leftClass = '';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a href="index.php?option=com_ticketstation&controller=tickets&task=edit&cid=<?= (int) $row->ticketid; ?>"><?= $row->eventname; ?> - <?= $row->ticketname; ?></a>
                                        <?php if ($row->show_seatplans == 1) { ?>
                                            <span class="fa fa-chair text-muted small" title="<?= Text::_('COM_TICKETSTATION_SEATPLANS') ?>"></span>
                                        <?php } ?>
                                    </td>
                                    <td class="small d-none d-md-table-cell text-nowrap"><?= date($this->config->dateformat . ' ' . $this->config->time_format, strtotime($row->startdate)); ?></td>
                                    <td>
                                        <div class="progress" role="progressbar" aria-valuenow="<?= $row->percentage_sold; ?>" aria-valuemin="0" aria-valuemax="100"
                                             title="<?= $row->sold; ?> / <?= $row->total; ?>">
                                            <div class="progress-bar <?= $barClass; ?>" style="width: <?= $row->percentage_sold; ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?= $row->sold; ?> / <?= $row->total; ?></span>
                                    </td>
                                    <td class="text-end text-nowrap <?= $leftClass; ?>">
                                        <?= $row->available <= 0 ? Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_SOLD_OUT') : $row->available; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <a href="index.php?option=com_ticketstation&view=tickets" class="small"><?= Text::_('COM_TICKETSTATION_CPANEL_AVAILABILITY_ALL') ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_ATTENTION_HEADER') ?>
                </h3>
                <div class="card-body">
                    <?php if (empty($this->attention)) { ?>
                        <p class="text-muted mb-0">
                            <span class="fa fa-check-circle text-success"></span>
                            <?= Text::_('COM_TICKETSTATION_CPANEL_ATTENTION_NONE') ?>
                        </p>
                    <?php } else { ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($this->attention as $item) { ?>
                                <li class="mb-2">
                                    <span class="fa <?= $item->icon; ?> text-<?= $item->level; ?> me-1" aria-hidden="true"></span>
                                    <a href="<?= $item->link; ?>"><?= Text::plural($item->key, $item->count); ?></a>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="ticketstation-cpanel-footer small mt-3 p-3 bg-light border-top border-4 d-flex flex-column">
                <p class="text-muted">
                    <span style="color:#c53c88;"><b><em>Ticketstation for Joomla!™</em></b></span> is based on the original code of RD Ticketmaster by Robert Dam,
                    which has been massively reworked and enhanced to make it Joomla! 6.x compatible and to suit the specific needs of <a href="https://www.huibuuke.nl">Stichting De Huibuuke</a>, Overloon, The Netherlands.
                    <br/>
                    <strong>Use it to your advantage, but please do not expect close support. This extension was developed with limited programming skills, merely as a hobby project.</strong>
                </p>

                <p class="text-muted">
                    Copyright 2022-<?= date('Y') ?> <a href="mailto:<?php echo $this->data['authorEmail']; ?>"><?php echo $this->data['author']; ?></a> Overloon. All legal rights reserved.
                    <br/>
                    <span style="color:#c53c88;"><b><em>Ticketstation for Joomla!™</em></b></span> is Free Software and is distributed under the terms of the
                    <a href="http://www.gnu.org/licenses/gpl-3.0.html">GNU General Public License</a>, version 3 or any later version.
                </p>
            </div>
        </div>
    </div>
</div>
