<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Scanners;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
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
 * Ticketstation Scanners Admin View
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


        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_SCANNERS_TITLE'), 'fa fa-qrcode');
        ToolBarHelper::addNew();
        ToolBarHelper::deleteList();
        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $items = $this->get('list');
        $pagination = $this->get('pagination');

        $this->items = $items;
        $this->pagination = $pagination;

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {

        // Set up the toolbar
        ToolBarHelper::apply();
        ToolBarHelper::save();
        ToolBarHelper::cancel();

        $data	    = $this->get('data');
        $config	    = $this->get('config');
        $events		= $this->get('events');
        $tickets	= $this->get('tickets');

        $users[]	     = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_SCANNING_SELECT_USER' ), 'id', 'name' );
        $users	         = array_merge( $users, $this->get('users'));
        $lists['users'] = HTMLHelper::_('select.genericlist',  $users, 'userid', 'class="form-select" ','id',
            'name', isset($data->userid)?$data->userid:0 );

        $yesno = array(
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_YES' )),
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_NO' )),
        );

        $lists['totals_visible'] = HTMLHelper::_('select.genericList', $yesno, 'totals_visible', ' class="form-select" ','value', 'text',
            isset($data->totals_visible)?$data->totals_visible:0 );

        $lists['manual_entry'] = HTMLHelper::_('select.genericList', $yesno, 'manual_entry', ' class="form-select" ','value', 'text',
            isset($data->manual_entry)?$data->manual_entry:0 );

        if (!empty($data->events)) {
            $assigned_events = json_decode($data->events);
        } else {
            $assigned_events = 0;
        }

        if (!empty($data->tickets)) {
            $assigned_tickets = json_decode($data->tickets);
        } else {
            $assigned_tickets = 0;
        }

        // Prepare API key display - it's a read-only field shown to help configure scanner apps
        $apikey = isset($data->apikey) ? $data->apikey : '';

        $this->config           = $config;
        $this->data             = $data;
        $this->events           = $events;
        $this->tickets          = $tickets;
        $this->assigned_events  = $assigned_events;
        $this->assigned_tickets = $assigned_tickets;
        $this->lists            = $lists;
        $this->apikey           = $apikey;

        $text = empty($this->data->id) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
        ToolBarHelper::title($text . ' ' . Text::_('COM_TICKETSTATION_VIEW_SCANNER_TITLE'), 'fa fa-qrcode');

        parent::display($tpl);
    }
}