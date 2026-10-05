<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Eventmail;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\EventMail;

/**
 * The "Mail the buyers" screen of an event.
 */
class HtmlView extends BaseHtmlView
{
    public $event;
    public $buyers = 0;
    public $draft  = [];

    function display($tpl = null)
    {
        $app     = Factory::getApplication();
        $eventid = $app->getInput()->getInt('eventid');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery($db->getQuery(true)
            ->select(['eventid', 'eventname', 'eventdate'])
            ->from($db->quoteName('#__ticketstation_events'))
            ->where($db->quoteName('eventid') . ' = ' . $eventid));
        $this->event = $db->loadObject();

        if (!$this->event)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $app->redirect('index.php?option=com_ticketstation&view=events');
        }

        $this->buyers = count(EventMail::audience($eventid));
        $this->draft  = (array) $app->getUserState('com_ticketstation.eventmail', []);
        $app->setUserState('com_ticketstation.eventmail', null);

        ToolbarHelper::title(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_TITLE', $this->event->eventname), 'fa fa-envelope');
        ToolbarHelper::custom('sendtest', 'fa fa-vial', '', 'COM_TICKETSTATION_EVENTMAIL_SEND_TEST', false);
        ToolbarHelper::custom('send', 'fa fa-paper-plane', '', 'COM_TICKETSTATION_EVENTMAIL_SEND', false);
        ToolbarHelper::cancel('cancel');
        Docs::toolbarButton('events');

        parent::display($tpl);
    }
}
