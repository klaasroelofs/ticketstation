<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Templates;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Mollie Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the Ticketstation Templates view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $config = [];

    function display($tpl = null) {

        if ($this->getLayout() == 'form') {
            $this->_displayForm($tpl);
            return;
        }
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'), 'fa fa-envelope');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $model = $this->getModel('Templates');

        $this->items = $model->getList();

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'), 'fa fa-envelope');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        ToolBarHelper::cancel();

        $model = $this->getModel('Templates');

        $this->data = $model->getData();

        ToolBarHelper::title(Text::sprintf('COM_TICKETSTATION_VIEW_EDIT_TEMPLATES_TITLE', $this->data->alias), 'fa fa-envelope');

        parent::display($tpl);
    }

}