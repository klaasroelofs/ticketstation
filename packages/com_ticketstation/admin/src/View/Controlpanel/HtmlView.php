<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Controlpanel;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * Ticketstation Controlpanel Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the main Ticketstation view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $data = [];

    public $stats = [];

    public $availability = [];

    public $attention = [];

    public $dailySales = [];

    public $setupSteps = [];

    public $update = null;

    /** @var bool  Whether the shop is in test mode. */
    public $testMode = false;

    /** @var bool  Whether test mode stops paid orders: the payment provider has no test environment. */
    public $testBlocked = false;

    /** @var bool  Whether this user may switch test mode. */
    public $canSwitchMode = false;

    /** @var string  The name of the payment provider that takes payments, '' when online payments are off. */
    public $providerTitle = '';

    function display($tpl = null) {

        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE'), 'icon-home');
        if (AclGate::can('core.admin')) {
            ToolbarHelper::preferences('com_ticketstation', 500, 900, 'COM_TICKETSTATION_TOOLBAR_PERMISSIONS');
        }

        Docs::toolbarButton('controlpanel');

        $model = $this->getModel('Controlpanel', 'Administrator');
        $this->data 	= $model->getData();
        $this->update   = $model->getAvailableUpdate();
		$this->paymentsOn = Shop::paymentsOn();
        $this->config   = $model->getConfig();

        $this->stats        = $model->getStats();
        $this->availability = $model->getAvailability();

        // Refunds a colleague made in the Mollie Dashboard, before the webhook reports them.
        Refund::pollProviders();

        // Test mode is one switch for the whole shop; the banner at the top says what it means for the payment provider.
        $this->testMode      = Shop::inTestMode();
        $this->testBlocked   = Shop::testPaymentsBlocked();
        $this->canSwitchMode = AclGate::can('core.options');
        $provider            = ProviderRegistry::active();
        $this->providerTitle = $provider !== null ? $provider->getTitle() : '';

        $this->attention    = $model->getAttention($this->config);
        $this->dailySales   = $model->getDailySales();
        $this->setupSteps   = $model->getSetupSteps($this->config);


        parent::display($tpl);
    }


}