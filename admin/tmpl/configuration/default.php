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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CONFIGURATION_TITLE') . ' - ' . $app->get('sitename'));

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=configuration'); ?>" method="POST" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="valuta" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_CURRENCY') ?>">
                    <?= Text::_('COM_TICKETSTATION_CURRENCY') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="valuta" id="valuta"
                           class="form-control"
                           value="<?= isset($this->config->valuta)?$this->config->valuta:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_CURRENCY_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="dateformat" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_DATEFORMAT') ?>">
                    <?= Text::_('COM_TICKETSTATION_DATEFORMAT') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="dateformat" id="dateformat"
                           class="form-control"
                           value="<?= isset($this->config->dateformat)?$this->config->dateformat:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_DATEFORMAT_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="time_format" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_TIMEFORMAT') ?>">
                    <?= Text::_('COM_TICKETSTATION_TIMEFORMAT') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="time_format" id="time_format"
                           class="form-control"
                           value="<?= isset($this->config->time_format)?$this->config->time_format:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_TIMEFORMAT_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="priceformat" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PRICES') ?>">
                    <?= Text::_('COM_TICKETSTATION_PRICES') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['placeholder']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_PRICES_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_eventlistnote" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_NOTE_EVENT') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_NOTE_EVENT') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_eventlistnote']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_NOTE_EVENT_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_available_tickets" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_AVAILABILITY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_AVAILABILITY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_available_tickets']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_AVAILABILITY_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_quantity_eventlist" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_QUANTITY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_QUANTITY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_quantity_eventlist']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_QUANTITY_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_price_eventlist" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_PRICE_EVENT') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_PRICE_EVENT') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_price_eventlist']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_PRICE_EVENT_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_venue" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_VENUE') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_VENUE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_venue']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="use_coupons" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_COUPON_SYSTEM') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_COUPON_SYSTEM') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['use_coupons']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_remark_field" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_REMARKS_IN_CART') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_REMARKS_IN_CART') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_remark_field']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SHOW_REMARKS_IN_CART_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_waitinglist" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_WAITING_LIST') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_WAITING_LIST') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_waitinglist']; ?>
                    <small class="form-text text-danger">
                        <?= Text::_('COM_TICKETSTATION_SHOW_WAITING_LIST_SEATPLAN_DISCLAIMER') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_ORDER_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="next_ordercode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_NEXT_ORDERCODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_NEXT_ORDERCODE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="next_ordercode" id="next_ordercode"
                           class="form-control" maxlength="5"
                           value="<?= isset($this->config->next_ordercode)?$this->config->next_ordercode:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_NEXT_ORDERCODE_DESC') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_REMOVAL_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="remove_unfinished" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_AUTO_REMOVE') ?>">
                    <?= Text::_('COM_TICKETSTATION_AUTO_REMOVE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['remove_unfinished']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_AUTO_REMOVE_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="removal_hours" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_AUTO_REMOVE_AFTER_X_HOURS') ?>">
                    <?= Text::_('COM_TICKETSTATION_AUTO_REMOVE_AFTER_X_HOURS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['removal_hours']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_AUTO_REMOVE_AFTER_X_HOURS_DESC') ?>
                    </small>
                </div>
            </div>
            
            <div class="row mb-3">
                <label for="removal_days" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_REMOVE_AFTER_X_DAYS') ?>">
                    <?= Text::_('COM_TICKETSTATION_REMOVE_AFTER_X_DAYS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['removal_days']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_REMOVE_AFTER_X_DAYS_DESC') ?>
                    </small>
                </div>
            </div>

        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_PAYMENT_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="variable_transcosts" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_VAR_TRANSACTION_COSTS') ?>">
                    <?= Text::_('COM_TICKETSTATION_VAR_TRANSACTION_COSTS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['variable_transcosts']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_VAR_TRANSACTION_COSTS_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="transcosts" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_VAR_COSTS') ?>">
                    <?= Text::_('COM_TICKETSTATION_VAR_COSTS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="transcosts" id="transcosts"
                           class="form-control"
                           value="<?= isset($this->config->transcosts)?$this->config->transcosts:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_VAR_COSTS_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="transactioncosts" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_TRANSACTION_COSTS') ?>">
                    <?= Text::_('COM_TICKETSTATION_TRANSACTION_COSTS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="transactioncosts" id="transactioncosts"
                           class="form-control"
                           value="<?= isset($this->config->transactioncosts)?$this->config->transactioncosts:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_TRANSACTION_COSTS_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="use_euros_in_pdf" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SPECIALCHAR_IN_PDF') ?>">
                    <?= Text::_('COM_TICKETSTATION_SPECIALCHAR_IN_PDF') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['use_euros_in_pdf']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SPECIALCHAR_IN_PDF_DESC') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_CHECKOUT_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="show_salutation" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_SALUTATION') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_SALUTATION') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_salutation']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_address" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_address']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_secondaddress" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_2ND_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_2ND_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_secondaddress']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_thirdaddress" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_3RD_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_3RD_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_thirdaddress']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_zipcode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_ZIPCODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_ZIPCODE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_zipcode']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_city" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_CITY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_CITY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_city']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_country" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_COUNTRY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_COUNTRY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_country']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_phone" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_PHONE') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_PHONE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_phone']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="show_birthday" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SHOW_DAYOFBIRTH') ?>">
                    <?= Text::_('COM_TICKETSTATION_SHOW_DAYOFBIRTH') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['show_birthday']; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_PDF_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="send_multi_ticket_only" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_ONLY') ?>">
                    <?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_ONLY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['send_multi_ticket_only']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_ONLY_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="send_pdf_tickets" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_TURN_OFF_PDF_TICKETS_EMAIL') ?>">
                    <?= Text::_('COM_TICKETSTATION_TURN_OFF_PDF_TICKETS_EMAIL') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['send_pdf_tickets']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_TURN_OFF_PDF_TICKETS_EMAIL_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="send_multi_ticket_admin" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_TO_ADMIN') ?>">
                    <?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_TO_ADMIN') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['send_multi_ticket_admin']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_TO_ADMIN_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="admin_receivers_multi_ticket" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_TO_ADMIN_EMAIL') ?>">
                    <?= Text::_('COM_TICKETSTATION_SEND_MULTI_TICKET_TO_ADMIN_EMAIL') ?>
                </label>
                <div class="col-sm-9">
                    <textarea class="form-control" name="admin_receivers_multi_ticket" id="admin_receivers_multi_ticket" rows="3"><?= isset($this->config->admin_receivers_multi_ticket)?ltrim($this->config->admin_receivers_multi_ticket):null; ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_INVOICE_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="send_invoice" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_SEND_INVOICES') ?>">
                    <?= Text::_('COM_TICKETSTATION_SEND_INVOICES') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['send_invoice']; ?>
                </div>
            </div>
            <div class="row mb-3">
                <label for="invoice_prefix" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_INVOICE_PREFIX') ?>">
                    <?= Text::_('COM_TICKETSTATION_INVOICE_PREFIX') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="invoice_prefix" id="invoice_prefix"
                           class="form-control"
                           value="<?= isset($this->config->invoice_prefix)?$this->config->invoice_prefix:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="address_format_client" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_CLIENT') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_CLIENT') ?>
                </label>
                <div class="col-sm-9">
                    <textarea class="form-control" name="address_format_client" id="address_format_client" rows="4"><?= isset($this->config->address_format_client)?$this->config->address_format_client:null; ?></textarea>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_CLIENT_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="address_format_company" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_COMPANY') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_COMPANY') ?>
                </label>
                <div class="col-sm-9">
                    <textarea class="form-control" name="address_format_company" id="address_format_company" rows="4"><?= isset($this->config->address_format_company)?$this->config->address_format_company:null; ?></textarea>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_ADDRESS_FORMAT_COMPANY_DESC') ?>
                    </small>
                </div>
            </div>
            <div class="row mb-3">
                <label for="company_logo" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_COMPANY_LOGO') ?>">
                    <?= Text::_('COM_TICKETSTATION_COMPANY_LOGO') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->companyLogoField; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_COMPANY_SETTINGS') ?>
        </h3>
        <div class="card-body">
            <div class="row mb-3">
                <label for="companyname" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_COMPANYNAME') ?>">
                    <?= Text::_('COM_TICKETSTATION_COMPANYNAME') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="companyname" id="companyname"
                           class="form-control"
                           value="<?= isset($this->config->companyname)?$this->config->companyname:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="address1" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="address1" id="address1"
                           class="form-control"
                           value="<?= isset($this->config->address1)?$this->config->address1:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="address2" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ADDRESS2') ?>">
                    <?= Text::_('COM_TICKETSTATION_ADDRESS2') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="address2" id="address2"
                           class="form-control"
                           value="<?= isset($this->config->address2)?$this->config->address2:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="zipcode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_ZIPCODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_ZIPCODE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="zipcode" id="zipcode"
                           class="form-control"
                           value="<?= isset($this->config->zipcode)?$this->config->zipcode:null; ?>"/>
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
                           value="<?= isset($this->config->city)?$this->config->city:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="state" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_STATES') ?>">
                    <?= Text::_('COM_TICKETSTATION_STATES') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="state" id="state"
                           class="form-control"
                           value="<?= isset($this->config->state)?$this->config->state:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="phone" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_PHONE') ?>">
                    <?= Text::_('COM_TICKETSTATION_PHONE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="phone" id="phone"
                           class="form-control"
                           value="<?= isset($this->config->phone)?$this->config->phone:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="fax" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_FAX') ?>">
                    <?= Text::_('COM_TICKETSTATION_FAX') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="fax" id="fax"
                           class="form-control"
                           value="<?= isset($this->config->fax)?$this->config->fax:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="email" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_EMAIL') ?>">
                    <?= Text::_('COM_TICKETSTATION_EMAIL') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="email" id="email"
                           class="form-control"
                           value="<?= isset($this->config->email)?$this->config->email:null; ?>"/>
                </div>
            </div>
            <div class="row mb-3">
                <label for="website" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_WEBSITE') ?>">
                    <?= Text::_('COM_TICKETSTATION_WEBSITE') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="website" id="website"
                           class="form-control"
                           value="<?= isset($this->config->website)?$this->config->website:null; ?>"/>
                </div>
            </div>
        </div>
    </div>

    <input name="option" type="hidden" value="com_ticketstation" />
    <input name="configid" type="hidden" value="1" />
    <input name="task" type="hidden" value="" />
    <input name="boxchecked" type="hidden" value="0" />
    <input name="controller" type="hidden" value="configuration" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>