<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Events;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Events Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation Events view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {
        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_EVENTS_TITLE'), 'fa fa-calendar-alt');
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

        $childBar->publish('events.publish')
            ->icon('fa fa-check-circle')
            ->text('JTOOLBAR_PUBLISH')
            ->listCheck(true);

        $childBar->unpublish('events.unpublish')
            ->icon('fa fa-times-circle')
            ->text('JTOOLBAR_UNPUBLISH')
            ->listCheck(true);

        $childBar->delete('events.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $items      = $this->get('list');
        $config     = $this->get('config');
        $sold       = $this->get('sold');
        $added      = $this->get('added');
        $pending    = $this->get('pending');
        $unfinished = $this->get('unfinished');
        $pagination = $this->get('pagination');

        $this->items = $items;
        $this->config = $config;
        $this->sold = $sold;
        $this->added = $added;
        $this->pending = $pending;
        $this->unfinished = $unfinished;
        $this->pagination = $pagination;

        parent::display($tpl);

    }
}
