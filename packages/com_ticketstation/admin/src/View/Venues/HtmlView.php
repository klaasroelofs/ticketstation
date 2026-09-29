<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Venues;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

/**
 * Ticketstation Venues Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation Venues view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {
        if ($this->getLayout() == 'form') {
            $this->_displayForm($tpl);
            return;
        }

        if ($this->getLayout() == 'modal') {
            $this->_displayModal($tpl);
            return;
        }

        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_VENUES_TITLE'), 'fa fa-hotel');
        if (AclGate::can('core.create')) {
            ToolBarHelper::addNew();
        }

        $toolbar = Toolbar::getInstance('toolbar');
        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        /** @var Toolbar $childBar */
        $childBar = $dropdown->getChildToolbar();

        if (AclGate::can('core.edit.state')) {
            $childBar->publish('venues.publish')
                ->icon('fa fa-check-circle')
                ->text('JTOOLBAR_PUBLISH')
                ->listCheck(true);
        }

        if (AclGate::can('core.edit.state')) {
            $childBar->unpublish('venues.unpublish')
                ->icon('fa fa-times-circle')
                ->text('JTOOLBAR_UNPUBLISH')
                ->listCheck(true);
        }

        if (AclGate::can('core.delete')) {
            $childBar->delete('venues.remove')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('records-venues');

        $items = $this->get('list');
        $pagination = $this->get('Pagination');

        $this->items = $items;
        $this->pagination = $pagination;

        parent::display($tpl);
    }

    function _displayModal($tpl = null)
    {

        $items = $this->get('list');

        $this->items = $items;

        parent::display($tpl);
    }


    function _displayForm($tpl = null)
    {

        // Set up the toolbar
        ToolBarHelper::apply();
        ToolBarHelper::save();
        ToolBarHelper::cancel();
        Docs::toolbarButton('records-venues');

        $data = $this->get('data');
        $config = $this->get('config');

        $yesno = array(
            '1' => array('value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')),
            '0' => array('value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')),
        );

        $lists['published'] = HTMLHelper::_('select.genericList', $yesno, 'published', ' class="input" ', 'value', 'text',
            isset($data->published) ? $data->published : 0);

        $this->data = $data;
        $this->config = $config;
        $this->lists = $lists;

        ## Seat plan templates of this venue (made in the seat plan editor of its tickets).
        $this->seatplanTemplates = \Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanLayout::templates((int) ($data->id ?? 0));

        $text = empty($this->data->id) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
        ToolBarHelper::title($text . ' ' . Text::_('COM_TICKETSTATION_VIEW_VENUE_TITLE'), 'fa fa-hotel');

        parent::display($tpl);
    }
}