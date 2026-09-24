<?php

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_TRANSACTION_DETAILS') . ' - ' . $app->get('sitename'));

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=transactions&task=edit&cid=' . (int) $this->data->pid); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <label for="transid" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PID') ?>">
                    <?= Text::_('COM_TICKETSTATION_PID') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="transid" id="transid"
                           class="form-control" disabled
                           value="<?= isset($this->data->pid)?$this->data->pid:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="date" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_DATE') ?>">
                    <?= Text::_('COM_TICKETSTATION_DATE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="date" id="date"
                           class="form-control" disabled
                           value="<?= isset($this->data->date)?date ("d-m-Y H:i", strtotime($this->data->date)):null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="type" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PAYMENT_TYPE') ?>">
                    <?= Text::_('COM_TICKETSTATION_PAYMENT_TYPE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="type" id="type"
                           class="form-control" disabled
                           value="<?= isset($this->data->type)?$this->data->type:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="client" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_CLIENT') ?>">
                    <?= Text::_('COM_TICKETSTATION_CLIENT') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="client" id="client"
                           class="form-control" disabled
                           value="<?= isset($this->data->name)?$this->data->firstname:null; ?> <?php echo $this->data->name;?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="amount" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_TRANSACTION_AMOUNT') ?>">
                    <?= Text::_('COM_TICKETSTATION_TRANSACTION_AMOUNT') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="amount" id="amount"
                           class="form-control" disabled
                           value="<?= isset($this->data->amount)?$this->config->valuta.' '.number_format($this->data->amount, 2, ',', ' '):null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="ordercode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ORDERCODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_ORDERCODE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="ordercode" id="ordercode"
                           class="form-control" disabled
                           value="<?= isset($this->data->orderid)?$this->data->orderid:null; ?>"/>
                </div>
            </div>
            <div class="row mb-1">
                <div class="row-fluid">
                    <div class="span5">
                        <h3><b><?php echo Text::_('COM_TICKETSTATION_MOLLIE_INFORMATION'); ?></b></h3>
                        <table>
                            <?php parse_str($this->data->details, $paymentdetails); ?>
                            <?php foreach ($paymentdetails as $key => $value) { ?>
                                <?php if (!is_array($value)) { ?>
                                    <tr>
                                        <td style="width: 125px;"><?php echo $key;?></td>
                                        <td><?php echo $value;?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </table>

                        <?php foreach ($paymentdetails as $key => $value) { ?>
                            <?php if (is_array($value)) { ?>
                                <h4><b><?php echo $key;?></b></h4>
                                <table>
                                    <?php foreach ($value as $key2 => $value2) { ?>
                                        <tr>
                                            <td style="width: 125px;"><?php echo $key2;?></td>
                                            <td><?php echo $value2;?></td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
             </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="controller" value="transactions" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>

