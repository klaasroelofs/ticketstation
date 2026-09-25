<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Seatplansettings;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;

/**
 * Ticketstation Seatplansettings Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation Seatplansettings view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getData();

        ## Child tickets (sections when Multi Seat = No) with their own colour overrides.
        $this->childColours = empty($this->item->ticketid) ? [] : SeatplanSettings::getChildColours((int) $this->item->ticketid);

        // Set up the toolbar
        $title_text = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_SETTINGS') : Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_SETTINGS') . ' ' . $this->item->ticketname . ' <small>(' . $this->item->ticketcode . ')</small>';
        ToolbarHelper::title($title_text, 'icon-cog');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
