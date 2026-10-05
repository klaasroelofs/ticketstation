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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletUpdate;

/**
 * The Communication screen of an event: a message to the buyers, bringing the passes in the
 * wallets in line with the event, and what has been sent so far.
 */
class HtmlView extends BaseHtmlView
{
    public $event;
    public $buyers = 0;
    public $draft  = [];

    /** Live updates are on for at least one wallet. */
    public $walletOn = false;

    /** The passes live updates reach: total, Google, Apple. */
    public $passes = ['total' => 0, 'google' => 0, 'apple' => 0];

    public $summary      = [];
    public $walletStatus = ['pending' => 0, 'failed' => 0, 'error' => ''];

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

        $this->buyers   = count(EventMail::audience($eventid));
        $this->summary  = EventMail::summary($eventid);
        $this->walletOn = WalletUpdate::enabled();

        if ($this->walletOn)
        {
            $this->passes = [
                'total'  => WalletUpdate::passesOfEvent($eventid),
                'google' => WalletUpdate::passesOfEvent($eventid, Wallet::GOOGLE),
                'apple'  => WalletUpdate::passesOfEvent($eventid, Wallet::APPLE),
            ];
            $this->walletStatus = WalletUpdate::statusOfEvent($eventid);
        }

        $this->draft = (array) $app->getUserState('com_ticketstation.eventmail', []);
        $app->setUserState('com_ticketstation.eventmail', null);

        ToolbarHelper::title(Text::sprintf('COM_TICKETSTATION_COMMUNICATION_TITLE', $this->event->eventname), 'fa fa-envelope');
        ToolbarHelper::cancel('cancel', 'COM_TICKETSTATION_COMMUNICATION_BACK');
        Docs::toolbarButton('events');

        parent::display($tpl);
    }
}
