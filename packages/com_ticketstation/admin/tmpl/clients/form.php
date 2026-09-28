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
            <?= $this->form->renderFieldset('details'); ?>
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

                        ?>
                        <tr class="<?= "row-$i"; ?>">
                            <td><a href="<?= $link;?>"><?= $row->ordercode; ?></a></td>
                            <td>
                                <?= $this->escape($row->eventcode); ?> | <?= $this->escape($row->ticketname); ?> <br />
                                <?php if ($row->remarks != '') { ?>
                                    <span class="badge bg-info" style="border: 1px solid #000;background-color:#f5a742;font-size:10pt;margin-top:5px;"><?= $this->escape($row->remarks); ?></span>
                                <?php } ?>
                            </td>
                            <td class="w-3 text-center" style="text-align: center;"><small><?= date ("d-m-Y", strtotime($row->orderdate)); ?></small></td>
                            <td class="w-3 d-none d-md-table-cell text-center" style="text-align: center;"><?= $row->totaltickets; ?></td>
                            <td class="w-3 d-none d-lg-table-cell text-center" style="text-align: center;"><?= $this->config->valuta; ?> <?= number_format($row->orderprice, 2, ',', ''); ?></td>
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
    <input type="hidden" name="clientid" value="<?php echo (int) $this->data->clientid; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>
		
