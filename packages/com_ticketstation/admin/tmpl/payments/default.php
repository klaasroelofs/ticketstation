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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_PAYMENTS_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

$off = $this->config->payment_provider === '';

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=payments'); ?>" method="POST" name="adminForm" id="adminForm">

    <input name="option" type="hidden" value="com_ticketstation" />
    <input name="task" type="hidden" value="" />
    <input name="controller" type="hidden" value="payments" />
    <input name="extension_id" type="hidden" value="0" />
    <?= HTMLHelper::_('form.token'); ?>

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_VIEW_PAYMENTS_TITLE') ?>
        </h3>
        <div class="card-body">

            <?php if ($this->providerMissing) : ?>
                <div class="alert alert-danger">
                    <?= Text::sprintf('COM_TICKETSTATION_PAYMENTS_PROVIDER_MISSING', $this->escape($this->config->payment_provider)) ?>
                </div>
            <?php endif; ?>

            <div class="row mb-3">
                <label for="payment_provider" class="col-sm-3 col-form-label">
                    <?= Text::_('COM_TICKETSTATION_PAYMENTS_PROVIDER') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['payment_provider']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_PAYMENTS_PROVIDER_DESC') ?>
                    </small>
                    <?php if ($this->pending > 0) : ?>
                        <div id="ts-payments-pending" class="alert alert-warning mt-2 mb-0"<?= $off ? '' : ' hidden' ?>>
                            <?= Text::plural('COM_TICKETSTATION_PAYMENTS_PENDING', $this->pending) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <label for="payment_currency" class="col-sm-3 col-form-label">
                    <?= Text::_('COM_TICKETSTATION_PAYMENTS_CURRENCY') ?>
                </label>
                <div class="col-sm-9">
                    <?= $this->lists['payment_currency']; ?>
                    <small class="form-text">
                        <?= Text::_('COM_TICKETSTATION_PAYMENTS_CURRENCY_DESC') ?>
                    </small>
                    <?php if (!isset(($this->currencyMap[$this->config->payment_provider] ?? [])[$this->config->payment_currency])) : ?>
                        <div class="alert alert-warning mt-2 mb-0">
                            <?= Text::sprintf('COM_TICKETSTATION_PAYMENTS_CURRENCY_NOT_SUPPORTED_NOW', $this->escape($this->config->payment_currency)) ?>
                        </div>
                    <?php endif; ?>
                    <div id="ts-currency-note" class="alert alert-info mt-2 mb-0" hidden></div>
                </div>
            </div>

        </div>
    </div>

    <div class="card mt-3 rounded-to">
        <h3 class="card-header">
            <?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGINS_TITLE') ?>
        </h3>
        <div class="card-body">
            <p><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGINS_INTRO') ?></p>

            <?php if (!$this->plugins) : ?>
                <div class="alert alert-info"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGINS_NONE') ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_VERSION') ?></th>
                                <th scope="col"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_STATUS') ?></th>
                                <th scope="col" class="text-end"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_ACTIONS') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->plugins as $plugin) : ?>
                                <tr>
                                    <td>
                                        <strong><?= $this->escape($plugin->name) ?></strong>
                                        <?php if ($plugin->description !== '') : ?>
                                            <br /><small class="text-muted"><?= $this->escape($plugin->description) ?></small>
                                        <?php endif; ?>
                                        <?php foreach ($plugin->warnings as $warning) : ?>
                                            <br /><small class="text-danger"><span class="fa fa-exclamation-circle" aria-hidden="true"></span> <?= Text::_($warning['key']) ?></small>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?= $this->escape($plugin->version) ?></td>
                                    <td>
                                        <?php if ($plugin->enabled) : ?>
                                            <span class="badge bg-success"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_ENABLED_STATE') ?></span>
                                        <?php else : ?>
                                            <span class="badge bg-secondary"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_DISABLED_STATE') ?></span>
                                        <?php endif; ?>
                                        <?php if ($plugin->enabled && !$plugin->available) : ?>
                                            <span class="badge bg-warning text-dark" title="<?= $this->escape(Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_NO_PROVIDER_DESC')) ?>"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_NO_PROVIDER') ?></span>
                                        <?php endif; ?>
                                        <?php if ($plugin->active) : ?>
                                            <span class="badge bg-primary"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_ACTIVE') ?></span>
                                        <?php endif; ?>
                                        <?php if ($plugin->available && !$plugin->configured) : ?>
                                            <span class="badge bg-warning text-dark"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_NOT_CONFIGURED') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-primary" href="<?= Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . $plugin->extension_id) ?>">
                                            <span class="fa fa-cog" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_SETTINGS') ?>
                                        </a>
                                        <?php if ($plugin->enabled) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="tsPaymentPlugin('unpublish', <?= $plugin->extension_id ?>)">
                                                <span class="fa fa-power-off" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_DISABLE') ?>
                                            </button>
                                        <?php else : ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="tsPaymentPlugin('publish', <?= $plugin->extension_id ?>)">
                                                <span class="fa fa-power-off" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_ENABLE') ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <p class="mb-0 text-muted"><?= Text::_('COM_TICKETSTATION_PAYMENTS_PLUGINS_MORE') ?></p>
        </div>
    </div>
</form>

<script>
    // Switching a payment plugin on or off: the same form, with the plugin in a hidden field.
    function tsPaymentPlugin(task, id) {
        var form = document.getElementById('adminForm');
        form.elements['task'].value = task;
        form.elements['extension_id'].value = id;
        form.submit();
    }

    (function () {
        var select = document.getElementById('payment_provider');
        var pending = document.getElementById('ts-payments-pending');
        var currency = document.getElementById('payment_currency');
        var note = document.getElementById('ts-currency-note');
        var currencies = <?= json_encode($this->currencyMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        var changedText = <?= json_encode(Text::_('COM_TICKETSTATION_PAYMENTS_CURRENCY_CHANGED'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

        if (!select) {
            return;
        }

        select.addEventListener('change', function () {
            // The orders that wait for payment can't be paid while online payments are off.
            if (pending) {
                pending.hidden = this.value !== '';
            }

            // Only the currencies the chosen provider can collect; keep the chosen one when it is among them.
            var allowed = currencies[this.value] || currencies[''];
            var codes = Object.keys(allowed);
            var before = currency.value;

            currency.innerHTML = '';

            codes.forEach(function (code) {
                var option = document.createElement('option');
                option.value = code;
                option.textContent = code + ' - ' + allowed[code];
                currency.appendChild(option);
            });

            if (codes.indexOf(before) !== -1) {
                currency.value = before;
                note.hidden = true;
            } else if (codes.length) {
                currency.value = codes[0];
                note.textContent = changedText.replace('%s', codes[0]).replace('%s', before);
                note.hidden = false;
            }
        });
    })();
</script>
