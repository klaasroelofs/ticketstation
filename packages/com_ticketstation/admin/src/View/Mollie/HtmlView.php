<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Mollie;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MollieCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MolliePaymentMethods;

/**
 * Ticketstation Mollie Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the Ticketstation configuration view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $config = [];

    /**
     * The payment methods chosen for checkout.
     *
     * @var array
     */
    public $paymentMethods = [];

    /**
     * The methods active in the Mollie account, or null when Mollie could not be asked.
     *
     * @var array|null
     */
    public $activeMethods = null;

    /**
     * The ISO code of the currency payments are made in.
     *
     * @var string
     */
    public $currency = MollieCurrencies::DEFAULT;

    function display($tpl = null) {

        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_MOLLIE_TITLE'), 'fa-regular fa-money-bill-wave');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolbarHelper::cancel();

        $model = $this->getModel('Mollie');
        $config = $model->getData();

        $yesno = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')],
        ];

        $lists = [];

        $lists['test_mode'] = HTMLHelper::_('select.genericList', $yesno, 'test_mode', ' class="form-select" ' . '',
            'value', 'text', $config->test_mode);

        $lists['bypass_mode'] = HTMLHelper::_('select.genericList', $yesno, 'bypass_mode', ' class="form-select" ' . '',
            'value', 'text', $config->bypass_mode);

        $language = [
            'nl_NL' => ['value' => 'nl_NL', 'text' => 'Nederlands'],
            'en_GB' => ['value' => 'en_GB', 'text' => 'English'],
            'fr_FR' => ['value' => 'fr_FR', 'text' => 'Francais'],
            'de_DE' => ['value' => 'de_DE', 'text' => 'Deutsch'],
        ];

        $lists['mollie_language'] = HTMLHelper::_('select.genericList', $language, 'mollie_language', ' class="form-select" ' . '',
            'value', 'text', $config->mollie_language);

        $this->currency = MollieCurrencies::fromConfig($config->currency ?? '');

        $currencies = [];

        foreach (MollieCurrencies::CURRENCIES as $code => $name) {
            $currencies[$code] = ['value' => $code, 'text' => $code . ' - ' . $name];
        }

        $lists['currency'] = HTMLHelper::_('select.genericList', $currencies, 'currency', ' class="form-select" ' . '',
            'value', 'text', $this->currency);

        $lists['send_tickets_directly'] =HTMLHelper::_('select.genericList', $yesno, 'send_tickets_directly', ' class="form-select" ' . '',
            'value', 'text', $config->send_tickets_directly);


        $this->paymentMethods = MolliePaymentMethods::fromConfig($config->payment_methods ?? '');

        // Ask Mollie with the key the checkout uses, so the screen can flag chosen methods
        // that are not activated in the Mollie Dashboard (Mollie would refuse those).
        $this->activeMethods = MolliePaymentMethods::activeInMollie($config->test_mode == '1' ? $config->api_key_test : $config->api_key);

        $this->config = $config;
        $this->lists = $lists;

        parent::display($tpl);
    }


}