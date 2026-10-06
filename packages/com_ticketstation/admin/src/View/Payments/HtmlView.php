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

        $currencies = [];

        foreach (PaymentCurrencies::CURRENCIES as $code => $name) {
            $currencies[] = ['value' => $code, 'text' => $code . ' - ' . $name];
        }

        $this->lists['payment_currency'] = HTMLHelper::_('select.genericList', $currencies, 'payment_currency', ' class="form-select"',
            'value', 'text', $this->config->payment_currency);

        parent::display($tpl);
    }
}
