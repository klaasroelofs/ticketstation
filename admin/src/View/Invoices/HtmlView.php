<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Invoices;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Invoices Admin View
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Display the Ticketstation invoices view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */
    function display($tpl = null)
    {
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_INVOICES'), 'fa fa-file-invoice');

        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $this->items      = $this->get('list');
        $this->config     = $this->get('config');
        $this->pagination = $this->get('Pagination');

        parent::display($tpl);
    }
}
