<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_MOLLIE_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');
$wa->registerAndUseStyle('searchtools', Uri::root() . 'media/templates/administrator/atum/css/system/searchtools/searchtools.css');
$wa->registerAndUseScript('passwordview', Uri::root() . 'media/system/js/fields/passwordview.js');

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=mollie'); ?>" method="POST" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_VIEW_MOLLIE_TITLE') ?>
        </h3>
        <div class="card-body">

            <div class="row mb-3">
                <label for="api_key" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY') ?>
                </label>
                <div class="col-sm-9">
                    <div class="input-group has-success">
                        <input autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" readonly onfocus="this.removeAttribute('readonly');" type="password" name="api_key" id="api_key" class="form-control input-full valid form-control-success" value="<?= isset($this->config->api_key)?$this->config->api_key:null; ?>"/>
                        <button type="button" class="btn btn-primary input-password-toggle">
                            <span class="icon-fw icon-eye" aria-hidden="true"></span>
                            <span class="visually-hidden">Show Password</span>
                        </button>
                    </div>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="api_key_test" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY_TEST') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY_TEST') ?>
                </label>
                <div class="col-sm-9">
                    <input autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other" readonly onfocus="this.removeAttribute('readonly');" type="text" name="api_key_test" id="api_key_test"
                           class="form-control"
                           value="<?= isset($this->config->api_key_test)?$this->config->api_key_test:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_API_KEY_TEST_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="test_mode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_TEST_MODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_TEST_MODE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['test_mode']; ?>
                </div>
            </div>

            <div class="row mb-3">
                <label for="bypass_mode" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_BYPASS_MODE') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_BYPASS_MODE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['bypass_mode']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_BYPASS_MODE_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="trans_cost" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_TRANS_COSTS') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_TRANS_COSTS') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="trans_cost" id="trans_cost"
                           class="form-control"
                           value="<?= isset($this->config->trans_cost)?$this->config->trans_cost:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_TRANS_COSTS_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="description" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_DESCRIPTION') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_DESCRIPTION') ?>
                </label>
                <div class="col-sm-9">
                    <input type="text" name="description" id="description"
                           class="form-control"
                           value="<?= isset($this->config->description)?$this->config->description:null; ?>"/>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_DESCRIPTION_DESC') ?>
                    </small>
                </div>
            </div>

            <div class="row mb-3">
                <label for="mollie_language" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_LANGUAGE') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_LANGUAGE') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['mollie_language']; ?>
                </div>
            </div>

            <div class="row mb-3">
                <label for="send_tickets_directly" class="col-sm-3 col-form-label"
                       rel="popover"
                       title="<?= Text::_('COM_TICKETSTATION_MOLLIE_SEND_TICKETS_DIRECTLY') ?>">
                    <?= Text::_('COM_TICKETSTATION_MOLLIE_SEND_TICKETS_DIRECTLY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['send_tickets_directly']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_MOLLIE_SEND_TICKETS_DIRECTLY_DESC') ?>
                    </small>
                </div>
            </div>

        </div>
    </div>

    <input name="option" type="hidden" value="com_ticketstation" />
    <input name="configid" type="hidden" value="1" />
    <input name="task" type="hidden" value="" />
    <input name="boxchecked" type="hidden" value="0" />
    <input name="controller" type="hidden" value="mollie" />
</form>