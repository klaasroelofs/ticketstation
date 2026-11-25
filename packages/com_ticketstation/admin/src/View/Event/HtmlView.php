<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Event;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

/**
 * Ticketstation Event Admin View
 */
class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;
    protected $ticketCount = 0;

    function display($tpl = null)
    {
        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();

        // Tickets that can move along when the event date changes
        $this->ticketCount = $model->getTicketCount((int) ($this->item->eventid ?? 0));

        // Set up the toolbar
        $add_edit = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
        $event_name = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_VIEW_EVENT_TITLE') : $this->item->eventname . ' <small>(' . $this->item->eventcode . ')</small>';
        ToolBarHelper::title($add_edit . ' ' . $event_name, 'fa fa-calendar-alt');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        if (empty($this->item->eventid)) {
            ToolbarHelper::cancel();
        }
        else {
            ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        }

        Docs::toolbarButton('events');
        parent::display($tpl);
    }
}
