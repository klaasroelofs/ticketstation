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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS') . ' - ' . $app->get('sitename'));

$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

$ghost    = $this->ghost;
$history  = $this->history ?? [];
$valuta   = $this->escape($this->config->valuta);
$datetime = $this->config->dateformat . ' ' . $this->config->time_format;

$reason_key = $ghost->reason === 'unfinished'
    ? 'COM_TICKETSTATION_ORDER_REMOVED_AUTO_REASON_UNFINISHED'
    : 'COM_TICKETSTATION_ORDER_REMOVED_AUTO_REASON_PENDING';

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

<div class="alert alert-secondary">
    <span class="fa fa-broom" aria-hidden="true"></span>
    <?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_NOTICE') ?>
    <br/>
    <small>
        <?= Text::_($reason_key) ?>
        &middot;
        <?= Date::_($ghost->removed_at, $datetime) ?>
    </small>
</div>

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
                        <td><strong><?= $this->escape($ghost->ordercode); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_ORDERDATE') ?></th>
                        <td><?= Date::_($ghost->orderdate, $datetime); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_(count($ghost->eventnames) > 1 ? 'COM_TICKETSTATION_BOXOFFICE_EVENTS' : 'COM_TICKETSTATION_BOXOFFICE_EVENT'); ?></th>
                        <td>
                            <?php foreach ($ghost->eventnames as $eventname) { ?>
                                <div><?= $this->escape($eventname); ?></div>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></th>
                        <td><?= $valuta; ?> <?= number_format($ghost->total, 2, ',', ''); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS') ?></th>
                        <td>
                            <?php if ($ghost->paid == 1) { ?>
                                <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_PAID'); ?></span>
                            <?php } elseif ($ghost->paid == 2) { ?>
                                <span class="badge bg-info"><?= Text::_('COM_TICKETSTATION_REFUNDED'); ?></span>
                            <?php } elseif ($ghost->paid == 3) { ?>
                                <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_PENDING'); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW'); ?></span>
                            <?php } ?>
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
                        <td>
                            <?php if ($ghost->client) { ?>
                                <a href="index.php?option=com_ticketstation&controller=clients&task=edit&cid=<?= (int) $ghost->client->clientid; ?>"><?= $this->escape(trim($ghost->client->firstname . ' ' . $ghost->client->name)); ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?></th>
                        <td class="text-break">
                            <?php if ($ghost->client) { ?>
                                <small><a href="mailto:<?= $this->escape($ghost->client->emailaddress); ?>"><?= $this->escape($ghost->client->emailaddress); ?></a></small>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php if ($ghost->note !== '') { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_CUSTOMER_NOTE') ?></th>
                            <td style="white-space: pre-line; overflow-wrap: anywhere;"><?= $this->escape($ghost->note); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.addTab', 'boxofficeTab', 'tickets', Text::_('COM_TICKETSTATION_TICKETS_IN_ORDER') . ' (' . count($ghost->lines) . ')'); ?>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_TICKET_ID') ?></th>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME') ?></th>
                    <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE') ?></th>
                    <th scope="col" class="text-center"><?= Text::_('COM_TICKETSTATION_SCANNED') ?></th>
                    <th scope="col" class="text-center"><?= Text::_('COM_TICKETSTATION_BOXOFFICE_BLACKLIST') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ghost->lines as $line) { ?>
                    <tr>
                        <td><?= (int) $line->orderid; ?></td>
                        <td>
                            <strong><?= $this->escape($line->eventname ?? ''); ?></strong><br />
                            <?= $this->escape($line->ticketname ?? ''); ?>
                            <?php if (!empty($line->seatid)) { ?>
                                &middot; <?= Text::_('COM_TICKETSTATION_SEAT') ?>: <?= $this->escape($line->row_name . $line->seatid); ?>
                            <?php } ?>
                        </td>
                        <td class="text-end text-nowrap"><?= $valuta; ?> <?= number_format((float) $line->price, 2, ',', ''); ?></td>
                        <td class="text-center">
                            <?php if ($line->scanned == 1) { ?>
                                <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                            <?php } else { ?>
                                <span class="text-muted">&ndash;</span>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <?php if ($line->blacklisted == 1) { ?>
                                <span class="badge bg-danger"><?= Text::_('COM_TICKETSTATION_YES'); ?></span>
                            <?php } else { ?>
                                <span class="text-muted">&ndash;</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.addTab', 'boxofficeTab', 'history', Text::_('COM_TICKETSTATION_HISTORY') . ($history ? ' (' . count($history) . ')' : '')); ?>

<div class="card">
    <div class="card-body">
        <?= LayoutHelper::render('history', ['history' => $history], JPATH_ADMINISTRATOR . '/components/com_ticketstation/tmpl/boxoffice/layouts'); ?>
    </div>
</div>

<?= HTMLHelper::_('uitab.endTab'); ?>

<?= HTMLHelper::_('uitab.endTabSet'); ?>

<input name="option" type="hidden" value="com_ticketstation" />
<input name="controller" type="hidden" value="boxoffice" />
<input name="task" type="hidden" value="" />
<input name="boxchecked" type="hidden" value="0" />
<?= HTMLHelper::_( 'form.token' ); ?>

</form>
