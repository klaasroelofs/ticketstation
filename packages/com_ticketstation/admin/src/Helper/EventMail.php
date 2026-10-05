<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Mail to the buyers of an event: a message written by an admin (a new start time, another
 * location, a cancellation) and the automatic reminder shortly before the event.
 *
 * A buyer is a client with an order that is paid and still holds valid tickets for the event
 * (see audience()); one mail goes out per order, however many tickets it holds. Both kinds are
 * written to #__ticketstation_event_mails, and to the history of the order. The reminder uses
 * that table to send every order only once per event and start time.
 *
 * @since 2.21.0
 */
class EventMail
{
    public const KIND_UPDATE   = 'update';
    public const KIND_REMINDER = 'reminder';

    /** Mail templates (#__ticketstation_templates.mailid). */
    public const TEMPLATE_UPDATE   = 7;
    public const TEMPLATE_REMINDER = 8;

    /**
     * The orders that hold valid, paid tickets for an event, with the client's address.
     *
     * @return  object[]  ordercode, userid, emailaddress, firstname, name
     */
    public static function audience(int $eventid): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['o.ordercode', 'MIN(o.userid) AS userid', 'c.emailaddress', 'c.firstname', 'c.name'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->where($db->quoteName('o.eventid') . ' = ' . $eventid)
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where(Refund::validSql('o'))
            ->where($db->quoteName('c.emailaddress') . " <> ''")
            ->group(['o.ordercode', 'c.emailaddress', 'c.firstname', 'c.name'])
            ->order($db->quoteName('o.ordercode'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Sends the message of an admin to every buyer of an event.
     *
     * @param   int          $eventid  The event.
     * @param   string       $subject  Subject of the mail.
     * @param   string       $message  The text (HTML), placed where the template has {message}.
     * @param   string|null  $testTo   Send one copy to this address only, using the first order as
     *                                 the example; nothing is logged then.
     *
     * @return  array{sent: int, failed: string[], total: int}  failed holds the ordercodes
     */
    public static function sendUpdate(int $eventid, string $subject, string $message, ?string $testTo = null): array
    {
        $recipients = self::audience($eventid);

        if ($testTo !== null)
        {
            $recipients = array_slice($recipients, 0, 1);

            if ($recipients)
            {
                $recipients[0]->emailaddress = $testTo;
            }
        }

        $result = ['sent' => 0, 'failed' => [], 'total' => count($recipients)];

        if (!$recipients)
        {
            return $result;
        }

        @set_time_limit(0);

        $event = self::eventVariables($eventid);

        foreach ($recipients as $recipient)
        {
            $variables = self::orderVariables((int) $recipient->ordercode) + $event + [
                'subject' => $subject,
                'message' => $message,
            ];

            if ($testTo !== null)
            {
                $variables['subject'] = '[TEST] ' . $subject;
            }

            if (self::deliver(self::TEMPLATE_UPDATE, $recipient, $variables))
            {
                $result['sent']++;

                if ($testTo === null)
                {
                    self::log($eventid, (int) $recipient->ordercode, self::KIND_UPDATE, null);
                    History::log($recipient->ordercode, 'event_mail', 'Message about the event sent: ' . $subject);
                }
            }
            else
            {
                $result['failed'][] = $recipient->ordercode;
            }
        }

        return $result;
    }

    /**
     * Sends the reminder to the orders whose event starts within the reminder period (the
     * Configuration's reminder hours) and that didn't get it yet. Orders placed inside that
     * period are skipped: they have just received their tickets. Meant to be called regularly,
     * by the Scheduled Task.
     *
     * @return  array{sent: int, failed: int}
     */
    public static function sendReminders(): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $config = (new Config)->get(['reminder_on', 'reminder_hours']);

        if ((int) $config->reminder_on !== 1)
        {
            return $result;
        }

        $hours = max(1, (int) $config->reminder_hours ?: 24);
        $now   = Date::localNow();
        $until = Date::localNow('Y-m-d H:i:s', '+' . $hours . ' hours');
        $db    = Factory::getContainer()->get('DatabaseDriver');

        // One row per order, event and start time that is due, with the earliest doors time.
        $query = $db->getQuery(true)
            ->select([
                'o.ordercode', 'MIN(o.userid) AS userid', 't.eventid', 't.startdate', 'MIN(NULLIF(t.doors_open, \'\')) AS doors_open',
                'c.emailaddress', 'c.firstname', 'c.name',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->join('INNER', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where(Refund::validSql('o'))
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('c.emailaddress') . " <> ''")
            ->where($db->quoteName('t.startdate') . ' > ' . $db->quote($now))
            ->where($db->quoteName('t.startdate') . ' <= ' . $db->quote($until))
            // Bought before the reminder period began; later orders have just got their tickets.
            ->where($db->quoteName('o.orderdate') . ' <= DATE_SUB(t.startdate, INTERVAL ' . $hours . ' HOUR)')
            ->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_event_mails', 'm')
                . ' WHERE m.ordercode = o.ordercode AND m.eventid = t.eventid AND m.startdate = t.startdate AND m.kind = '
                . $db->quote(self::KIND_REMINDER) . ')')
            ->group(['o.ordercode', 't.eventid', 't.startdate', 'c.emailaddress', 'c.firstname', 'c.name'])
            ->order($db->quoteName('t.startdate'));

        $db->setQuery($query);
        $due = $db->loadObjectList();

        if (!$due)
        {
            return $result;
        }

        @set_time_limit(0);

        foreach ($due as $row)
        {
            $variables = self::orderVariables((int) $row->ordercode) + self::eventVariables((int) $row->eventid, $row->startdate, $row->doors_open);
            $variables['ticketlink'] = Uri::root() . 'index.php?option=com_ticketstation&view=losttickets';

            $ics = Calendar::ics((int) $row->ordercode);

            if (self::deliver(self::TEMPLATE_REMINDER, $row, $variables, $ics))
            {
                $result['sent']++;
                self::log((int) $row->eventid, (int) $row->ordercode, self::KIND_REMINDER, $row->startdate);
                History::log($row->ordercode, 'event_reminder', 'Reminder for the event sent');
            }
            else
            {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Sends one mail from a template. Failures (a mail server error) are reported by the return
     * value, not thrown: one bad address mustn't stop the others.
     */
    private static function deliver(int $template, object $recipient, array $variables, string $ics = ''): bool
    {
        try
        {
            $message = new eTicketsMessage;
            $message->id((string) $template)
                ->user((int) $recipient->userid)
                ->variables($variables);

            if ($ics !== '')
            {
                $message->stringAttachment($ics, 'event-' . (int) $recipient->ordercode . '.ics', 'text/calendar');
            }

            return $message->send();
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    /**
     * The order placeholders of the mail (ordercode, orderdate, orderlist, price).
     */
    private static function orderVariables(int $ordercode): array
    {
        return eTicketsMessage::orderVariables($ordercode);
    }

    /**
     * The event placeholders: name, date and time, doors, and the location of its first ticket.
     *
     * @param   string|null  $startdate  The start of the order's tickets; the earliest ticket of the event by default.
     * @param   string|null  $doors      The doors time (HH:MM); the first ticket's by default.
     */
    private static function eventVariables(int $eventid, ?string $startdate = null, ?string $doors = null): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['e.eventname', 't.startdate', 't.doors_open', 'v.venue', 'v.street', 'v.zipcode', 'v.city'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = IF(' . $db->quoteName('t.venue') . ' > 0, ' . $db->quoteName('t.venue') . ', ' . $db->quoteName('p.venue') . ')')
            ->where($db->quoteName('t.eventid') . ' = ' . $eventid)
            ->order($db->quoteName('t.startdate'));

        if ($startdate !== null)
        {
            $query->where($db->quoteName('t.startdate') . ' = ' . $db->quote($startdate));
        }

        $db->setQuery($query, 0, 1);
        $row = $db->loadObject();

        if (!$row)
        {
            return ['eventname' => '', 'eventdate' => '', 'eventtime' => '', 'doorsopen' => '', 'location' => ''];
        }

        $start   = $startdate ?? $row->startdate;
        $address = trim(trim((string) $row->street) . ', ' . trim(trim((string) $row->zipcode) . ' ' . trim((string) $row->city)), ', ');
        $opens   = Date::doorsOpen($start, $doors ?? $row->doors_open);

        return [
            'eventname' => (string) $row->eventname,
            'eventdate' => Date::long($start),
            'eventtime' => $start ? date('H:i', strtotime($start)) : '',
            'doorsopen' => $opens !== '' ? date('H:i', strtotime($opens)) : '',
            'location'  => implode(', ', array_filter([trim((string) $row->venue), $address])),
        ];
    }

    private static function log(int $eventid, int $ordercode, string $kind, ?string $startdate): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $row = (object) [
            'eventid'   => $eventid,
            'ordercode' => $ordercode,
            'kind'      => $kind,
            'startdate' => $startdate,
            'sent'      => Date::localNow(),
        ];

        $db->insertObject('#__ticketstation_event_mails', $row);
    }
}
