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
use Joomla\CMS\Language\Text;
use Mollie\Api\MollieApiClient;

/**
 * Refunds and chargebacks of an order, kept in #__ticketstation_refunds.
 *
 * A refund is made in the Box Office (sent to Mollie, or registered by hand for an order paid
 * outside Mollie), or in the Mollie dashboard, in which case Mollie's webhook reports it and it
 * waits under "Needs attention" until an admin has decided what happens to the tickets.
 *
 * The order rows stay when their tickets are refunded, so the order stays in the Box Office.
 * Each row's refund_state says what happened to its ticket (see the TICKET_ constants); only a
 * released ticket gives its place back, which is why capacity counts use heldSql().
 */
class Refund
{
    /** The ticket was not touched by a refund. */
    public const TICKET_UNCHANGED = 0;

    /** Refunded (e.g. as a gesture), the ticket stays valid. */
    public const TICKET_VALID = 1;

    /** Invalid for the scanner, its place stays taken. */
    public const TICKET_INVALID = 2;

    /** Invalid for the scanner and its place is for sale again. */
    public const TICKET_RELEASED = 3;

    /** A refund from Mollie waits for a decision about the tickets. */
    public const ATTENTION_DECISION = 1;

    /** A refund failed or a chargeback was reversed after the tickets were dealt with. */
    public const ATTENTION_FAILED = 2;

    /**
     * Statuses of money that went, or is going, back to the customer. A Mollie refund can still
     * fail or be cancelled while queued or pending; a chargeback can be reversed.
     */
    public const COUNTING = ['queued', 'pending', 'processing', 'refunded', 'manual', 'charged_back'];

    /**
     * Statuses from which the money can't be stopped any more: a Mollie refund can only be
     * cancelled while queued or pending. The chosen treatment of the tickets waits until a refund
     * reaches one of these, so cancelling a refund in Mollie leaves the tickets as they were.
     * Mollie calls the webhook when a refund reaches processing, refunded or failed.
     */
    public const FINAL = ['processing', 'refunded', 'manual', 'charged_back'];

    /**
     * Condition for order rows that still hold their place: every row except a released one.
     * Every count of order rows against a capacity uses it.
     */
    public static function heldSql(string $alias = ''): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $db->quoteName(($alias !== '' ? $alias . '.' : '') . 'refund_state') . ' <> ' . self::TICKET_RELEASED;
    }

    /**
     * Condition for order rows whose ticket is still valid: not made invalid by a refund. Ticket
     * files and ticket mails only cover these.
     */
    public static function validSql(string $alias = ''): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $db->quoteName(($alias !== '' ? $alias . '.' : '') . 'refund_state') . ' < ' . self::TICKET_INVALID;
    }

    /**
     * Condition for refunds that count as money given back.
     */
    public static function countingSql(string $alias = ''): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $db->quoteName(($alias !== '' ? $alias . '.' : '') . 'status')
            . ' IN (' . implode(',', array_map([$db, 'quote'], self::COUNTING)) . ')';
    }

    public static function counts(string $status): bool
    {
        return in_array($status, self::COUNTING, true);
    }

    /**
     * The refunds of an order, oldest first, with the names of who made and decided them.
     */
    public static function forOrder(int $ordercode): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['r.*', 'uc.name AS created_by_name', 'ud.name AS decided_by_name'])
            ->from($db->quoteName('#__ticketstation_refunds', 'r'))
            ->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('r.created_by'))
            ->join('LEFT', $db->quoteName('#__users', 'ud') . ' ON ' . $db->quoteName('ud.id') . ' = ' . $db->quoteName('r.decided_by'))
            ->where($db->quoteName('r.ordercode') . ' = ' . $ordercode)
            ->order($db->quoteName('r.created') . ' ASC, ' . $db->quoteName('r.id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    public static function get(int $id): ?object
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_refunds'))
            ->where($db->quoteName('id') . ' = ' . $id);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Per order what was refunded and whether a refund needs attention, for the Box Office list.
     *
     * @param   string[]  $codes
     *
     * @return  array  ordercode => (object) refunded, attention
     */
    public static function summaries(array $codes): array
    {
        if (!$codes) {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('ordercode'),
                'SUM(CASE WHEN ' . self::countingSql() . ' THEN ' . $db->quoteName('amount') . ' ELSE 0 END) AS refunded',
                'MAX(' . $db->quoteName('attention') . ') AS attention',
            ])
            ->from($db->quoteName('#__ticketstation_refunds'))
            ->where($db->quoteName('ordercode') . ' IN (' . implode(',', array_map('intval', $codes)) . ')')
            ->group($db->quoteName('ordercode'));

        $db->setQuery($query);

        $summaries = [];

        foreach ($db->loadObjectList() as $row) {
            $summaries[(string) $row->ordercode] = (object) [
                'refunded'  => round((float) $row->refunded, 2),
                'attention' => (int) $row->attention,
            ];
        }

        return $summaries;
    }

    /**
     * Total refunded for an order (refunds and chargebacks that count).
     */
    public static function refundedAmount(int $ordercode): float
    {
        return self::summaries([(string) $ordercode])[(string) $ordercode]->refunded ?? 0.0;
    }

    /**
     * Whether an order was refunded in full, given what was paid.
     */
    public static function isFull(float $refunded, float $paid): bool
    {
        return $refunded > 0 && $refunded >= round($paid, 2) - 0.004;
    }

    /**
     * What the customer paid: the latest transaction, or else the order total.
     */
    public static function paidAmount(int $ordercode): float
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('amount'))
            ->from($db->quoteName('#__ticketstation_transactions'))
            ->where($db->quoteName('orderid') . ' = ' . $ordercode)
            ->order($db->quoteName('pid') . ' DESC');

        $db->setQuery($query, 0, 1);
        $amount = (float) $db->loadResult();

        return round($amount > 0 ? $amount : OrderTotals::get($ordercode)->total, 2);
    }

    /**
     * The Mollie payment the order was paid with, or '' when it was paid outside Mollie (Box
     * Office, reservation). The webhook stores the payment id on the processed attempt.
     */
    public static function molliePaymentId(int $ordercode): string
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('message'))
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode)
            ->where($db->quoteName('processed') . ' = 1')
            ->where($db->quoteName('message') . ' LIKE ' . $db->quote('tr\_%'))
            ->order($db->quoteName('id') . ' DESC');

        $db->setQuery($query, 0, 1);

        return (string) $db->loadResult();
    }

    /**
     * A Mollie client with the key of the current mode (test or live).
     *
     * @throws  \RuntimeException  when no key is set.
     */
    public static function mollieClient(): MollieApiClient
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery('SELECT * FROM ' . $db->quoteName('#__ticketstation_mollie') . ' WHERE ' . $db->quoteName('configid') . ' = 1');
        $config = $db->loadObject();

        $key = $config && $config->test_mode == '1' ? $config->api_key_test : ($config->api_key ?? '');

        if (empty($key)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NO_KEY'));
        }

        require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

        $mollie = new MollieApiClient();
        $mollie->setApiKey($key);

        return $mollie;
    }

    /**
     * A status, method or other value from Mollie as a plain string. Since version 4 of Mollie's
     * library such fields hold an enum case for the values it knows and a string for the rest.
     */
    public static function mollieValue($value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * Refunds (part of) a paid order and records what happens to its tickets. With a Mollie
     * payment the refund is made at Mollie; nothing is stored when Mollie refuses it. The tickets
     * only change once the refund can't be cancelled any more (see FINAL): usually a day later,
     * when Mollie reports it as processing. Without a Mollie payment the refund is registered as
     * paid back by hand and the tickets change straight away.
     *
     * @param   array  $treatments  orderid => TICKET_ constant
     *
     * @return  int  The id of the new refund.
     *
     * @throws  \RuntimeException  with a message for the admin; nothing was changed then.
     */
    public static function create(int $ordercode, float $amount, string $description, array $treatments): int
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_AMOUNT'));
        }

        if (!self::isPaid($ordercode)) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NOT_PAID'));
        }

        $description = trim($description) !== '' ? trim($description) : Text::sprintf('COM_TICKETSTATION_REFUND_DEFAULT_DESCRIPTION', $ordercode);
        $description = mb_substr($description, 0, 255);
        $paymentId   = self::molliePaymentId($ordercode);
        $userId      = (int) Factory::getApplication()->getIdentity()->id;

        $refund = (object) [
            'ordercode'         => $ordercode,
            'type'              => 'refund',
            'mollie_payment_id' => $paymentId,
            'amount'            => $amount,
            'description'       => $description,
            'source'            => 'ticketstation',
            'attention'         => 0,
            'created'           => Factory::getDate()->toSql(),
            'created_by'        => $userId,
            'decided'           => Factory::getDate()->toSql(),
            'decided_by'        => $userId,
            'treatments'        => self::encodeTreatments($treatments),
        ];

        if ($paymentId !== '') {
            try {
                $payment = self::mollieClient()->payments->get($paymentId);

                if (!$payment->canBeRefunded()) {
                    throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NOT_REFUNDABLE'));
                }

                if ($amount > $payment->getAmountRemaining() + 0.004) {
                    throw new \RuntimeException(Text::sprintf('COM_TICKETSTATION_REFUND_ERROR_TOO_MUCH', number_format($payment->getAmountRemaining(), 2, ',', '')));
                }

                $currency = $payment->amount->currency;

                $mollieRefund = $payment->refund([
                    'amount'      => ['currency' => $currency, 'value' => number_format($amount, 2, '.', '')],
                    'description' => $description,
                    'metadata'    => ['source' => 'ticketstation', 'ordercode' => $ordercode],
                ]);
            } catch (\Mollie\Api\Exceptions\MollieException $e) {
                throw new \RuntimeException(Text::sprintf('COM_TICKETSTATION_REFUND_ERROR_MOLLIE', $e->getPlainMessage()));
            }

            $refund->mollie_id = $mollieRefund->id;
            $refund->currency  = $currency;
            $refund->status    = self::mollieValue($mollieRefund->status);
        } else {
            $remaining = round(self::paidAmount($ordercode) - self::refundedAmount($ordercode), 2);

            if ($amount > $remaining + 0.004) {
                throw new \RuntimeException(Text::sprintf('COM_TICKETSTATION_REFUND_ERROR_TOO_MUCH', number_format(max(0, $remaining), 2, ',', '')));
            }

            $refund->mollie_id = null;
            $refund->currency  = self::currency();
            $refund->status    = 'manual';
        }

        $id = self::store($refund);

        History::log($ordercode, 'refund_created', ($paymentId !== '' ? 'Refund of ' : 'Manual refund of ')
            . number_format($amount, 2, '.', '') . ' ' . $refund->currency . ($paymentId !== '' ? ' made at Mollie (' . $refund->mollie_id . ')' : ' registered'),
            ['amount' => $amount, 'refund' => $id, 'mollie_id' => $refund->mollie_id]);

        self::applyPending($id);

        return $id;
    }

    /**
     * Records the decision about the tickets for a refund reported by Mollie. Like a refund made
     * in the Box Office, the tickets change once the refund can't be cancelled any more; Mollie
     * only reports refunds from processing on, so that is normally straight away.
     *
     * @param   array  $treatments  orderid => TICKET_ constant
     */
    public static function decide(int $ordercode, int $id, array $treatments): void
    {
        $refund = self::get($id);

        if (!$refund || (int) $refund->ordercode !== $ordercode) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NOT_FOUND'));
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_refunds'))
            ->set($db->quoteName('decided') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('decided_by') . ' = ' . (int) Factory::getApplication()->getIdentity()->id)
            ->set($db->quoteName('attention') . ' = 0')
            ->set($db->quoteName('treatments') . ' = ' . $db->quote(self::encodeTreatments($treatments)))
            ->set($db->quoteName('applied') . ' = NULL')
            ->where($db->quoteName('id') . ' = ' . $id);

        $db->setQuery($query)->execute();

        History::log($ordercode, 'refund_decided', 'Decision taken for the ' . ($refund->type === 'chargeback' ? 'chargeback' : 'refund')
            . ' of ' . number_format((float) $refund->amount, 2, '.', '') . ' ' . $refund->currency, ['refund' => $id]);

        self::applyPending($id);
    }

    /**
     * Whether the chosen treatment of the tickets still waits for the refund to become final.
     */
    public static function isWaiting(object $refund): bool
    {
        return !empty($refund->treatments) && empty($refund->applied);
    }

    /**
     * Gives the tickets the treatment chosen for a refund, once the refund is final (see FINAL)
     * and only once. The ticket files are made again when a ticket became invalid.
     *
     * @return  bool  Whether the treatment was applied now.
     */
    public static function applyPending(int $id): bool
    {
        $refund = self::get($id);

        if (!$refund || !self::isWaiting($refund) || !in_array($refund->status, self::FINAL, true)) {
            return false;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_refunds'))
            ->set($db->quoteName('applied') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('applied') . ' IS NULL');

        // Only the request that marks it applied goes on, so a webhook and a sync at the same
        // moment can't both do it.
        if (!$db->setQuery($query)->execute() || $db->getAffectedRows() < 1) {
            return false;
        }

        $result = self::applyTreatments((int) $refund->ordercode, $id, (array) json_decode($refund->treatments, true));

        if ($result->invalidated) {
            self::refreshTicketFiles((int) $refund->ordercode);
        }

        return true;
    }

    /**
     * A treatment that became invalid only removes the order's combined ticket file. When the
     * order's other tickets had been created, their files are made again without the invalid
     * ones, so a resend or download only holds valid tickets.
     */
    public static function refreshTicketFiles(int $ordercode): void
    {
        $folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

        if (file_exists(Tickets::combinedPath($ordercode))) {
            return;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['a.orderid', 'a.ordercode'])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON ' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('a.orderid'))
            ->where($db->quoteName('a.ordercode') . ' = ' . $db->quote($ordercode))
            ->where($db->quoteName('a.paid') . ' = 1')
            ->where($db->quoteName('a.pdfcreated') . ' = 1')
            ->where(self::validSql('a'));
        Tickets::orderForPdf($query, 'a');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        // A single ticket left whose own file still exists needs nothing.
        if (!$rows || (count($rows) === 1 && file_exists(Tickets::singlePath($ordercode)))) {
            return;
        }

        ticketcreator::createOrderFile($ordercode, array_map('intval', array_column($rows, 'orderid')));

        History::log($ordercode, 'tickets_generated', 'Tickets generated without the invalid ones (QR codes unchanged)');
    }

    /**
     * The chosen treatments as stored: only tickets that change, as JSON, or null for none.
     */
    private static function encodeTreatments(array $treatments): ?string
    {
        $treatments = array_filter(array_map('intval', $treatments), static function ($state) {
            return in_array($state, [self::TICKET_VALID, self::TICKET_INVALID, self::TICKET_RELEASED], true);
        });

        return $treatments ? json_encode($treatments) : null;
    }

    /**
     * Takes a failed refund or reversed chargeback off the "Needs attention" list.
     */
    public static function acknowledge(int $ordercode, int $id): void
    {
        $refund = self::get($id);

        if (!$refund || (int) $refund->ordercode !== $ordercode) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NOT_FOUND'));
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_refunds'))
            ->set($db->quoteName('attention') . ' = 0')
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('attention') . ' = ' . self::ATTENTION_FAILED);

        $db->setQuery($query)->execute();

        History::log($ordercode, 'refund_acknowledged', 'Failed or reversed ' . ($refund->type === 'chargeback' ? 'chargeback' : 'refund') . ' acknowledged', ['refund' => $id]);
    }

    /**
     * Gives the tickets of an order their treatment. A released ticket can't be changed again:
     * its place may have been sold to someone else. A released ticket gives its seat back and
     * goes to the waiting list; the files of a ticket that is no longer valid are removed.
     *
     * @param   array  $treatments  orderid => TICKET_ constant; TICKET_UNCHANGED leaves a ticket alone.
     *
     * @return  object  changed (number of tickets), invalidated (bool: a ticket became invalid)
     */
    public static function applyTreatments(int $ordercode, int $refundId, array $treatments): object
    {
        $result = (object) ['changed' => 0, 'invalidated' => false];

        $treatments = array_filter(array_map('intval', $treatments), static function ($state) {
            return in_array($state, [self::TICKET_VALID, self::TICKET_INVALID, self::TICKET_RELEASED], true);
        });

        if (!$treatments) {
            return $result;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
            ->whereIn($db->quoteName('orderid'), array_map('intval', array_keys($treatments)));

        $db->setQuery($query);

        $tickets  = new Tickets;
        $released = [];
        $counts   = [self::TICKET_VALID => 0, self::TICKET_INVALID => 0, self::TICKET_RELEASED => 0];

        foreach ($db->loadObjectList() as $row) {
            $state = $treatments[(int) $row->orderid];

            if ((int) $row->refund_state === self::TICKET_RELEASED || (int) $row->refund_state === $state) {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ticketstation_orders'))
                ->set($db->quoteName('refund_state') . ' = ' . $state)
                ->set($db->quoteName('refund_id') . ' = ' . $refundId)
                ->where($db->quoteName('orderid') . ' = ' . (int) $row->orderid);

            $db->setQuery($query)->execute();

            if ($state >= self::TICKET_INVALID) {
                $tickets->removeTicketFromServer($ordercode, $row->barcode);
                $result->invalidated = true;
            }

            if ($state === self::TICKET_RELEASED) {
                if ((int) $row->seat_sector !== 0) {
                    $tickets->resetSeatSate((int) $row->orderid);
                }

                $released[] = (int) $row->ticketid;
            }

            $counts[$state]++;
            $result->changed++;
        }

        if ($result->invalidated) {
            // The combined file still holds the tickets that are no longer valid.
            $tickets->removeCombinedTicketFromServer($ordercode);
            $tickets->removeMultiTicketFromServer($ordercode);
        }

        $labels = [
            self::TICKET_VALID    => 'refunded, still valid',
            self::TICKET_INVALID  => 'made invalid, place kept',
            self::TICKET_RELEASED => 'made invalid and released',
        ];

        foreach ($counts as $state => $count) {
            if ($count > 0) {
                History::log($ordercode, 'refund_tickets', $count . ' ticket(s) ' . $labels[$state], ['refund' => $refundId, 'state' => $state]);
            }
        }

        // The released tickets go to the waiting list first, as with a deleted order.
        if ($released) {
            (new WaitingList)->promote($released);
        }

        return $result;
    }

    /**
     * Stores the refunds and chargebacks Mollie has for the order's payment. New ones made in the
     * Mollie dashboard wait for a decision; a refund that fails or a chargeback that is reversed
     * after its decision is flagged again. Called by the webhook and by "Sync with Mollie".
     *
     * @param   \Mollie\Api\Resources\Payment  $payment
     *
     * @return  int  The number of new refunds and chargebacks.
     */
    public static function syncFromMollie(int $ordercode, $payment): int
    {
        $new = 0;

        foreach ($payment->refunds() as $refund) {
            $new += self::report($ordercode, $payment->id, 'refund', $refund->id, (float) $refund->amount->value, $refund->amount->currency,
                (string) $refund->description, self::mollieValue($refund->status), (string) $refund->createdAt,
                isset($refund->metadata->source) && $refund->metadata->source === 'ticketstation');
        }

        if ($payment->hasChargebacks()) {
            foreach ($payment->chargebacks() as $chargeback) {
                $new += self::report($ordercode, $payment->id, 'chargeback', $chargeback->id, (float) $chargeback->amount->value, $chargeback->amount->currency,
                    (string) ($chargeback->reason->description ?? ''), $chargeback->reversedAt ? 'reversed' : 'charged_back', (string) $chargeback->createdAt, false);
            }
        }

        return $new;
    }

    /**
     * Fetches the order's payment from Mollie and stores its refunds (see syncFromMollie()).
     *
     * @return  int  The number of new refunds and chargebacks.
     *
     * @throws  \RuntimeException
     */
    public static function sync(int $ordercode): int
    {
        $paymentId = self::molliePaymentId($ordercode);

        if ($paymentId === '') {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_REFUND_ERROR_NO_PAYMENT'));
        }

        try {
            return self::syncFromMollie($ordercode, self::mollieClient()->payments->get($paymentId));
        } catch (\Mollie\Api\Exceptions\MollieException $e) {
            throw new \RuntimeException(Text::sprintf('COM_TICKETSTATION_REFUND_ERROR_MOLLIE', $e->getPlainMessage()));
        }
    }

    /**
     * Minutes between two looks at Mollie's latest refunds, per admin session.
     */
    private const POLL_MINUTES = 5;

    /**
     * Looks at the latest refunds and chargebacks of the whole Mollie account and stores the ones
     * that belong to an order. The webhook only reports a refund once Mollie processes it (usually
     * the next working day), so without this a refund a colleague made in the Mollie Dashboard
     * would stay unnoticed until then. Called when the dashboard or the Box Office opens, at most
     * once every few minutes per session; errors are ignored, the webhook remains the fallback.
     *
     * @return  int  The number of new refunds and chargebacks.
     */
    public static function pollMollie(bool $force = false): int
    {
        $session = Factory::getApplication()->getSession();
        $last    = (int) $session->get('com_ticketstation.refunds.polled', 0);

        if (!$force && $last > time() - self::POLL_MINUTES * 60) {
            return 0;
        }

        $session->set('com_ticketstation.refunds.polled', time());

        try {
            $mollie = self::mollieClient();
            $found  = [];

            foreach ($mollie->refunds->page(null, 50) as $refund) {
                $found[] = ['refund', $refund->paymentId, $refund->id, (float) $refund->amount->value, $refund->amount->currency,
                    (string) $refund->description, self::mollieValue($refund->status), (string) $refund->createdAt,
                    isset($refund->metadata->source) && $refund->metadata->source === 'ticketstation'];
            }

            foreach ($mollie->chargebacks->page(null, 50) as $chargeback) {
                $found[] = ['chargeback', $chargeback->paymentId, $chargeback->id, (float) $chargeback->amount->value, $chargeback->amount->currency,
                    (string) ($chargeback->reason->description ?? ''), $chargeback->reversedAt ? 'reversed' : 'charged_back', (string) $chargeback->createdAt, false];
            }
        } catch (\Throwable $e) {
            return 0;
        }

        $orders = self::ordersForPayments(array_unique(array_column($found, 1)));
        $new    = 0;

        foreach ($found as [$type, $paymentId, $mollieId, $amount, $currency, $description, $status, $createdAt, $fromTicketstation]) {
            if (isset($orders[$paymentId])) {
                $new += self::report($orders[$paymentId], $paymentId, $type, $mollieId, $amount, $currency, $description, $status, $createdAt, $fromTicketstation);
            }
        }

        return $new;
    }

    /**
     * The orders paid with the given Mollie payments.
     *
     * @return  array  payment id => ordercode
     */
    private static function ordersForPayments(array $paymentIds): array
    {
        $paymentIds = array_values(array_filter($paymentIds));

        if (!$paymentIds) {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('message'), $db->quoteName('ordercode')])
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('processed') . ' = 1')
            ->where($db->quoteName('message') . ' IN (' . implode(',', array_map([$db, 'quote'], $paymentIds)) . ')');

        $db->setQuery($query);

        return array_map('intval', $db->loadAssocList('message', 'ordercode'));
    }

    /**
     * Deletes the refunds of an order, when the order itself is deleted.
     */
    public static function remove($ordercode): void
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_refunds'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query)->execute();
    }

    /**
     * The refunds that need attention, for the control panel: {decision, failed} with per kind
     * the number of refunds and, when there is exactly one, its ordercode and id.
     */
    public static function attention(): object
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('ordercode'), $db->quoteName('attention')])
            ->from($db->quoteName('#__ticketstation_refunds'))
            ->where($db->quoteName('attention') . ' > 0');

        $db->setQuery($query);

        $result = (object) [
            'decision' => (object) ['count' => 0, 'ordercode' => 0, 'id' => 0],
            'failed'   => (object) ['count' => 0, 'ordercode' => 0, 'id' => 0],
        ];

        foreach ($db->loadObjectList() as $row) {
            $kind = (int) $row->attention === self::ATTENTION_DECISION ? 'decision' : 'failed';

            $result->$kind->count++;
            $result->$kind->ordercode = (int) $row->ordercode;
            $result->$kind->id        = (int) $row->id;
        }

        return $result;
    }

    /**
     * A proposal for the tickets a refund of $amount from Mollie covers: all tickets when it
     * covers what is left of the order, otherwise tickets whose prices add up to exactly the
     * amount (with or without the service fee). Only tickets without a refund yet are used.
     *
     * @param   array  $rows  The order rows (orderid, price, discount, refund_state).
     *
     * @return  int[]  The orderids, or [] when no combination matches.
     */
    public static function propose(array $rows, float $amount, float $fee, float $remainingPaid): array
    {
        $open = array_values(array_filter($rows, static function ($row) {
            return (int) $row->refund_state === self::TICKET_UNCHANGED;
        }));

        if (!$open) {
            return [];
        }

        if ($amount >= $remainingPaid - 0.004) {
            return array_map(static fn ($row) => (int) $row->orderid, $open);
        }

        // Subset sum in cents, over at most 40 tickets.
        $open  = array_slice($open, 0, 40);
        $cents = array_map(static fn ($row) => (int) round(((float) $row->price - (float) $row->discount) * 100), $open);

        foreach (array_unique([(int) round($amount * 100), (int) round(($amount - $fee) * 100)]) as $target) {
            if ($target <= 0) {
                continue;
            }

            // $reach[sum] = the index of the ticket that first reached that sum, and the sum before it.
            $reach = [0 => null];

            foreach ($cents as $i => $value) {
                if ($value <= 0) {
                    continue;
                }

                foreach (array_keys($reach) as $sum) {
                    $next = $sum + $value;

                    if ($next <= $target && !array_key_exists($next, $reach)) {
                        $reach[$next] = [$i, $sum];
                    }
                }

                if (array_key_exists($target, $reach)) {
                    break;
                }
            }

            if (array_key_exists($target, $reach)) {
                $ids = [];

                for ($sum = $target; $sum > 0; $sum = $reach[$sum][1]) {
                    $ids[] = (int) $open[$reach[$sum][0]]->orderid;
                }

                return $ids;
            }
        }

        return [];
    }

    /**
     * Stores a refund or chargeback that Mollie reports, or updates its status.
     *
     * @return  int  1 when it was new.
     */
    private static function report(int $ordercode, string $paymentId, string $type, string $mollieId, float $amount, string $currency,
        string $description, string $status, string $createdAt, bool $fromTicketstation): int
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_refunds'))
            ->where($db->quoteName('mollie_id') . ' = ' . $db->quote($mollieId));

        $db->setQuery($query);
        $existing = $db->loadObject();
        $label    = $type === 'chargeback' ? 'Chargeback' : 'Refund';

        if (!$existing) {
            $refund = (object) [
                'ordercode'         => $ordercode,
                'type'              => $type,
                'mollie_id'         => $mollieId,
                'mollie_payment_id' => $paymentId,
                'amount'            => round($amount, 2),
                'currency'          => $currency,
                'description'       => mb_substr($description, 0, 255),
                'status'            => $status,
                'source'            => $fromTicketstation ? 'ticketstation' : 'mollie',
                'attention'         => !$fromTicketstation && self::counts($status) ? self::ATTENTION_DECISION : 0,
                'created'           => Factory::getDate($createdAt ?: 'now')->toSql(),
            ];

            self::store($refund);

            if (!$fromTicketstation) {
                History::log($ordercode, $type === 'chargeback' ? 'chargeback_reported' : 'refund_reported',
                    $label . ' of ' . number_format($amount, 2, '.', '') . ' ' . $currency . ' reported by Mollie (' . $mollieId . ')'
                    . ($refund->attention ? '; a decision about the tickets is needed' : ''),
                    ['amount' => $amount, 'mollie_id' => $mollieId, 'status' => $status], 'Mollie');
            }

            return $fromTicketstation ? 0 : 1;
        }

        if ($existing->status === $status) {
            return 0;
        }

        $attention  = (int) $existing->attention;
        $treatments = $existing->treatments;

        if (self::counts($existing->status) && !self::counts($status)) {
            // No money went back after all. Tickets whose treatment was still waiting stay as they
            // were, and there is nothing to decide any more. Only when the tickets were already
            // dealt with does the admin need to look at them again.
            $attention = !empty($existing->applied) ? self::ATTENTION_FAILED : 0;

            History::log($ordercode, 'refund_failed', $label . ' ' . $mollieId . ' is now ' . $status . ' at Mollie; no money went back'
                . (self::isWaiting($existing) ? '. The tickets were left unchanged' : ''),
                ['mollie_id' => $mollieId, 'status' => $status], 'Mollie');

            if (self::isWaiting($existing)) {
                $treatments = null;
            }
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_refunds'))
            ->set($db->quoteName('status') . ' = ' . $db->quote($status))
            ->set($db->quoteName('attention') . ' = ' . $attention)
            ->set($db->quoteName('treatments') . ' = ' . ($treatments === null ? 'NULL' : $db->quote($treatments)))
            ->where($db->quoteName('id') . ' = ' . (int) $existing->id);

        $db->setQuery($query)->execute();

        // Now final: the tickets get the treatment that was chosen for them.
        self::applyPending((int) $existing->id);

        return 0;
    }

    /**
     * Inserts a refund, or, when the webhook already stored the same Mollie refund, completes that
     * row with what the Box Office knows.
     *
     * @return  int  The refund's id.
     */
    private static function store(object $refund): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        if (!empty($refund->mollie_id)) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__ticketstation_refunds'))
                ->where($db->quoteName('mollie_id') . ' = ' . $db->quote($refund->mollie_id));

            $db->setQuery($query);
            $id = (int) $db->loadResult();

            if ($id) {
                $refund->id = $id;
                $db->updateObject('#__ticketstation_refunds', $refund, 'id');

                return $id;
            }
        }

        $db->insertObject('#__ticketstation_refunds', $refund, 'id');

        return (int) $refund->id;
    }

    private static function isPaid(int $ordercode): bool
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('paid') . ')')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }

    private static function currency(): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery('SELECT ' . $db->quoteName('currency') . ' FROM ' . $db->quoteName('#__ticketstation_mollie') . ' WHERE ' . $db->quoteName('configid') . ' = 1');

        return MollieCurrencies::fromConfig($db->loadResult());
    }
}
