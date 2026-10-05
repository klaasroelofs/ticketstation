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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CONFIGURATION_TITLE') . ' - ' . $app->get('sitename'));

// The venue address/description/website options only apply when the location line itself is shown,
// so hide them while show_venue is set to No. Transaction costs are either a percentage or a fixed
// amount, so only the field of the chosen kind is shown.
$document->getWebAssetManager()->addInlineScript("
    document.addEventListener('DOMContentLoaded', function () {
        var showVenue = document.getElementById('show_venue');
        var subOptions = document.getElementById('venue-sub-options');
        if (showVenue && subOptions) {
            var toggle = function () {
                subOptions.style.display = showVenue.value === '1' ? '' : 'none';
            };
            showVenue.addEventListener('change', toggle);
            toggle();
        }

        var costsKind = document.getElementById('variable_transcosts');
        var variableRow = document.getElementById('transcosts');
        var fixedRow = document.getElementById('transactioncosts');
        if (costsKind && variableRow && fixedRow) {
            variableRow = variableRow.closest('.row');
            fixedRow = fixedRow.closest('.row');
            var toggleCosts = function () {
                variableRow.style.display = costsKind.value === '1' || costsKind.value === '3' ? '' : 'none';
                fixedRow.style.display = costsKind.value === '0' || costsKind.value === '3' ? '' : 'none';
            };
            costsKind.addEventListener('change', toggleCosts);
            toggleCosts();
        }
    });
");

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=configuration'); ?>" method="POST" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <?= HTMLHelper::_('uitab.startTabSet', 'configTabs', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'general', Text::_('COM_TICKETSTATION_CONFIG_TAB_GENERAL')); ?>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_FORMAT_SETTINGS') ?>
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
                        <?= $this->lists['dateformat']; ?>
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
                        <?= $this->lists['time_format']; ?>
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
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_SHOP_FEATURE_SETTINGS') ?>
            </h3>
            <div class="card-body">
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

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'display', Text::_('COM_TICKETSTATION_CONFIG_TAB_DISPLAY')); ?>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_EVENTLIST_SETTINGS') ?>
            </h3>
            <div class="card-body">
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
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_TICKETPAGE_SETTINGS') ?>
            </h3>
            <div class="card-body">
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
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_VENUE_SETTINGS') ?>
            </h3>
            <div class="card-body">
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
                <div id="venue-sub-options" class="ms-4">
                    <div class="row mb-3">
                        <label for="show_venue_address" class="col-sm-3 col-form-label"
                               rel="popover"
                               title="<?= Text::_('COM_TICKETSTATION_SHOW_VENUE_ADDRESS') ?>">
                            <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_ADDRESS') ?>
                        </label>
                        <div class="col-sm-9">
                            <?= $this->lists['show_venue_address']; ?>
                            <small class="form-text">
                                <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_ADDRESS_DESC') ?>
                            </small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="show_venue_description" class="col-sm-3 col-form-label"
                               rel="popover"
                               title="<?= Text::_('COM_TICKETSTATION_SHOW_VENUE_DESCRIPTION') ?>">
                            <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_DESCRIPTION') ?>
                        </label>
                        <div class="col-sm-9">
                            <?= $this->lists['show_venue_description']; ?>
                            <small class="form-text">
                                <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_DESCRIPTION_DESC') ?>
                            </small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="show_venue_website" class="col-sm-3 col-form-label"
                               rel="popover"
                               title="<?= Text::_('COM_TICKETSTATION_SHOW_VENUE_WEBSITE') ?>">
                            <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_WEBSITE') ?>
                        </label>
                        <div class="col-sm-9">
                            <?= $this->lists['show_venue_website']; ?>
                            <small class="form-text">
                                <?= Text::_('COM_TICKETSTATION_SHOW_VENUE_WEBSITE_DESC') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_SEARCH_ENGINE_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="show_jsonld" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_SHOW_JSONLD') ?>">
                        <?= Text::_('COM_TICKETSTATION_SHOW_JSONLD') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['show_jsonld']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_SHOW_JSONLD_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'orders', Text::_('COM_TICKETSTATION_CONFIG_TAB_ORDERS')); ?>

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
                               class="form-control" maxlength="6" inputmode="numeric" pattern="[1-9][0-9]{0,5}"
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
                <?= Text::_('COM_TICKETSTATION_REMINDER_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="reminder_on" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_REMINDER_ON') ?>">
                        <?= Text::_('COM_TICKETSTATION_REMINDER_ON') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['reminder_on']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_REMINDER_ON_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="reminder_hours" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_REMINDER_HOURS') ?>">
                        <?= Text::_('COM_TICKETSTATION_REMINDER_HOURS') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['reminder_hours']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_REMINDER_HOURS_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'checkout', Text::_('COM_TICKETSTATION_CONFIG_TAB_CHECKOUT')); ?>

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
                    <label for="send_tickets_directly" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_SEND_TICKETS_DIRECTLY') ?>">
                        <?= Text::_('COM_TICKETSTATION_SEND_TICKETS_DIRECTLY') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['send_tickets_directly']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_SEND_TICKETS_DIRECTLY_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'documents', Text::_('COM_TICKETSTATION_CONFIG_TAB_DOCUMENTS')); ?>

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

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'company', Text::_('COM_TICKETSTATION_CONFIG_TAB_COMPANY')); ?>

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
                <div class="row mb-3">
                    <label for="terms_url" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_CONFIG_TERMS_URL') ?>">
                        <?= Text::_('COM_TICKETSTATION_CONFIG_TERMS_URL') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="terms_url" id="terms_url"
                               class="form-control"
                               value="<?= htmlspecialchars($this->config->terms_url ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_CONFIG_TERMS_URL_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="privacy_url" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_CONFIG_PRIVACY_URL') ?>">
                        <?= Text::_('COM_TICKETSTATION_CONFIG_PRIVACY_URL') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="privacy_url" id="privacy_url"
                               class="form-control"
                               value="<?= htmlspecialchars($this->config->privacy_url ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_CONFIG_PRIVACY_URL_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_MAIL_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="from_name" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_CONFIG_FROM_NAME') ?>">
                        <?= Text::_('COM_TICKETSTATION_CONFIG_FROM_NAME') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="from_name" id="from_name"
                               class="form-control"
                               value="<?= htmlspecialchars($this->config->from_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_CONFIG_FROM_NAME_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="from_email" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_CONFIG_FROM_EMAIL') ?>">
                        <?= Text::_('COM_TICKETSTATION_CONFIG_FROM_EMAIL') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="email" name="from_email" id="from_email"
                               class="form-control"
                               value="<?= htmlspecialchars($this->config->from_email ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_CONFIG_FROM_EMAIL_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.addTab', 'configTabs', 'wallet', Text::_('COM_TICKETSTATION_CONFIG_TAB_WALLET')); ?>

        <p class="mt-3">
            <?= Text::_('COM_TICKETSTATION_WALLET_CONFIG_INTRO') ?>
            <a href="<?= Route::_('index.php?option=com_ticketstation&view=docs#docs-wallet') ?>" target="blank"><?= Text::_('COM_TICKETSTATION_WALLET_CONFIG_DOCS_LINK') ?></a>
        </p>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="wallet_apple" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_ENABLE') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['wallet_apple']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_ENABLE_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_CERTIFICATE') ?>
                    </div>
                    <div class="col-sm-9">
                        <p class="form-control-plaintext">
                            <?php if ($this->walletApple === null) { ?>
                                <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_NO_CERTIFICATE') ?>
                            <?php } elseif ($this->walletApple['expired']) { ?>
                                <strong class="text-danger"><?= Text::sprintf('COM_TICKETSTATION_WALLET_APPLE_CERTIFICATE_EXPIRED_ON',
                                    $this->escape($this->walletApple['passTypeId']),
                                    HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $this->walletApple['validTo']), Text::_('DATE_FORMAT_LC4'))) ?></strong>
                            <?php } else { ?>
                                <?= Text::sprintf('COM_TICKETSTATION_WALLET_APPLE_CERTIFICATE_VALID',
                                    '<code>' . $this->escape($this->walletApple['passTypeId']) . '</code>',
                                    $this->escape($this->walletApple['teamId']),
                                    HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $this->walletApple['validTo']), Text::_('DATE_FORMAT_LC4'))) ?>
                            <?php } ?>
                            <?php if ($this->walletApplePending) { ?>
                                <br><?= Text::_('COM_TICKETSTATION_WALLET_APPLE_REQUEST_WAITING') ?>
                            <?php } ?>
                        </p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_STEP_REQUEST') ?>
                    </div>
                    <div class="col-sm-9">
                        <a class="btn btn-outline-secondary" href="<?= $this->walletCsrLink ?>">
                            <span class="icon-download" aria-hidden="true"></span>
                            <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_CREATE_REQUEST') ?>
                        </a>
                        <small class="form-text d-block">
                            <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_CREATE_REQUEST_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="wallet_apple_cer" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_STEP_CERTIFICATE') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="file" name="wallet_apple_cer" id="wallet_apple_cer" class="form-control" accept=".cer,.crt,.pem" />
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_UPLOAD_CER_DESC') ?>
                        </small>
                    </div>
                </div>
                <details class="mb-3">
                    <summary><?= Text::_('COM_TICKETSTATION_WALLET_APPLE_P12_TITLE') ?></summary>
                    <div class="mt-3">
                        <div class="row mb-3">
                            <label for="wallet_apple_p12" class="col-sm-3 col-form-label">
                                <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_P12') ?>
                            </label>
                            <div class="col-sm-9">
                                <input type="file" name="wallet_apple_p12" id="wallet_apple_p12" class="form-control" accept=".p12,.pfx" />
                                <small class="form-text">
                                    <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_P12_DESC') ?>
                                </small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="wallet_apple_p12_password" class="col-sm-3 col-form-label">
                                <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_P12_PASSWORD') ?>
                            </label>
                            <div class="col-sm-9">
                                <input type="password" name="wallet_apple_p12_password" id="wallet_apple_p12_password" class="form-control" autocomplete="new-password" />
                            </div>
                        </div>
                    </div>
                </details>
                <?php if ($this->walletApple !== null || $this->walletApplePending) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="wallet_apple_remove" id="wallet_apple_remove" value="1" />
                        <label class="form-check-label" for="wallet_apple_remove">
                            <?= Text::_('COM_TICKETSTATION_WALLET_APPLE_REMOVE') ?>
                        </label>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="wallet_google" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_ENABLE') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->lists['wallet_google']; ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_ENABLE_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="wallet_google_issuer_id" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_ISSUER_ID') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="wallet_google_issuer_id" id="wallet_google_issuer_id"
                               class="form-control" inputmode="numeric" maxlength="25"
                               value="<?= $this->escape($this->config->wallet_google_issuer_id ?? ''); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_ISSUER_ID_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="wallet_google_json" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_KEY') ?>
                    </label>
                    <div class="col-sm-9">
                        <p class="form-control-plaintext pt-0">
                            <?php if ($this->walletGoogle === null) { ?>
                                <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_NO_KEY') ?>
                            <?php } else { ?>
                                <?= Text::sprintf('COM_TICKETSTATION_WALLET_GOOGLE_KEY_STORED', '<code>' . $this->escape($this->walletGoogle['client_email']) . '</code>') ?>
                            <?php } ?>
                        </p>
                        <input type="file" name="wallet_google_json" id="wallet_google_json" class="form-control" accept=".json,application/json" />
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_KEY_DESC') ?>
                        </small>
                    </div>
                </div>
                <?php if ($this->walletGoogle !== null) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="wallet_google_remove" id="wallet_google_remove" value="1" />
                        <label class="form-check-label" for="wallet_google_remove">
                            <?= Text::_('COM_TICKETSTATION_WALLET_GOOGLE_REMOVE') ?>
                        </label>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card mt-3 rounded-to">
            <h3 class="card-header">
                <?= Text::_('COM_TICKETSTATION_WALLET_LOOK_SETTINGS') ?>
            </h3>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="wallet_logo" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_LOGO') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $this->walletLogoField; ?>
                        <?php if ($this->walletLogoProblem !== null) { ?>
                            <div class="alert alert-warning mt-2 mb-1"><?= Text::_($this->walletLogoProblem) ?></div>
                        <?php } ?>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_LOGO_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="wallet_bg_color" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_BG_COLOR') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="color" name="wallet_bg_color" id="wallet_bg_color"
                               class="form-control form-control-color"
                               value="<?= $this->escape($this->walletColors[0]); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_BG_COLOR_DESC') ?>
                        </small>
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="wallet_fg_color" class="col-sm-3 col-form-label">
                        <?= Text::_('COM_TICKETSTATION_WALLET_FG_COLOR') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="color" name="wallet_fg_color" id="wallet_fg_color"
                               class="form-control form-control-color"
                               value="<?= $this->escape($this->walletColors[1]); ?>"/>
                        <small class="form-text">
                            <?= Text::_('COM_TICKETSTATION_WALLET_FG_COLOR_DESC') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    <?= HTMLHelper::_('uitab.endTab'); ?>

    <?= HTMLHelper::_('uitab.endTabSet'); ?>

    <input name="option" type="hidden" value="com_ticketstation" />
    <input name="configid" type="hidden" value="1" />
    <input name="task" type="hidden" value="" />
    <input name="boxchecked" type="hidden" value="0" />
    <input name="controller" type="hidden" value="configuration" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>