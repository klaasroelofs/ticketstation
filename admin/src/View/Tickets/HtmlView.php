<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Tickets;

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
 * Ticketstation Tickets Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation Tickets view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {
        // Setup the toolbar
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TICKETS_TITLE'), 'fa fa-ticket-alt');
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

        $childBar->publish('tickets.publish')
            ->icon('fa fa-check-circle')
            ->text('JTOOLBAR_PUBLISH')
            ->listCheck(true);

        $childBar->unpublish('tickets.unpublish')
            ->icon('fa fa-times-circle')
            ->text('JTOOLBAR_UNPUBLISH')
            ->listCheck(true);

        $childBar->standardButton('duplicate', 'COM_TICKETSTATION_COPY', 'tickets.duplicate')
            ->icon('fa fa-copy')
            ->listCheck(true);

        $childBar->delete('tickets.resetscanstate', 'Reset scans')
            ->icon('fa fa-eye-slash')
            ->message('COM_TICKETSTATION_CONFIRM_RESET_SCANS')
            ->listCheck(true);

        $childBar->delete('tickets.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('seatplans', 'fa-solid fa-chair', '', 'COM_TICKETSTATION_SEATPLANS', false,false);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $filter_order     = $app->getUserStateFromRequest( 'filter_ordering_t', 'filter_ordering_t','a.fueltype','cmd' );
        $filter_order_Dir = $app->getUserStateFromRequest( 'filter_order_Dir', 'filter_order_Dir', 'asc', 'word' );
        $filter_state	  = $app->getUserStateFromRequest( 'filter_state', 'filter_state', '3', 'int' );
        $filter_venue	  = $app->getUserStateFromRequest( 'filter_ordering_venue', 'filter_ordering_venue', '0', 'int' );

        ## table ordering
        $lists['order_Dir']  = $filter_order_Dir;
        $lists['order']      = $filter_order;

        $query = $db->getQuery(true);

        $query->select( array('eventid', 'eventname AS name') );
        $query->from($db->quoteName('#__ticketstation_events'));
        $query->order('eventname ASC');

        $db->setQuery($query);

        $eventlist[]	  = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_SELECTLIST_EVENT' ),
            'eventid', 'name' );

        $eventlist	      = array_merge( $eventlist, $db->loadObjectList() );
        $lists['eventid'] = HTMLHelper::_('select.genericlist',  $eventlist, 'filter_ordering_t', 'class="form-select js-select-submit-on-change active"  
						onchange="this.form.submit();"', 'eventid', 'name', intval($filter_order) );

        $query = $db->getQuery(true);

        $query->select( array('id', 'venue AS name') );
        $query->from($db->quoteName('#__ticketstation_venues'));

        $db->setQuery($query);

        $venuelist[]	  = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_SELECTLIST_VENUE' ), 'id', 'name' );
        $venuelist	      = array_merge( $venuelist, $db->loadObjectList() );
        $lists['venue'] = HTMLHelper::_('select.genericlist',  $venuelist, 'filter_ordering_venue', 'class="form-select js-select-submit-on-change active"  
						onchange="this.form.submit();"',
            'id', 'name', intval($filter_venue) );

        $state = array(
            '0' => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_SELECTLIST_PUBLISHING_STATE' )),
            '1' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_UNPUBLSIHED' )),
            '2' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_PUBLISHED' )),
        );

        $lists['filter_state'] = HTMLHelper::_('select.genericList', $state, 'filter_state', 'class="form-select js-select-submit-on-change active"  
						onchange="this.form.submit();"',
            'value', 'text', $filter_state );

        $model = $this->getModel();

        $items      = $model->getList();
        $sold       = $model->getSold();
        $config     = $model->getConfig();
        $pagination = $model->getPagination();
        $childs     = $model->getChilds();

        $this->lists = $lists;
        $this->items = $items;
        $this->sold = $sold;
        $this->config = $config;
        $this->pagination = $pagination;
        $this->childs = $childs;

        parent::display($tpl);

    }
}
