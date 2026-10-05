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
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletUpdate;

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

        // What goes out: the mail (on by default), the wallets' copies of the tickets brought in
        // line with the event, and the text as a message on the passes.
        $sendMail      = $input->getInt('send_mail', 1) === 1;
        $walletUpdate  = $input->getInt('wallet_update') === 1 && WalletUpdate::enabled();
        $walletMessage = $input->getInt('wallet_message') === 1 && WalletUpdate::enabled();
        $draft         = ['subject' => $subject, 'message' => $message];

        if ($test && !$sendMail)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_NO_TEST'), 'warning');
            $app->setUserState('com_ticketstation.eventmail', $draft);
            $this->setRedirect($back);

            return;
        }

        if ($sendMail)
        {
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
                $app->setUserState('com_ticketstation.eventmail', $draft);
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_TEST_SENT', $app->getIdentity()->email));
                $this->setRedirect($back);

                return;
            }

            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENTMAIL_SENT', $result['sent'], $result['total']));
        }

        if ($walletUpdate)
        {
            $marked = WalletUpdate::markEvent($eventid);
            $app->enqueueMessage(Text::plural('COM_TICKETSTATION_WALLET_UPDATE_MARKED', $marked));
        }

        if ($walletMessage)
        {
            $reached = WalletUpdate::queueMessage($eventid, $subject, trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />', '<br/>'], "\n", $message)), ENT_QUOTES, 'UTF-8')));
            $app->enqueueMessage(Text::plural('COM_TICKETSTATION_WALLET_MESSAGE_QUEUED', $reached));
        }

        if ($walletUpdate || $walletMessage)
        {
            // A first portion right away; the Scheduled Task sends the rest
            $sent = WalletUpdate::process();

            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_WALLET_UPDATE_RESULT', $sent['done'], $sent['notfound']), $sent['done'] ? 'message' : 'warning');

            if ($sent['review'])
            {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_WALLET_UPDATE_REVIEW', $sent['review']), 'warning');
            }

            if ($sent['failed'])
            {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_WALLET_UPDATE_FAILED', $sent['failed'], WalletUpdate::lastError()), 'error');
            }

            if ($sent['remaining'])
            {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_WALLET_UPDATE_REMAINING', $sent['remaining']), 'info');
            }
        }

        $app->setUserState('com_ticketstation.eventmail', null);
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }
}
