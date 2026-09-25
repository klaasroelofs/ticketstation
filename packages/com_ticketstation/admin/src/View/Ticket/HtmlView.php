<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Ticket;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Ticket Admin View
 */
class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    function display($tpl = null)
    {

        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();

        // Set up the toolbar
        $add_edit = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
        $ticket_name = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_VIEW_TICKET_TITLE') : $this->item->ticketname . ' <small>(' . $this->item->ticketcode . ')</small>';
        ToolBarHelper::title($add_edit . ' ' . $ticket_name, 'fa fa-ticket-alt');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        if (empty($this->item->ticketid)) {
            ToolbarHelper::cancel();
        }
        else {
            ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        }

        $app = Factory::getApplication();
        $app->getInput()->set('hidemainmenu', 1);

        parent::display($tpl);
    }
}
