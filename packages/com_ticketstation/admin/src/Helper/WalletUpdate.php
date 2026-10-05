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
 * marks the passes it concerns as pending, and process() sends them to the wallet in portions: a
 * first portion right away, the rest by the Scheduled Task. A pass the customer never saved is
 * not an error: the wallet doesn't know it, so there is nothing to change.
 *
 * Only Google Wallet so far (see WalletGoogleApi).
 *
 * @since 2.22.0
 */
class WalletUpdate
{
    /**
     * Live updates are on, so changes are worth marking.
     */
    public static function enabled(): bool
    {
        return WalletGoogleApi::ready();
    }

    /**
     * The Google passes recorded for the orders of an event.
     */
    public static function passesOfEvent(int $eventid): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT COUNT(DISTINCT p.id) FROM ' . $db->quoteName('#__ticketstation_wallet_passes', 'p')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = p.orderid'
            . ' WHERE p.wallet = ' . $db->quote(Wallet::GOOGLE) . ' AND o.eventid = ' . $eventid);

        return (int) $db->loadResult();
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
            . ' SET p.update_pending = 1 WHERE p.wallet = ' . $db->quote(Wallet::GOOGLE) . ' AND o.eventid = ' . $eventid);
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

            $db->setQuery('UPDATE ' . $db->quoteName('#__ticketstation_wallet_passes')
                . ' SET update_pending = 1 WHERE wallet = ' . $db->quote(Wallet::GOOGLE)
                . ' AND ordercode = ' . $db->quote((string) $ordercode));
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
            . ' WHERE p.wallet = ' . $db->quote(Wallet::GOOGLE) . ' AND o.eventid = ' . $eventid
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
            . ' WHERE wallet = ' . $db->quote(Wallet::GOOGLE) . ' AND update_pending + message_pending > 0 AND update_error <> ' . $db->quote('')
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
            . ' FROM ' . $db->quoteName('#__ticketstation_wallet_passes')
            . ' WHERE wallet = ' . $db->quote(Wallet::GOOGLE) . ' AND (update_pending = 1 OR message_pending > 0)');
        $row = $db->loadObject();

        return ['pending' => (int) ($row->pending ?? 0), 'failed' => (int) ($row->failed ?? 0)];
    }

    /**
     * Sends pending changes and messages to Google, oldest first, until the portion or the time
     * is used up. A pass that fails keeps its mark and the reason, and is tried again next time.
     *
     * @param   int  $limit    The most passes to handle.
     * @param   int  $seconds  The most time to take.
     *
     * @return  array{done: int, failed: int, remaining: int}
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
            ->select('*')
            ->from($db->quoteName('#__ticketstation_wallet_passes'))
            ->where($db->quoteName('wallet') . ' = ' . $db->quote(Wallet::GOOGLE))
            ->where('(' . $db->quoteName('update_pending') . ' = 1 OR ' . $db->quoteName('message_pending') . ' > 0)')
            ->order(['(' . $db->quoteName('update_error') . " <> ''" . ')', $db->quoteName('id')]), 0, $limit);
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
     * Brings the class (once per run) and the object of a pass in line with the ticket.
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
