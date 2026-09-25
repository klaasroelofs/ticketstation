<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Waitinglist;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Waitinglist Admin View
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Display the Ticketstation waitinglist view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */
    function display($tpl = null)
    {
        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_WAITINGLIST'), 'fa fa-hourglass-half');

        $toolbar = Toolbar::getInstance('toolbar');

        $toolbar->standardButton('confirm', 'COM_TICKETSTATION_TOOLBAR_CONFIRM_WAITINGLIST', 'waitinglist.confirm')
            ->icon('fa fa-check')
            ->listCheck(true);

        $toolbar->delete('waitinglist.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('', 'spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $this->items      = $this->get('list');
        $this->config     = $this->get('config');
        $this->pagination = $this->get('Pagination');

        parent::display($tpl);
    }
}
