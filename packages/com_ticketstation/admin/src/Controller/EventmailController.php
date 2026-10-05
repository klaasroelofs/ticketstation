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
 * The Communication screen of one event: a message to its buyers (by mail, to the wallets, or
 * both) and bringing the passes in the wallets in line with the event. See EventMail and
 * WalletUpdate.
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
     * Sends the message to the buyers of the event, by mail and/or to the wallets.
     */
    public function send()
    {
        $this->message(false);
    }

    /**
     * Sends the message by mail to the admin's own address only, with the data of the first order.
     */
    public function sendtest()
    {
        $this->message(true);
    }

    /**
     * Brings the passes in the wallets in line with the event: no text, just the current details.
     */
    public function updatewallets()
    {
        $app     = Factory::getApplication();
        $eventid = $app->getInput()->getInt('eventid');

        if (!$eventid || !WalletUpdate::enabled())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COMMUNICATION_WALLETS_OFF'), 'warning');
            $this->setRedirect($this->back($eventid));

            return;
        }

        $marked = WalletUpdate::markEvent($eventid);

        if ($marked === 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COMMUNICATION_NO_PASSES'), 'warning');
            $this->setRedirect($this->back($eventid));

            return;
        }

        $app->enqueueMessage(Text::plural('COM_TICKETSTATION_WALLET_UPDATE_MARKED', $marked));
        $this->sendPending();
        $this->setRedirect($this->back($eventid));
    }

    public function cancel()
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }

    private function back(int $eventid): string
    {
        return Uri::base() . 'index.php?option=com_ticketstation&controller=eventmail&task=display&eventid=' . $eventid;
    }

    /**
     * The message card: subject and text are needed, and at least one way to send them.
     */
    private function message(bool $test): void
    {
        $app     = Factory::getApplication();
        $input   = $app->getInput();
        $eventid = $input->getInt('eventid');
        $subject = trim($input->getString('subject', ''));
        $message = trim((string) $input->get('message', '', 'raw'));
        $back    = $this->back($eventid);
        $draft   = ['subject' => $subject, 'message' => $message];

        $byMail    = $input->getInt('send_mail', 1) === 1;
        $toWallets = $input->getInt('send_wallet') === 1 && WalletUpdate::enabled();

        if (!$eventid || $subject === '' || trim(strip_tags($message)) === '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_INCOMPLETE'), 'error');
            $app->setUserState('com_ticketstation.eventmail', $draft);
            $this->setRedirect($back);

            return;
        }

        if ($test && !$byMail)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_NO_TEST'), 'warning');
            $app->setUserState('com_ticketstation.eventmail', $draft);
            $this->setRedirect($back);

            return;
        }

        if (!$byMail && !$toWallets)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COMMUNICATION_NO_CHANNEL'), 'warning');
            $app->setUserState('com_ticketstation.eventmail', $draft);
            $this->setRedirect($back);

            return;
        }

        if ($byMail)
        {
            $result = EventMail::sendUpdate($eventid, $subject, $message, $test ? $app->getIdentity()->email : null);

            if ($result['total'] === 0)
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTMAIL_NO_BUYERS'), 'warning');
                $app->setUserState('com_ticketstation.eventmail', $draft);
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

        if ($toWallets)
        {
            $plain   = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />', '<br/>'], "\n", $message)), ENT_QUOTES, 'UTF-8'));
            $reached = WalletUpdate::queueMessage($eventid, $subject, $plain);

            $app->enqueueMessage(Text::plural('COM_TICKETSTATION_WALLET_MESSAGE_QUEUED', $reached));
            $this->sendPending();
        }

        $this->setRedirect($back);
    }

    /**
     * A first portion of the pending wallet changes right away, and what happened.
     */
    private function sendPending(): void
    {
        $app  = Factory::getApplication();
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
}
