<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Controlpanel;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

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

    function display($tpl = null) {

        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE'), 'icon-home');

        $model = $this->getModel('Controlpanel', 'Administrator');
        $this->data 	= $model->getData();
		$this->mollie   = $model->getMollie();
        $this->config   = $model->getConfig();


        parent::display($tpl);
    }


}