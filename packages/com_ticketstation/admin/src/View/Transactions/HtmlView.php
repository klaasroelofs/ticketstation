<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Transactions;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

/**
 * Ticketstation Transactions View: the payments overview. A payment opens in its order.
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation transactions view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TRANSACTIONS_TITLE'), 'fa fa-credit-card');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('records-transactions');

        $lists['search'] = $this->escape($this->getModel()->getState('filter.search'));

        $items		= $this->get('list');
        $pagination	= $this->get('pagination');
        $data		= $this->get('config');

        $this->items = $items;
        $this->pagination = $pagination;
        $this->lists = $lists;
        $this->data = $data;

        parent::display($tpl);
    }

}
