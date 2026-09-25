<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Clients;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Clients Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation clients view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'form') {
            $this->_displayForm($tpl);
            return;
        }

        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CUSTOMER_TITLE'), 'icon-users');

        $toolbar = Toolbar::getInstance('toolbar');
        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        /** @var Toolbar $childBar */
        $childBar = $dropdown->getChildToolbar();

        $childBar->publish('clients.publish')
            ->icon('fa fa-check-circle')
            ->text('JTOOLBAR_PUBLISH')
            ->listCheck(true);

        $childBar->unpublish('clients.unpublish')
            ->icon('fa fa-times-circle')
            ->text('JTOOLBAR_UNPUBLISH')
            ->listCheck(true);

        $childBar->delete('clients.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $app		= Factory::getApplication();

        ## Getting the items into a variable
        $items	= $this->get('list');
        $pagination = $this->get( 'Pagination' );

        $filter_order       = $app->getUserStateFromRequest( 'filter_ordering', 'filter_ordering', 'name', 'cmd' );
        $search			    = $app->getUserStateFromRequest( 'searchbox', 'searchbox', '', 'string' );
        $search			    = strtolower( $search );

        $lists['search']= $search;

        ## Filling the Array() for doors and make a select list for it.
        $ordering = array(
            'name' => array('value' => 'name', 'text' => Text::_( 'COM_TICKETSTATION_SEARCH_NAME' )),
            'address' => array('value' => 'address', 'text' => Text::_( 'COM_TICKETSTATION_SEARCH_ADDRESS' )),
            'zipcode' => array('value' => 'zipcode', 'text' => Text::_( 'COM_TICKETSTATION_SEARCH_ZIPCODE' )),
            'city' => array('value' => 'city', 'text' => Text::_( 'COM_TICKETSTATION_SEARCH_CITY' )),
            'emailaddress' => array('value' => 'emailaddress', 'text' => Text::_( 'COM_TICKETSTATION_SEARCH_EMAIL' )),

        );

        $lists['ordering'] = HTMLHelper::_('select.genericList', $ordering, 'filter_ordering', ' class="form-select"',
            'value', 'text', $filter_order );

        $this->pagination = $pagination;
        $this->items = $items;
        $this->lists = $lists;

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CUSTOMER_DETAILS'), 'icon-user');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolBarHelper::cancel();

        ## Getting the items into a variable
        $data	= $this->get('data');
        $items	= $this->get('orderlist');
        $config	= $this->get('config');

        $is_published = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_NO' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_YES' )),
        );

        $lists['published'] = HTMLHelper::_('select.genericList', $is_published, 'published', ' class="form-select" '. '',
            'value', 'text', isset($data->published)?$data->published:1 );

        ## Filling the Array() for gender and make a select list for it.
        $gender = array(
            1 => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_MR' )),
            2 => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_MRS' )),
            3 => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_MISS' )),
            4 => array('value' => '4', 'text' => Text::_( 'COM_TICKETSTATION_FAMILY' )),
        );

        $lists['gender'] = HTMLHelper::_('select.genericList', $gender, 'gender', ' class="inputbox" ' , 'value', 'text', isset($data->gender)?$data->gender:4 );

        $this->data = $data;
        $this->items = $items;
        $this->config = $config;
        $this->lists = $lists;

        parent::display($tpl);
    }

}