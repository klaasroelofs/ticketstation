<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 * TODO: change layout to Joomla standard
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CUSTOMER_DETAILS') . ' - ' . $app->get('sitename'));

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=clients&task=edit&cid=' . (int) $this->data->clientid); ?>" method="POST" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_VIEW_CUSTOMER_DETAILS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="firstname" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_FIRSTNAME') ?>">
                    <?= Text::_('COM_TICKETSTATION_FIRSTNAME') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="firstname" id="firstname"
                           class="form-control"
                           value="<?= isset($this->data->firstname)?$this->data->firstname:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="name" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_LASTNAME') ?>">
                    <?= Text::_('COM_TICKETSTATION_LASTNAME') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="name" id="name"
                           class="form-control"
                           value="<?= isset($this->data->name)?$this->data->name:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="address" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="address" id="address"
                           class="form-control"
                           value="<?= isset($this->data->address)?$this->data->address:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="zipcode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ZIP') ?>">
                    <?= Text::_('COM_TICKETSTATION_ZIP') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="zipcode" id="zipcode"
                           class="form-control"
                           value="<?= isset($this->data->zipcode)?$this->data->zipcode:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="city" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_CITY') ?>">
                    <?= Text::_('COM_TICKETSTATION_CITY') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="city" id="city"
                           class="form-control"
                           value="<?= isset($this->data->city)?$this->data->city:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="phonenumber" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?>">
                    <?= Text::_('COM_TICKETSTATION_PHONENUMBER') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="phonenumber" id="phonenumber"
                           class="form-control"
                           value="<?= isset($this->data->phonenumber)?$this->data->phonenumber:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="emailaddress" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_EMAILADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="emailaddress" id="emailaddress"
                           class="form-control"
                           value="<?= isset($this->data->emailaddress)?$this->data->emailaddress:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="ipaddress" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_IP_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_IP_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="ipaddress" id="ipaddress"
                           class="form-control" disabled
                           value="<?= isset($this->data->ipaddress)?$this->data->ipaddress:null; ?>"/>
                </div>
            </div>
        </div>
    </div>



    <div class="card">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_CLIENT_ORDER_HISTORY') ?>
        </h3>
        <div class="card-body">
            <?php if (count($this->items) > 0) { ?>
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col" class="w-3"><?= Text::_( 'COM_TICKETSTATION_ORDERCODE' ); ?></th>
                        <th scope="col" class="w-10"><?= Text::_( 'COM_TICKETSTATION_ORDER_INFORMATION' ); ?></th>
                        <th scope="col" class="w-3 text-center"><?= Text::_( 'COM_TICKETSTATION_ORDERDATE' ); ?></th>
                        <th scope="col" class="w-3 d-none d-md-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_TOTAL_TICKETS_2' ); ?></th>
                        <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE' ); ?></th>
                        <th scope="col" class="w-3 d-none d-md-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS' ); ?></th>
                    </tr>
                    </thead>

                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ) {

                        ## Give give $row the this->item[$i]
                        $row        = &$this->items[$i];
                        $link       = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid='.$row->ordercode;

                        if ($row->transaction_amount != 0)
                        {
                            $orderprice = $row->transaction_amount;
                        }
                        else
                        {
                            $orderprice = $row->orderprice;
                        }

                        ?>
                        <tr class="<?= "row-$i"; ?>">
                            <td><a href="<?= $link;?>"><?= $row->ordercode; ?></a></td>
                            <td>
                                <?= $row->eventcode;?> | <?= $row->ticketname;?> <br />
                                <?php if ($row->remarks != '') { ?>
                                    <span class="badge bg-info" style="border: 1px solid #000;background-color:#f5a742;font-size:10pt;margin-top:5px;"><?= $row->remarks; ?></span>
                                <?php } ?>
                            </td>
                            <td class="w-3 text-center" style="text-align: center;"><small><?= date ("d-m-Y", strtotime($row->orderdate)); ?></small></td>
                            <td class="w-3 d-none d-md-table-cell text-center" style="text-align: center;"><?= $row->totaltickets; ?></td>
                            <td class="w-3 d-none d-lg-table-cell text-center" style="text-align: center;"><?= $this->config->valuta; ?> <?= number_format($orderprice, 2, ',', ''); ?></td>
                            <td class="w-3 d-none d-md-table-cell text-center" style="text-align: center;">
                                <?php if ($row->paid == 1){ ?>
                                    <span class="badge bg-success"><?= Text::_( 'COM_TICKETSTATION_PAID' ); ?></span>
                                <?php } elseif ($row->paid == 2) { ?>
                                    <span class="badge bg-info" ><?= Text::_( 'COM_TICKETSTATION_REFUNDED' ); ?></span>
                                <?php } elseif($row->paid == 3) { ?>
                                    <span class="badge bg-warning"><?= Text::_( 'COM_TICKETSTATION_PENDING' ); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-danger" ><?= Text::_( 'COM_TICKETSTATION_UNPAID_OVERVIEW' ); ?></span>
                                <?php } ?>
                            </td>

                        </tr>
                    <?php } ?>

                </table>
            <?php } else { ?>
                <h4 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CLIENT_NO_ORDERS') ?>
                </h4>
            <?php } ?>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="clients" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="clientid" value="<?php echo $this->data->clientid; ?>" />


</form>
		
