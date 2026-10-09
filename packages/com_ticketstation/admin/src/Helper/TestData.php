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
     * Forgets the modes looked up so far, for after orders were changed or removed.
     */
    public static function reset(): void
    {
        self::$orderModes = [];
    }
}
