<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\EventMail;

/**
 * The screen that mails the buyers of one event (see EventMail): a new start time, another
 * location, a cancellation or any other message.
 */
class EventmailController extends BaseController
{
    use RegisterControllerTasks;

    protected $default_view = 'Eventmail';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'eventmail');
        parent::display();
    }

    /**
     * Sends the message to every buyer of the event.
     */
    public function send()
    {
        $this->deliver(false);
    }

    /**
     * Sends the message to the admin's own address only, with the data of the first order.
     */
    public function sendtest()
    {
        $this->deliver(true);
    }

    public function cancel()
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }

    private function deliver(bool $test): void
    {
        $app     = Factory::getApplication();
        $input   = $app->getInput();
        $eventid = $input->getInt('eventid');
        $subject = trim($input->getString('subject', ''));
        $message = trim((string) $input->get('message', '', 'raw'));
        $back    = Uri::base() . 'index.php?option=com_ticketstation&controller=eventmail&task=display&eventid=' . $eventid;

        if (!$eventid || $subject === '' || trim(strip_tags($message)) === '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_INCOMPLETE'), 'error');
            $app->setUserState('com_ticketstation.eventmail', ['subject' => $subject, 'message' => $message]);
            $this->setRedirect($back);

            return;
        }

        $result = EventMail::sendUpdate($eventid, $subject, $message, $test ? $app->getIdentity()->email : null);

        if ($result['total'] === 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_NO_BUYERS'), 'warning');
            $this->setRedirect($back);

            return;
        }

        if ($result['failed'])
        {
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_FAILED', count($result['failed']), implode(', ', $result['failed'])), 'error');
        }

        if ($test)
        {
            // A test leaves the text in the form, so it can be sent for real next.
            $app->setUserState('com_ticketstation.eventmail', ['subject' => $subject, 'message' => $message]);
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_TEST_SENT', $app->getIdentity()->email));
            $this->setRedirect($back);

            return;
        }

        $app->setUserState('com_ticketstation.eventmail', null);
        $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_SENT', $result['sent'], $result['total']));
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }
}
