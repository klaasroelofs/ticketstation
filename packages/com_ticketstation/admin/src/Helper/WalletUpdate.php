<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Keeps the passes customers already have in their wallet in line with their tickets, and shows
 * messages on them. Every pass handed out is recorded in #__ticketstation_wallet_passes; a change
 * marks the passes it concerns as pending, and process() sends them to the wallets in portions: a
 * first portion right away, the rest by the Scheduled Task.
 *
 * Google Wallet: the pass is changed at Google (see WalletGoogleApi). A pass the customer never
 * saved is not an error: Google doesn't know it, so there is nothing to change.
 *
 * Apple Wallet: nothing is sent; the pass is marked as changed (updated_at) and the devices that
 * registered for it get a push notification, after which they fetch the new pass from the web
 * service (see WalletAppleService). Without the push (a server without HTTP/2) the device still
 * gets the change when the customer opens Wallet.
 *
 * @since 2.22.0
 */
class WalletUpdate
{
    /**
     * Live updates are on for at least one wallet, so changes are worth marking.
     */
    public static function enabled(): bool
    {
        return self::wallets() !== [];
    }

    public static function googleEnabled(): bool
    {
        return WalletGoogleApi::ready();
    }

    public static function appleEnabled(): bool
    {
        return (int) (Wallet::config()->wallet_apple_updates ?? 0) === 1 && Wallet::appleReady();
    }

    /**
     * The wallets with live updates on.
     *
     * @return  string[]
     */
    public static function wallets(): array
    {
        return array_values(array_filter([
            self::googleEnabled() ? Wallet::GOOGLE : null,
            self::appleEnabled() ? Wallet::APPLE : null,
        ]));
    }

    /**
     * An SQL condition for the passes of the wallets with live updates on, on the qualified
     * column that holds the wallet. An Apple pass only counts when a device registered for it:
     * without one (a pass made before 2.23, or never added) there is nobody to tell.
     */
    private static function walletCondition(string $alias, bool $removed = false): string
    {
        $db         = Factory::getContainer()->get('DatabaseDriver');
        $conditions = [];

        if (self::googleEnabled())
        {
            $conditions[] = $alias . '.wallet = ' . $db->quote(Wallet::GOOGLE);
        }

        if (self::appleEnabled())
        {
            $conditions[] = '(' . $alias . '.wallet = ' . $db->quote(Wallet::APPLE)
                . ' AND EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_wallet_devices') . ' AS wd WHERE wd.pass_row = ' . $alias . '.id))';
        }

        $sql = $conditions ? '(' . implode(' OR ', $conditions) . ')' : '1 = 0';

        return $removed ? $sql : $sql . ' AND ' . $alias . '.removed = 0';
    }

    /**
     * The passes recorded for the orders of an event that live updates reach.
     */
    public static function passesOfEvent(int $eventid, ?string $wallet = null): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT COUNT(DISTINCT p.id) FROM ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = p.orderid'
            . ' WHERE ' . self::walletCondition('p') . ' AND o.eventid = ' . $eventid
            . ($wallet !== null ? ' AND p.wallet = ' . $db->quote($wallet) : ''));

        return (int) $db->loadResult();
    }

    /**
     * The messages sent to the wallets for an event: how many, and when the last was sent.
     *
     * @return  array{total: int, last: ?string}
     */
    public static function messagesOfEvent(int $eventid): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT COUNT(*) AS total, MAX(created) AS last FROM ' . $db->quoteName('#__ticketstation_wallet_messages')
            . ' WHERE eventid = ' . $eventid);
        $row = $db->loadObject();

        return ['total' => (int) ($row->total ?? 0), 'last' => $row->last ?? null];
    }

    /**
     * What is waiting or went wrong for the passes of an event.
     *
     * @return  array{pending: int, failed: int, error: string}
     */
    public static function statusOfEvent(int $eventid): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT SUM(p.update_pending = 1 OR p.message_pending > 0) AS pending, SUM((p.update_pending = 1 OR p.message_pending > 0) AND p.update_error <> ' . $db->quote('') . ') AS failed,'
            . ' MAX(CASE WHEN (p.update_pending = 1 OR p.message_pending > 0) AND p.update_error <> ' . $db->quote('') . ' THEN p.update_error END) AS error'
            . ' FROM ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = p.orderid'
            . ' WHERE ' . self::walletCondition('p') . ' AND o.eventid = ' . $eventid);
        $row = $db->loadObject();

        return ['pending' => (int) ($row->pending ?? 0), 'failed' => (int) ($row->failed ?? 0), 'error' => (string) ($row->error ?? '')];
    }

    /**
     * Marks the passes of an event to be brought in line with their tickets (a new time, venue or
     * name; refunded tickets become inactive).
     *
     * @return  int  the number of passes marked
     */
    public static function markEvent(int $eventid): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('UPDATE ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = p.orderid'
            . ' SET p.update_pending = 1 WHERE ' . self::walletCondition('p') . ' AND o.eventid = ' . $eventid);
        $db->execute();

        return $db->getAffectedRows();
    }

    /**
     * Marks the passes of one order, and starts sending them. Called when a ticket of the order
     * is refunded, blocked or gets a new QR code; never lets a failure reach the caller.
     */
    public static function orderChanged($ordercode): void
    {
        if (!self::enabled())
        {
            return;
        }

        try
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $db->setQuery('UPDATE ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
                . ' SET p.update_pending = 1 WHERE ' . self::walletCondition('p')
                . ' AND p.ordercode = ' . $db->quote((string) $ordercode));
            $db->execute();

            if ($db->getAffectedRows() > 0)
            {
                self::process(10, 8);
            }
        }
        catch (\Throwable $e)
        {
            // The pass is marked; the Scheduled Task sends it later
        }
    }

    /**
     * The tickets with these order ids are about to be deleted (a removed order, or tickets removed
     * from one): marks their passes as removed and sends that to the wallets, so the passes don't
     * stay in the customers' wallets as if nothing happened. Call it before the rows are deleted.
     *
     * Google: the pass expires, which takes it off the active passes. Apple: a pass can't be
     * taken out of a wallet from outside, so it is marked void and expired; the iPhones that have
     * it fetch that version, built here from the ticket while it still exists and stripped of
     * everything about the customer. Never lets a failure reach the caller.
     *
     * @param   int[]  $orderids  orderid of the order rows that are being deleted
     */
    public static function ticketsRemoved(array $orderids): void
    {
        $orderids = array_values(array_unique(array_filter(array_map('intval', $orderids))));

        // Live updates are off or nothing was handed out: nothing to tell. Passes of a wallet whose live
        // updates are off are left alone, as before.
        if (!$orderids || !self::enabled())
        {
            return;
        }

        try
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $db->setQuery($db->getQuery(true)
                ->select('p.*')
                ->from($db->quoteName('#__ticketstation_wallet_passes', 'p'))
                ->where(self::walletCondition('p'))
                ->whereIn($db->quoteName('p.orderid'), $orderids));
            $passes = $db->loadObjectList();

            $tickets = [];

            foreach ($passes as $pass)
            {
                $snapshot = null;

                if ($pass->wallet === Wallet::APPLE)
                {
                    $tickets[$pass->ordercode] ??= Wallet::tickets((int) $pass->ordercode, false);

                    foreach ($tickets[$pass->ordercode] as $ticket)
                    {
                        if ((int) $ticket->orderid === (int) $pass->orderid)
                        {
                            $json     = WalletApple::removedJson($ticket);
                            $snapshot = $json !== null ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
                        }
                    }
                }

                $db->setQuery($db->getQuery(true)
                    ->update($db->quoteName('#__ticketstation_wallet_passes'))
                    ->set([
                        $db->quoteName('removed') . ' = 1',
                        $db->quoteName('update_pending') . ' = 1',
                        $db->quoteName('message_pending') . ' = 0',
                        $db->quoteName('snapshot') . ' = ' . ($snapshot !== null ? $db->quote($snapshot) : 'NULL'),
                    ])
                    ->where($db->quoteName('id') . ' = ' . (int) $pass->id))->execute();
            }

            if ($passes)
            {
                self::process(25, 8);
            }
        }
        catch (\Throwable $e)
        {
            // The passes are marked; the Scheduled Task finishes it
        }
    }

    /**
     * Stores a message for the buyers of an event and marks their passes to show it.
     *
     * @return  int  the number of passes that will show it
     */
    public static function queueMessage(int $eventid, string $header, string $body): int
    {
        $db  = Factory::getContainer()->get('DatabaseDriver');
        $row = (object) [
            'eventid' => $eventid,
            'header'  => mb_substr($header, 0, 100),
            'body'    => $body,
            'created' => Date::localNow(),
        ];

        $db->insertObject('#__ticketstation_wallet_messages', $row, 'id');

        // Valid tickets only: a refunded ticket has nothing to be told
        $db->setQuery('UPDATE ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = p.orderid'
            . ' SET p.message_pending = ' . (int) $row->id
            . ' WHERE ' . self::walletCondition('p') . ' AND o.eventid = ' . $eventid
            . ' AND ' . Refund::validSql('o'));
        $db->execute();

        return $db->getAffectedRows();
    }

    /**
     * The reason the most recent failed pass gave, for the message after a failed run.
     */
    public static function lastError(): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT update_error FROM ' . $db->quoteName('#__ticketstation_wallet_passes')
            . ' WHERE update_pending + message_pending > 0 AND update_error <> ' . $db->quote('')
            . ' ORDER BY id DESC', 0, 1);

        return (string) $db->loadResult();
    }

    /**
     * The passes still waiting to be sent, and those that failed last time.
     *
     * @return  array{pending: int, failed: int}
     */
    public static function status(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT COUNT(*) AS pending, SUM(update_error <> ' . $db->quote('') . ') AS failed'
            . ' FROM ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' WHERE ' . self::walletCondition('p', true) . ' AND (update_pending = 1 OR message_pending > 0)');
        $row = $db->loadObject();

        return ['pending' => (int) ($row->pending ?? 0), 'failed' => (int) ($row->failed ?? 0)];
    }

    /**
     * Sends pending changes and messages, oldest first, until the portion or the time is used up.
     * A pass that fails keeps its mark and the reason, and is tried again next time.
     *
     * @param   int  $limit    The most passes to handle.
     * @param   int  $seconds  The most time to take.
     *
     * @return  array{done: int, failed: int, notfound: int, review: int, remaining: int}
     */
    public static function process(int $limit = 40, int $seconds = 20): array
    {
        $result = ['done' => 0, 'failed' => 0, 'notfound' => 0, 'review' => 0, 'remaining' => 0];

        if (!self::enabled())
        {
            return $result;
        }

        $started  = time();
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $issuerId = trim((string) Wallet::config()->wallet_google_issuer_id);

        $db->setQuery($db->getQuery(true)
            ->select('p.*')
            ->from($db->quoteName('#__ticketstation_wallet_passes', 'p'))
            ->where(self::walletCondition('p', true))
            ->where('(p.update_pending = 1 OR p.message_pending > 0)')
            ->order(['(p.update_error <> ' . $db->quote('') . ')', 'p.id']), 0, $limit);
        $passes = $db->loadObjectList();

        $tickets  = [];
        $messages = [];
        $classes  = [];

        foreach ($passes as $pass)
        {
            if (time() - $started >= $seconds)
            {
                break;
            }

            try
            {
                // The ticket is gone: a Google pass can't be deleted, but it can expire, which takes
                // it off the customer's active passes. Nothing is left to remember afterwards.
                if ((int) $pass->removed === 1 && $pass->wallet === Wallet::GOOGLE)
                {
                    WalletGoogleApi::patchObject($pass->pass_id, ['state' => 'EXPIRED']);

                    $db->setQuery('DELETE FROM ' . $db->quoteName('#__ticketstation_wallet_passes') . ' WHERE id = ' . (int) $pass->id)->execute();
                    $result['done']++;

                    continue;
                }

                if ($pass->wallet === Wallet::APPLE)
                {
                    $error = self::applyApple($pass);

                    self::finish((int) $pass->id, $error);
                    $result['done']++;

                    continue;
                }

                $tickets[$pass->ordercode] ??= Wallet::tickets((int) $pass->ordercode, false);
                $ticket = null;

                foreach ($tickets[$pass->ordercode] as $candidate)
                {
                    if ((int) $candidate->orderid === (int) $pass->orderid)
                    {
                        $ticket = $candidate;
                    }
                }

                $found = $ticket !== null;

                if ($ticket !== null)
                {
                    if ((int) $pass->update_pending === 1)
                    {
                        $found = self::update($pass, $ticket, $tickets[$pass->ordercode], $issuerId, $classes, $result) && $found;
                    }

                    if ((int) $pass->message_pending > 0)
                    {
                        $messages[$pass->message_pending] ??= self::message((int) $pass->message_pending);

                        if ($messages[$pass->message_pending] !== null)
                        {
                            $found = WalletGoogleApi::addMessage($pass->pass_id, $messages[$pass->message_pending]->header, $messages[$pass->message_pending]->body) && $found;
                        }
                    }
                }

                // A pass Google doesn't know was never saved by the customer: nothing to update
                self::finish((int) $pass->id, $found ? '' : 'Not found at Google (the customer never saved this pass)');
                $result[$found ? 'done' : 'notfound']++;
            }
            catch (\Throwable $e)
            {
                self::finish((int) $pass->id, $e->getMessage(), true);
                $result['failed']++;
            }
        }

        $result['remaining'] = self::status()['pending'];

        return $result;
    }

    /**
     * Whether an Apple pass notifies with its message. A phone with several passes of one order
     * gets one notification, not one per pass: the pass with the lowest id notifies, a later one
     * only when a phone has it that no earlier pass of the order with the same message reaches.
     * A pass shared to another phone is the same pass with one more device, so that phone is
     * notified through it.
     */
    private static function notifies(object $pass): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__ticketstation_wallet_devices', 'd')
            . ' WHERE d.pass_row = ' . (int) $pass->id
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_wallet_devices', 'd2')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_wallet_passes', 'p2') . ' ON p2.id = d2.pass_row'
            . ' WHERE d2.device_id = d.device_id AND p2.wallet = ' . $db->quote(Wallet::APPLE)
            . ' AND p2.ordercode = ' . $db->quote((string) $pass->ordercode)
            . ' AND p2.id < ' . (int) $pass->id . ' AND p2.removed = 0'
            . ' AND (p2.message_pending = ' . (int) $pass->message_pending . ' OR ABS(p2.message_shown) = ' . (int) $pass->message_pending . '))');

        return (int) $db->loadResult() > 0;
    }

    /**
     * An Apple pass: marks it as changed, makes its message the current one, and pushes the
     * devices that registered for it.
     *
     * @return  string  A remark when a push failed (the pass is done anyway: the device gets the
     *                  change when Wallet is opened), else an empty string
     */
    private static function applyApple(object $pass): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $fields = [$db->quoteName('updated_at') . ' = ' . (int) round(microtime(true) * 1000)];

        if ((int) $pass->message_pending > 0)
        {
            // Negative: shown without a notification, as the phone is notified on another pass
            $fields[] = $db->quoteName('message_shown') . ' = ' . (self::notifies($pass) ? 1 : -1) * (int) $pass->message_pending;
        }

        $db->setQuery($db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_wallet_passes'))
            ->set($fields)
            ->where($db->quoteName('id') . ' = ' . (int) $pass->id))->execute();

        $db->setQuery($db->getQuery(true)
            ->select(['id', 'push_token'])
            ->from($db->quoteName('#__ticketstation_wallet_devices'))
            ->where($db->quoteName('pass_row') . ' = ' . (int) $pass->id));
        $remark = '';

        foreach ($db->loadObjectList() as $device)
        {
            $push = WalletApplePush::send((string) $device->push_token);

            // The device no longer has the pass
            if ($push['status'] === 410)
            {
                $db->setQuery('DELETE FROM ' . $db->quoteName('#__ticketstation_wallet_devices') . ' WHERE id = ' . (int) $device->id)->execute();
            }
            elseif ($push['status'] !== 200)
            {
                $remark = 'Push to an iPhone failed: ' . ($push['reason'] !== '' ? $push['reason'] : 'HTTP ' . $push['status']);
            }
        }

        return $remark;
    }

    /**
     * Brings the class (once per run) and the object of a Google pass in line with the ticket.
     *
     * @param   object[]  $orderTickets  Every ticket of the order, for the number of the ticket
     * @param   array     $classes       Classes already patched in this run
     * @param   array     $result        The counters of process(): classes that Google keeps under review are counted
     *
     * @return  bool  false when Google doesn't have the pass
     */
    private static function update(object $pass, object $ticket, array $orderTickets, string $issuerId, array &$classes, array &$result): bool
    {
        $valid = (int) $ticket->refund_state < Refund::TICKET_INVALID && (int) ($ticket->blacklisted ?? 0) === 0;

        if ($pass->class_id !== '' && empty($classes[$pass->class_id]))
        {
            $class = WalletGoogle::passClass($ticket, $issuerId);
            unset($class['id']);
            $class['reviewStatus'] = 'UNDER_REVIEW';

            $answer = WalletGoogleApi::patchClass($pass->class_id, $class);

            // A changed class of an approved issuer is reviewed by Google first; the passes show the
            // old details until it is approved.
            if (is_array($answer) && ($answer['reviewStatus'] ?? '') === 'UNDER_REVIEW')
            {
                $result['review']++;
            }

            $classes[$pass->class_id] = true;
        }

        // The number of the ticket among the valid ones of its order, as when the pass was made
        $validTickets = array_values(array_filter($orderTickets, fn ($t) => (int) $t->refund_state < Refund::TICKET_INVALID && (int) ($t->blacklisted ?? 0) === 0));
        $position     = 1;

        foreach ($validTickets as $index => $candidate)
        {
            if ((int) $candidate->orderid === (int) $ticket->orderid)
            {
                $position = $index + 1;
            }
        }

        $object = WalletGoogle::passObject($ticket, $pass->class_id, $issuerId, $position, count($validTickets));
        unset($object['id'], $object['classId']);

        // The QR code of an invalid ticket stays; the pass just stops being valid
        $object['state'] = $valid ? 'ACTIVE' : 'INACTIVE';

        // A pass that has no textModulesData now (one ticket left) mustn't keep the old one
        if (!isset($object['textModulesData']))
        {
            $object['textModulesData'] = [];
        }

        return WalletGoogleApi::patchObject($pass->pass_id, $object);
    }

    private static function message(int $id): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($db->getQuery(true)
            ->select(['header', 'body'])
            ->from($db->quoteName('#__ticketstation_wallet_messages'))
            ->where($db->quoteName('id') . ' = ' . $id));

        return $db->loadObject() ?: null;
    }

    /**
     * Clears what was sent from a pass, or keeps it with the reason it failed.
     */
    private static function finish(int $id, string $error, bool $keep = false): void
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $fields = [$db->quoteName('update_error') . ' = ' . $db->quote(mb_substr($error, 0, 255))];

        if (!$keep)
        {
            $fields[] = $db->quoteName('update_pending') . ' = 0';
            $fields[] = $db->quoteName('message_pending') . ' = 0';
            $fields[] = $db->quoteName('updated') . ' = ' . $db->quote(Date::localNow());
        }

        $db->setQuery($db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_wallet_passes'))
            ->set($fields)
            ->where($db->quoteName('id') . ' = ' . $id));
        $db->execute();
    }
}
