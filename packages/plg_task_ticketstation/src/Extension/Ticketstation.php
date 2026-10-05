<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_task_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\Task\Ticketstation\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Ticketstation\Component\Ticketstation\Administrator\Helper\EventMail;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletUpdate;

/**
 * Scheduled Tasks of Ticketstation. Run "Ticketstation reminders" regularly (hourly is fine): it
 * mails the buyers of events that start within the reminder period set in the Configuration, once
 * per order. It does nothing while the reminder is switched off there.
 */
final class Ticketstation extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var    string[][]
     */
    private const TASKS_MAP = [
        'ticketstation.reminders' => [
            'langConstPrefix' => 'PLG_TASK_TICKETSTATION_REMINDERS',
            'method'          => 'sendReminders',
        ],
        'ticketstation.walletupdates' => [
            'langConstPrefix' => 'PLG_TASK_TICKETSTATION_WALLETUPDATES',
            'method'          => 'sendWalletUpdates',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Sends the pending changes and messages to the passes in Google Wallet (see WalletUpdate).
     */
    private function sendWalletUpdates(ExecuteTaskEvent $event): int
    {
        if (!class_exists(WalletUpdate::class)) {
            $this->logTask('The Ticketstation component is not installed.', 'error');

            return Status::KNOCKOUT;
        }

        $this->getApplication()->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR);

        $result = WalletUpdate::process(200, 50);

        $this->logTask(sprintf('Wallet passes updated: %d, failed: %d, still waiting: %d', $result['done'], $result['failed'], $result['remaining']));

        return Status::OK;
    }

    private function sendReminders(ExecuteTaskEvent $event): int
    {
        if (!class_exists(EventMail::class)) {
            $this->logTask('The Ticketstation component is not installed.', 'error');

            return Status::KNOCKOUT;
        }

        // The mail texts and dates use the component's language strings.
        $this->getApplication()->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR);

        $result = EventMail::sendReminders();

        $this->logTask(sprintf('Reminders sent: %d, failed: %d', $result['sent'], $result['failed']));

        // A failed mail is tried again the next time: it is not logged as sent.
        return Status::OK;
    }
}
