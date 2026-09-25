<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Invoice;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_INVOICES') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=invoices'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <th scope="col" class="w-3"><?php echo Text::_( 'COM_TICKETSTATION_INVOICE_ID' ); ?></th>
                            <th scope="col" class="w-3"><?php echo Text::_( 'COM_TICKETSTATION_ORDERCODE' ); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_( 'COM_TICKETSTATION_CLIENT' ); ?></th>
                            <th scope="col" class="w-5 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_INVOICE_AMOUNT' ); ?></th>
                            <th scope="col" class="w-6 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_ADDED' ); ?></th>
                            <th scope="col" class="w-3 text-center"><?php echo Text::_( 'COM_TICKETSTATION_SENT' ); ?></th>
                            <th scope="col" class="w-3 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_INVOICE_DOWNLOAD' ); ?></th>
                        </tr>
                    </thead>
                    <?php if (count($this->items) == 0) { ?>
                        <tr>
                            <td colspan="7" class="text-center"><?php echo Text::_( 'COM_TICKETSTATION_NO_ITEMS_FOUND' ); ?></td>
                        </tr>
                    <?php } ?>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        $row       = $this->items[$i];
                        $orderlink = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $row->ordercode;
                        $clientlink = 'index.php?option=com_ticketstation&controller=clients&task=edit&cid='.$row->userid;

                        ?>
                        <tr class="row<?= $i;?>">
                            <td><?php echo (new Invoice)->getInvoiceNumber($row->invoiceid, $this->config->invoice_prefix); ?></td>
                            <td><a href="<?php echo $orderlink; ?>"><?php echo (int) $row->ordercode; ?></a></td>
                            <td><a href="<?php echo $clientlink; ?>"><?= $row->client_firstname ?> <?= $row->client_name ?></a><br/><small><?= $row->client_email ?></small></td>
                            <td class="d-none d-md-table-cell text-center"><?php echo TicketstationFunctions::showprice($this->config->priceformat, $row->netto + $row->fees, $this->config->valuta); ?></td>
                            <td class="d-none d-md-table-cell text-center small"><?php echo Date::_($row->invoicedate, $this->config->dateformat . ' H:i'); ?></td>
                            <td class="text-center">
                                <?php if ($row->sent == 1) { ?>
                                    <span class="badge bg-success"><?php echo Text::_( 'JYES' ); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-warning"><?php echo Text::_( 'COM_TICKETSTATION_INVOICE_NOT_SENT' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="d-none d-md-table-cell text-center">
                                <a class="btn btn-sm btn-secondary" target="blank" href="<?php echo Uri::root() . 'administrator/components/com_ticketstation/invoices/' . (new Invoice)->getPdfFilename($row->invoiceid); ?>">
                                    <span class="fa fa-download"></span>
                                </a>
                            </td>
                        </tr>
                    <?php }  ?>
                </table>
            </div>
        </div>
    </div>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="invoices"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "limitstart" type="hidden" value="<?php echo $this->pagination->limitstart; ?>" />
</form>

<table width="100%" border="0" align="center" cellpadding="1" cellspacing="1">
    <tr>
        <td>
            <div align="center"><?php echo $this->pagination->getPagesLinks(); ?></div>
        </td>
    </tr>
</table>
