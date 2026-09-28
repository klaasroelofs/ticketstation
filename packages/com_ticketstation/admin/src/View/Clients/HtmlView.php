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
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

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
        Docs::toolbarButton('records-customers');

        ## Getting the items into a variable
        $items	= $this->get('list');
        $pagination = $this->get( 'Pagination' );

        $lists['search'] = $this->escape($this->getModel()->getState('filter.search'));

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
        Docs::toolbarButton('records-customers');

        ## Getting the items into a variable
        $data	= $this->get('data');
        $items	= $this->get('orderlist');
        $config	= $this->get('config');

        // The standard Joomla form escapes the stored values. No control, so the fields post
        // under their own names, as ClientsModel::store() expects.
        $form = Factory::getContainer()->get(FormFactoryInterface::class)->createForm('com_ticketstation.client');
        $form->loadFile(JPATH_ADMINISTRATOR . '/components/com_ticketstation/forms/client.xml');
        $form->bind($data ?: []);

        $this->data = $data;
        $this->form = $form;
        $this->items = $items;
        $this->config = $config;

        parent::display($tpl);
    }

}