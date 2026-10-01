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

    function display($tpl = null) {

        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE'), 'icon-home');
        if (AclGate::can('core.admin')) {
            ToolbarHelper::preferences('com_ticketstation', 500, 900, 'COM_TICKETSTATION_TOOLBAR_PERMISSIONS');
        }

        Docs::toolbarButton('controlpanel');

        $model = $this->getModel('Controlpanel', 'Administrator');
        $this->data 	= $model->getData();
        $this->update   = $model->getAvailableUpdate();
		$this->mollie   = $model->getMollie();
        $this->config   = $model->getConfig();

        $this->stats        = $model->getStats();
        $this->availability = $model->getAvailability();

        // Refunds a colleague made in the Mollie Dashboard, before the webhook reports them.
        Refund::pollMollie();

        $this->attention    = $model->getAttention($this->config, $this->mollie);
        $this->dailySales   = $model->getDailySales();
        $this->setupSteps   = $model->getSetupSteps($this->config, $this->mollie);


        parent::display($tpl);
    }


}