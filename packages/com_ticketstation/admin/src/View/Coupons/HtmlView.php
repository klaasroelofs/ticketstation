<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Coupons;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Coupons Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation coupons view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {
        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_COUPONS'), 'fa fa-percent');
        ToolBarHelper::addNew();

        $toolbar = Toolbar::getInstance('toolbar');
        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        /** @var Toolbar $childBar */
        $childBar = $dropdown->getChildToolbar();

        $childBar->publish('coupons.publish')
            ->icon('fa fa-check-circle')
            ->text('JTOOLBAR_PUBLISH')
            ->listCheck(true);

        $childBar->unpublish('coupons.unpublish')
            ->icon('fa fa-times-circle')
            ->text('JTOOLBAR_UNPUBLISH')
            ->listCheck(true);

        $childBar->delete('coupons.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $app = Factory::getApplication();

        $search = $app->getUserStateFromRequest('searchbox', 'searchbox', '', 'string');
        $search = strtolower($search);

        $lists['search'] = $search;

        $items = $this->get('list');
        $config = $this->get('config');
        $pagination = $this->get('Pagination');

        $this->items = $items;
        $this->config = $config;
        $this->pagination = $pagination;

        parent::display($tpl);

    }
}