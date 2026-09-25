<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Transactions;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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
 * Ticketstation Transaction Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation transaction view
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
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TRANSACTIONS_TITLE'), 'fa fa-credit-card');
        ToolBarHelper::editList('edit', 'JOPEN');
        ToolBarHelper::deleteList();
        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $app		= Factory::getApplication();

        $trix 		= $app->getUserStateFromRequest( 'trix', 'trix', '', 'cmd' );
        $ordercode 	= $app->getUserStateFromRequest( 'ordercode', 'ordercode', '', 'cmd' );
        $search			    = $app->getUserStateFromRequest( 'searchbox', 'searchbox', '', 'string' );
        $search			    = strtolower( $search );

        $lists['search']	= $search;
        $lists['ordercode']	= $ordercode;
        $lists['trix']		= $trix;

        $items		= $this->get('list');
        $pagination	= $this->get('pagination');
        $data		= $this->get('config');

        $this->items = $items;
        $this->pagination = $pagination;
        $this->lists = $lists;
        $this->data = $data;

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TRANSACTION_DETAILS'), 'fa fa-credit-card');
        ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

        ## Getting the items into a variable
        $data	= $this->get('data');
        $config	= $this->get('config');

        $this->data = $data;
        $this->config = $config;

        parent::display($tpl);
    }

}