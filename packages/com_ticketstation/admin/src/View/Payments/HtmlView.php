<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Payments;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * Ticketstation Payments Admin View
 */
class HtmlView extends BaseHtmlView
{
    /** @var object  payment_provider and payment_currency */
    public $config;

    /** @var object[]  The installed payment plugins. */
    public $plugins = [];

    /** @var int  The number of orders waiting for payment. */
    public $pending = 0;

    /** @var array  Select lists by name. */
    public $lists = [];

    /** @var bool  Whether the chosen provider's plugin is off or gone. */
    public $providerMissing = false;

    /** @var bool  Whether to advise the admin to show prices without decimals. */
    public $decimalsHint = false;

    /** @var array  Provider id => the currencies it allows (ISO code => name); the empty id is Off. */
    public $currencyMap = [];

    public function display($tpl = null)
    {
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_PAYMENTS_TITLE'), 'fa-regular fa-money-bill-wave');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolbarHelper::cancel();
        Docs::toolbarButton('payments');

        $model = $this->getModel('Payments');

        $this->config  = $model->getData();
        $this->plugins = $model->getPlugins();
        $this->pending = $model->getPending();

        // A currency without decimals shows prices without them only when Configuration > Prices says so.
        $this->decimalsHint = PaymentCurrencies::digits($this->config->payment_currency) === 0
            && (int) ((new Config)->getPartialConfig(['price_decimals'])->price_decimals ?? 2) === 2;

        // Only providers that are available can be chosen; a chosen one that isn't stays in the
        // list, flagged, so the screen doesn't silently turn online payments off.
        $options = [['value' => '', 'text' => Text::_('COM_TICKETSTATION_PAYMENTS_PROVIDER_NONE')]];

        foreach (ProviderRegistry::all() as $id => $provider) {
            $options[] = ['value' => $id, 'text' => $provider->getTitle()];
        }

        $this->providerMissing = $this->config->payment_provider !== '' && ProviderRegistry::get($this->config->payment_provider) === null;

        if ($this->providerMissing) {
            $options[] = ['value' => $this->config->payment_provider, 'text' => Text::sprintf('COM_TICKETSTATION_PAYMENTS_PROVIDER_UNAVAILABLE', $this->config->payment_provider)];
        }

        $this->lists['payment_provider'] = HTMLHelper::_('select.genericList', $options, 'payment_provider', ' class="form-select"',
            'value', 'text', $this->config->payment_provider);

        // The currencies each choice of provider allows, for the list and for the script that
        // refreshes it when another provider is chosen ('' = Off: every currency).
        $this->currencyMap = ['' => PaymentCurrencies::CURRENCIES];

        foreach (ProviderRegistry::all() as $id => $provider) {
            $this->currencyMap[$id] = PaymentCurrencies::forProvider($provider);
        }

        $allowed    = $this->currencyMap[$this->config->payment_provider] ?? PaymentCurrencies::CURRENCIES;
        $currencies = [];

        foreach ($allowed as $code => $name) {
            $currencies[] = ['value' => $code, 'text' => $code . ' - ' . $name];
        }

        // A stored currency the provider can't collect stays in the list, flagged, so the screen
        // doesn't silently change it.
        if (!isset($allowed[$this->config->payment_currency])) {
            $currencies[] = ['value' => $this->config->payment_currency,
                'text'  => Text::sprintf('COM_TICKETSTATION_PAYMENTS_CURRENCY_UNSUPPORTED_OPTION', $this->config->payment_currency)];
        }

        $this->lists['payment_currency'] = HTMLHelper::_('select.genericList', $currencies, 'payment_currency', ' class="form-select"',
            'value', 'text', $this->config->payment_currency);

        parent::display($tpl);
    }
}
