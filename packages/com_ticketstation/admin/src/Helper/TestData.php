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
use Joomla\CMS\Language\Text;
use Joomla\Database\QueryInterface;

/**
 * Keeps test data and live data apart.
 *
 * Every order, payment, invoice and waiting-list entry carries a flag "test": 1 when it was made
 * while the shop was in test mode (the switch on the control panel), 0 when it was made live. The
 * flag is set once, when the row is made, and never changes after that: switching the shop between
 * test and live changes what is shown, not what an order is.
 *
 * The Box Office, the payments overview, the statistics and the availability of tickets only look
 * at the data of the mode the shop is in, so a test order never shows up live and the other way
 * round. A single order, found by its code or id, is always found whatever its mode.
 */
final class TestData
{
    /** @var array<string, int>  ordercode => flag */
    private static array $orderModes = [];

    /**
     * The flag of what is made now: 1 in test mode, 0 live.
     */
    public static function mode(): int
    {
        return Shop::inTestMode() ? 1 : 0;
    }

    /**
     * Limits a query to the rows of one mode, by default the mode the shop is in.
     *
     * @param   QueryInterface  $query   The query.
     * @param   string          $column  The flag column, with the table alias if it has one ("o.test").
     * @param   int|null        $mode    0 (live) or 1 (test); null for the mode the shop is in.
     */
    public static function scope(QueryInterface $query, string $column = 'test', ?int $mode = null): QueryInterface
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $query->where($db->quoteName($column) . ' = ' . ($mode === null ? self::mode() : (int) $mode));
    }

    /**
     * The SQL condition for the same, to put in a hand-written query.
     */
    public static function condition(string $column = 'test', ?int $mode = null): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        return $db->quoteName($column) . ' = ' . ($mode === null ? self::mode() : (int) $mode);
    }

    /**
     * The mode an order was made in: 1 for a test order, 0 for a live one or an unknown order.
     */
    public static function ofOrder($ordercode): int
    {
        $ordercode = (string) $ordercode;

        if ($ordercode === '' || $ordercode === '0') {
            return 0;
        }

        if (!isset(self::$orderModes[$ordercode])) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('test'))
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode))
                ->setLimit(1);

            $db->setQuery($query);

            $mode = $db->loadResult();

            // An order that is not there (yet) counts as live and is looked up again next time.
            if ($mode === null) {
                return 0;
            }

            self::$orderModes[$ordercode] = (int) $mode;
        }

        return self::$orderModes[$ordercode];
    }

    /**
     * Whether an order was made in test mode.
     */
    public static function isTestOrder($ordercode): bool
    {
        return self::ofOrder($ordercode) === 1;
    }

    /**
     * What there is of test data, for the control panel: test orders (made by a customer, so no
     * empty carts; the orders the ticketcleaner removed count too), test customers and the
     * signups of the waiting list made in test mode.
     *
     * @return  object  orders, customers, waiting
     */
    public static function summary(): object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('ordercode') . ')')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('test') . ' = 1')
            ->where($db->quoteName('userid') . ' != 0');
        $db->setQuery($query);
        $orders = (int) $db->loadResult();

        $orders += count(History::getAutoRemovedGhosts(null, 1));

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('test') . ' = 1');
        $db->setQuery($query);
        $customers = (int) $db->loadResult();

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('test') . ' = 1')
            ->where($db->quoteName('processed') . ' = 0');
        $db->setQuery($query);

        return (object) ['orders' => $orders, 'customers' => $customers, 'waiting' => (int) $db->loadResult()];
    }

    /**
     * Frees the seats held by test orders, for when the shop goes live: a seat is held by one
     * order at a time, and a test order must not keep a seat from the customers. The test orders
     * stay, with their seat number; only the seat on the chart is let go.
     *
     * @return  int  The number of seats freed.
     */
    public static function releaseSeats(): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery(
            'UPDATE ' . $db->quoteName('#__ticketstation_seatplancoords', 'c')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_orders', 'o') . ' ON o.orderid = c.orderid'
            . ' SET c.booked = c.blocked, c.orderid = 0'
            . ' WHERE o.test = 1'
        )->execute();

        return $db->getAffectedRows();
    }

    /**
     * The notice at the top of a screen that lists orders, customers or payments, so nobody takes
     * test data for live data: empty while the shop is live.
     */
    public static function banner(): string
    {
        if (self::mode() !== 1) {
            return '';
        }

        return '<div class="alert alert-warning d-flex align-items-center gap-2" role="status">'
            . '<span class="fa fa-flask" aria-hidden="true"></span>'
            . '<span>' . htmlspecialchars(Text::_('COM_TICKETSTATION_TESTDATA_NOTICE_TEST'), ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }

    /**
     * Forgets the modes looked up so far, for after orders were changed or removed.
     */
    public static function reset(): void
    {
        self::$orderModes = [];
    }
}
