<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_TRANSACTION_DETAILS') . ' - ' . $app->get('sitename'));

$data     = $this->data;
$valuta   = $this->escape($this->config->valuta);
$datetime = $this->config->dateformat . ' ' . $this->config->time_format;
$method   = $data->type !== '' ? ProviderRegistry::methodLabel(strtolower((string) $data->type), (string) ($data->provider ?? '')) : '';
$provider = ProviderRegistry::providerLabel((string) ($data->provider ?? ''));

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=transactions&task=edit&cid=' . (int) $data->pid); ?>" method="post" name="adminForm" id="adminForm">

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="mb-0"><?= Text::_('COM_TICKETSTATION_TRANSACTION_PAYMENT') ?></h3>
        </div>
        <div class="card-body">
            <table class="table mb-0">
                <tbody>
                    <tr>
                        <th scope="row" class="fw-normal w-25"><?= Text::_('COM_TICKETSTATION_ORDERCODE') ?></th>
                        <td><a href="index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=<?= (int) $data->orderid; ?>"><?= (int) $data->orderid; ?></a></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_CLIENT') ?></th>
                        <td><a href="index.php?option=com_ticketstation&controller=clients&task=edit&cid=<?= (int) $data->userid; ?>"><?= $this->escape($data->name ?? ''); ?></a></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_DATE') ?></th>
                        <td><?= $this->escape(Date::screen($data->date, $datetime)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_TRANSACTION_AMOUNT') ?></th>
                        <td><?= Price::format((float) $data->amount, $valuta); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_PAYMENT_TYPE') ?></th>
                        <td><?= $this->escape($method); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_PAYMENT_PROVIDER') ?></th>
                        <td>
                            <?= $this->escape($provider); ?>
                            <?php if ($this->providerMode !== '') { ?>
                                <span class="badge <?= $this->providerMode === 'test' ? 'bg-warning text-dark' : 'bg-success'; ?> ms-1"><?= Text::_('COM_TICKETSTATION_TRANSACTION_MODE_' . strtoupper($this->providerMode)); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php if ($this->providerPaymentId !== '') { ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_TRANSACTION_PROVIDER_PAYMENT_ID') ?></th>
                            <td><code><?= $this->escape($this->providerPaymentId); ?></code></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?= Text::_('COM_TICKETSTATION_PID') ?></th>
                        <td><?= (int) $data->pid; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($this->refunds) { ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="mb-0"><?= Text::_('COM_TICKETSTATION_REFUNDS') ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_DATE') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_TYPE') ?></th>
                                <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_REFUND_AMOUNT') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_REFUND_STATUS') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->refunds as $refund) {
                                $counts = Refund::counts($refund->status);
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= $this->escape(Date::screen($refund->created, $datetime)); ?></td>
                                    <td><?= Text::_($refund->type === 'chargeback' ? 'COM_TICKETSTATION_REFUND_TYPE_CHARGEBACK' : 'COM_TICKETSTATION_REFUND_TYPE_REFUND'); ?></td>
                                    <td class="text-end text-nowrap<?= $counts ? '' : ' text-decoration-line-through text-muted'; ?>"><?= Price::format((float) $refund->amount, $valuta); ?></td>
                                    <td><span class="badge <?= $counts ? 'bg-info' : 'bg-danger'; ?>"><?= Text::_('COM_TICKETSTATION_REFUND_STATUS_' . strtoupper($refund->status)); ?></span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0"><?= Text::_('COM_TICKETSTATION_TRANSACTION_REFUNDS_NOTE') ?></p>
            </div>
        </div>
    <?php } ?>

    <?php if ($this->providerData) { ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="mb-0"><?= Text::_('COM_TICKETSTATION_MOLLIE_INFORMATION') ?></h3>
            </div>
            <div class="card-body">
                <details>
                    <summary class="mb-2"><?= Text::sprintf('COM_TICKETSTATION_TRANSACTION_PROVIDER_DATA_SHOW', count($this->providerData)) ?></summary>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($this->providerData as $key => $value) { ?>
                                    <tr>
                                        <th scope="row" class="fw-normal w-25"><?= $this->escape($key); ?></th>
                                        <td class="text-break"><?= $this->escape($value); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    <?php } ?>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="controller" value="transactions" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>
