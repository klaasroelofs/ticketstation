<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use stdClass;
use Ticketstation\Component\Ticketstation\Administrator\Model\Mixin\ListState;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CustomerNote;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Invoice;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Coupon;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SendTicketCopy;
use Ticketstation\Component\Ticketstation\Administrator\Helper\ticketcreator;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Tickets;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Transaction;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletUpdate;

/**
 * Ticketstation Box Office Model
 * @since 0.3.2
 */
class BoxofficeModel extends ListModel
{
    use ListState;

    /**
     * Prefix of the user state of the order list: its filters, search and page (see
     * ListState).
     */
    private const CONTEXT = 'com_ticketstation.boxoffice.';

    /**
     * Payment status filter value => the paid value of the orders it shows. "Refunded" and
     * "Refund needs attention" are handled on their own, see getKeyQuery().
     */
    private const PAID_FILTER = [1 => 1, 2 => 0, 4 => 3];

    /**
     * Payment status filter: orders with a refund, or marked refunded before 2.9.0 (paid = 2).
     */
    private const FILTER_REFUNDED = 3;

    /**
     * Payment status filter: orders with a refund that waits for a decision or has failed.
     */
    private const FILTER_REFUND_ATTENTION = 5;

    /**
     * @var int
     */
    protected $id;

    /**
     * Cache for getList().
     *
     * @var array|null
     */
    private $items;

    /**
     * Cache for getTotal().
     *
     * @var int|null
     */
    private $total;

    /**
     * Cache for getGhostRows().
     *
     * @var array|null
     */
    private $ghostRows;

    /**
     * Cache for getGhostSnapshot(): false once looked up and not a ghost, an array snapshot
     * otherwise, null before the first lookup.
     *
     * @var array|false|null
     */
    private $ghostSnapshot;

    /**
     * Ordercodes paymentResender() sent no payment link for, because they are paid or refunded.
     *
     * @var string[]
     */
    public $skippedPaymentRequests = [];

    /**
     * Ordercodes sendTicketsForOrders() sent nothing for, because they aren't paid or have no
     * tickets yet.
     *
     * @var string[]
     */
    public $skippedTicketMails = [];

    function __construct()
    {

        parent::__construct();

        $app = Factory::getApplication();

        $this->populateListState('boxoffice', [
            'search' => ['searchbox', '', 'string'],
            'paid'   => ['filter_ordering_paid', 0, 'int'],
            'event'  => ['filter_ordering_event', 0, 'int'],
            'sent'   => ['filter_ordering_sent', 0, 'int'],
        ]);

        $array    = $app->getInput()->get('cid', [0], 'array');
        $this->id = (int) $array[0];
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination($this->getTotal(), $this->getPageStart(), (int) $this->getState('limit'));
        }

        return $this->_pagination;
    }

    /**
     * The number of orders in the list: the live orders matching the filters and search, plus
     * the matching orders the ticketcleaner removed (see getGhostRows()).
     *
     * @return  int
     */
    function getTotal()
    {
        if ($this->total === null)
        {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->getKeyQuery()->alias('k'));

            $db->setQuery($query);

            $this->total = (int) $db->loadResult() + count($this->getGhostRows());
        }

        return $this->total;
    }

    /**
     * The orders on the current page, newest first. Each row describes a whole order; see
     * loadLiveRows() for its fields.
     *
     * The page is found in two steps. First the ordercodes and dates of the matching live
     * orders, up to and including this page, are merged with the removed ("ghost") orders and
     * the page is cut out of that. Then only the orders on the page are loaded in full.
     *
     * @return  array
     */
    function getList()
    {
        if ($this->items !== null)
        {
            return $this->items;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $start = $this->getPageStart();
        $limit = (int) $this->getState('limit');

        $query = $this->getKeyQuery()
            ->order('MIN(' . $db->quoteName('a.orderdate') . ') DESC')
            ->order($db->quoteName('a.ordercode') . ' DESC');

        $db->setQuery($query, 0, $limit > 0 ? $start + $limit : 0);

        $keys = [];

        foreach ($db->loadObjectList() as $key)
        {
            $keys[] = ['ordercode' => (string) $key->ordercode, 'orderdate' => (string) $key->orderdate, 'ghost' => null];
        }

        foreach ($this->getGhostRows() as $ghost)
        {
            $keys[] = ['ordercode' => (string) $ghost->ordercode, 'orderdate' => (string) $ghost->orderdate, 'ghost' => $ghost];
        }

        usort($keys, static function ($a, $b) {
            return [$b['orderdate'], $b['ordercode']] <=> [$a['orderdate'], $a['ordercode']];
        });

        $keys = array_slice($keys, $start, $limit > 0 ? $limit : null);

        $live = $this->loadLiveRows(array_column(array_filter($keys, static function ($key) {
            return $key['ghost'] === null;
        }), 'ordercode'));

        $this->items = [];

        foreach ($keys as $key)
        {
            $row = $key['ghost'] ?? ($live[$key['ordercode']] ?? null);

            if ($row)
            {
                $this->items[] = $row;
            }
        }

        return $this->items;
    }

    /**
     * Totals of the live orders matching the filters and search: orders, tickets, scanned
     * tickets and, with the event filter on, the tickets for that event. Orders removed by
     * the ticketcleaner don't count: their tickets were released.
     *
     * @return  object  orders, tickets, scanned, event_tickets
     */
    public function getSummary()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('orders'),
                'COALESCE(SUM(' . $db->quoteName('k.tickets') . '), 0) AS ' . $db->quoteName('tickets'),
                'COALESCE(SUM(' . $db->quoteName('k.scanned') . '), 0) AS ' . $db->quoteName('scanned'),
                'COALESCE(SUM(' . $db->quoteName('k.event_tickets') . '), 0) AS ' . $db->quoteName('event_tickets'),
            ])
            ->from($this->getKeyQuery(true)->alias('k'));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * The events for the event filter, the most recent first.
     *
     * @return  array  eventid, eventname, eventdate
     */
    public function getEventOptions()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('eventid'), $db->quoteName('eventname'), $db->quoteName('eventdate')])
            ->from($db->quoteName('#__ticketstation_events'))
            ->order($db->quoteName('eventdate') . ' DESC')
            ->order($db->quoteName('eventname') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * The order a search term points at exactly: an ordercode, or the code of a ticket's QR
     * code (as a scanner types it). Used to open that order straight away.
     *
     * @param   string  $search
     *
     * @return  string|null  The ordercode, or null when the term doesn't identify one order.
     */
    public function findExactOrder(string $search)
    {
        $search = trim($search);

        if ($search === '')
        {
            return null;
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('ordercode'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('userid') . ' != 0')
            ->where('(' . $db->quoteName('ordercode') . ' = ' . $db->quote($search)
                . ' OR ' . $db->quoteName('barcode') . ' = ' . $db->quote($search) . ')');

        $db->setQuery($query, 0, 2);
        $codes = $db->loadColumn();

        // The fixed test-mode code is on many tickets: that doesn't identify one order.
        if (count($codes) === 1)
        {
            return (string) $codes[0];
        }

        if (count($codes) === 0 && ctype_digit($search) && History::getAutoRemovedGhosts([$search]))
        {
            return $search;
        }

        return null;
    }

    /**
     * The tickets of the live orders matching the filters and search, one row per ticket,
     * for the CSV export. With the event filter on, only the tickets for that event.
     *
     * @return  array
     */
    public function getExportRows()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $event = (int) $this->getState('filter.event');

        $query = $db->getQuery(true)
            ->select([
                'a.ordercode', 'a.orderid', 'a.orderdate', 'a.paid', 'a.price', 'a.scanned', 'a.scandate', 'a.blacklisted',
                'c.firstname', 'c.name', 'c.emailaddress', 'c.phonenumber',
                'e.eventname', 't.ticketname', 'co.row_name', 'co.seatid', 'r.remarks',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('INNER', '(' . $this->getKeyQuery() . ') AS ' . $db->quoteName('k') . ' ON ' . $db->quoteName('k.ordercode') . ' = ' . $db->quoteName('a.ordercode'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('a.userid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 'co') . ' ON ' . $db->quoteName('co.orderid') . ' = ' . $db->quoteName('a.orderid'))
            ->join('LEFT', '(SELECT ' . $db->quoteName('ordercode') . ', MAX(' . $db->quoteName('remarks') . ') AS ' . $db->quoteName('remarks')
                . ' FROM ' . $db->quoteName('#__ticketstation_remarks') . ' GROUP BY ' . $db->quoteName('ordercode') . ') AS ' . $db->quoteName('r')
                . ' ON ' . $db->quoteName('r.ordercode') . ' = ' . $db->quoteName('a.ordercode'))
            ->order([
                $db->quoteName('e.eventdate') . ' DESC', $db->quoteName('e.eventname'), $db->quoteName('c.name'), $db->quoteName('c.firstname'),
                $db->quoteName('a.ordercode'), $db->quoteName('co.row_name'), 'CAST(' . $db->quoteName('co.seatid') . ' AS UNSIGNED)', $db->quoteName('co.seatid'), $db->quoteName('a.orderid'),
            ]);

        if ($event)
        {
            $query->where($db->quoteName('a.eventid') . ' = ' . $event);
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * The current page's first row, moved back to the last page when a smaller selection
     * leaves the stored one past the end.
     *
     * @return  int
     */
    private function getPageStart()
    {
        $start = (int) $this->getState('limitstart');
        $limit = (int) $this->getState('limit');
        $total = $this->getTotal();

        if ($start > 0 && $start >= $total)
        {
            $start = $limit > 0 ? (int) (max(0, floor(($total - 1) / $limit)) * $limit) : 0;

            $this->setState('limitstart', $start);
            Factory::getApplication()->setUserState(self::CONTEXT . 'limitstart', $start);
        }

        return $start;
    }

    /**
     * One row per live order matching the filters and search: its ordercode and date, and
     * with $counts its number of tickets, scanned tickets and tickets for the filtered event.
     *
     * Filters and search select whole orders (HAVING and EXISTS), never single order rows, so
     * the counts always cover the entire order.
     *
     * @param   bool  $counts
     *
     * @return  DatabaseQuery
     */
    private function getKeyQuery(bool $counts = false)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $paid   = (int) $this->getState('filter.paid');
        $event  = (int) $this->getState('filter.event');
        $sent   = (int) $this->getState('filter.sent');
        $search = (string) $this->getState('filter.search');

        $query = $db->getQuery(true)
            ->select([$db->quoteName('a.ordercode'), 'MIN(' . $db->quoteName('a.orderdate') . ') AS ' . $db->quoteName('orderdate')])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('a.userid'))
            // An unfinished cart (no customer yet) isn't an order.
            ->where($db->quoteName('a.userid') . ' != 0')
            ->group($db->quoteName('a.ordercode'));

        if ($counts)
        {
            $query->select([
                'COUNT(*) AS ' . $db->quoteName('tickets'),
                'SUM(' . $db->quoteName('a.scanned') . ') AS ' . $db->quoteName('scanned'),
                ($event ? 'SUM(' . $db->quoteName('a.eventid') . ' = ' . $event . ')' : '0') . ' AS ' . $db->quoteName('event_tickets'),
            ]);
        }

        if (isset(self::PAID_FILTER[$paid]))
        {
            $query->having('MAX(' . $db->quoteName('a.paid') . ') = ' . self::PAID_FILTER[$paid]);
        }
        elseif ($paid === self::FILTER_REFUNDED)
        {
            $query->having('(MAX(' . $db->quoteName('a.paid') . ') = 2 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_refunds', 'rf')
                . ' WHERE ' . $db->quoteName('rf.ordercode') . ' = ' . $db->quoteName('a.ordercode') . ' AND ' . Refund::countingSql('rf') . '))');
        }
        elseif ($paid === self::FILTER_REFUND_ATTENTION)
        {
            $query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_refunds', 'rf')
                . ' WHERE ' . $db->quoteName('rf.ordercode') . ' = ' . $db->quoteName('a.ordercode') . ' AND ' . $db->quoteName('rf.attention') . ' > 0)');
        }

        // An order that holds tickets for several events is shown for each of them.
        if ($event)
        {
            $query->having('SUM(' . $db->quoteName('a.eventid') . ' = ' . $event . ') > 0');
        }

        // Tickets sent: every ticket of the order was mailed. Not sent: at least one wasn't.
        if ($sent === 1)
        {
            $query->having('MIN(COALESCE(' . $db->quoteName('a.pdfsent') . ', 0)) = 1');
        }
        elseif ($sent === 2)
        {
            $query->having('MIN(COALESCE(' . $db->quoteName('a.pdfsent') . ', 0)) = 0');
        }

        if ($search !== '')
        {
            $query->where('(' . implode(' OR ', $this->getSearchConditions($search)) . ')');
        }

        return $query;
    }

    /**
     * What the search looks in: the start of the ordercode, the customer's name (first name
     * and last name together, so "Jan Jansen" is found), email address and phone number, the
     * Order Reference, the customer note, a seat number (e.g. A12) and the exact code of a
     * ticket's QR code. Words are found in the order they are typed, with anything in between.
     *
     * @param   string  $search
     *
     * @return  string[]  Conditions on the outer order row "a" and its customer "c".
     */
    private function getSearchConditions(string $search)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $words   = preg_split('/\s+/', $search);
        $pattern = $db->quote('%' . implode('%', array_map(static function ($word) use ($db) {
            return $db->escape($word, true);
        }, $words)) . '%', false);
        $like    = ' LIKE ' . $pattern;

        $conditions = [
            'CONCAT_WS(' . $db->quote(' ') . ', ' . $db->quoteName('c.firstname') . ', ' . $db->quoteName('c.name') . ')' . $like,
            $db->quoteName('c.emailaddress') . $like,
            $db->quoteName('c.phonenumber') . $like,
            'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_remarks', 'r')
                . ' WHERE ' . $db->quoteName('r.ordercode') . ' = ' . $db->quoteName('a.ordercode')
                . ' AND ' . $db->quoteName('r.remarks') . $like . ')',
            'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_customer_notes', 'n')
                . ' WHERE ' . $db->quoteName('n.ordercode') . ' = ' . $db->quoteName('a.ordercode')
                . ' AND ' . $db->quoteName('n.note') . $like . ')',
            // The seat number as printed on the ticket: row name and seat, e.g. A12.
            'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_orders', 's')
                . ' INNER JOIN ' . $db->quoteName('#__ticketstation_seatplancoords', 'co') . ' ON ' . $db->quoteName('co.orderid') . ' = ' . $db->quoteName('s.orderid')
                . ' WHERE ' . $db->quoteName('s.ordercode') . ' = ' . $db->quoteName('a.ordercode')
                . ' AND CONCAT(COALESCE(' . $db->quoteName('co.row_name') . ", ''), " . $db->quoteName('co.seatid') . ')' . $like . ')',
            // The content of a ticket's QR code, as a scanner at the desk types it.
            'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_orders', 'b')
                . ' WHERE ' . $db->quoteName('b.ordercode') . ' = ' . $db->quoteName('a.ordercode')
                . ' AND ' . $db->quoteName('b.barcode') . ' = ' . $db->quote($search) . ')',
        ];

        if (ctype_digit($search))
        {
            array_unshift($conditions, $db->quoteName('a.ordercode') . ' LIKE ' . $db->quote($db->escape($search, true) . '%', false));
        }

        return $conditions;
    }

    /**
     * Loads the given live orders for the list, each summed up over all its order rows.
     *
     * Every row has: ordercode, orderdate, orderid, paid, userid, firstname, name,
     * emailaddress, o_tickets, scanned_tickets, blocked_tickets, created_tickets,
     * sent_tickets, downloaded, published, coupon, orderprice (what was paid, or else what the
     * customer pays), transaction_pid, remarks, customer_note, events (per event: eventid,
     * eventname, tickets, ticketnames) and, with the event filter on, event_tickets.
     *
     * The order rows are summed up on their own, and everything that has one row per order
     * (customer, remark, note, the latest transaction, the service fee terms and coupon) is looked up
     * separately, so a second transaction can never count the tickets twice.
     *
     * @param   string[]  $codes
     *
     * @return  array  ordercode => row
     */
    private function loadLiveRows(array $codes)
    {
        if (!$codes)
        {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $in    = implode(',', array_map([$db, 'quote'], $codes));
        $event = (int) $this->getState('filter.event');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.ordercode'),
                'MIN(a.orderdate) AS orderdate',
                'MAX(a.orderid) AS orderid',
                'MAX(a.paid) AS paid',
                'MAX(a.userid) AS userid',
                'COUNT(*) AS o_tickets',
                'SUM(a.scanned) AS scanned_tickets',
                'SUM(COALESCE(a.blacklisted, 0) = 1) AS blocked_tickets',
                'SUM(COALESCE(a.pdfcreated, 0) = 1) AS created_tickets',
                'SUM(COALESCE(a.pdfsent, 0) = 1) AS sent_tickets',
                'MAX(COALESCE(a.downloaded, 0)) AS downloaded',
                'MAX(a.published) AS published',
                'SUM(a.price) AS tickets_amount',
                ($event ? 'SUM(a.eventid = ' . $event . ')' : 'NULL') . ' AS event_tickets',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->where($db->quoteName('a.ordercode') . ' IN (' . $in . ')')
            ->group($db->quoteName('a.ordercode'));

        $db->setQuery($query);
        $rows = $db->loadObjectList('ordercode');

        $clients      = $this->loadClients(array_column($rows, 'userid'));
        $events       = $this->loadEventsPerOrder($codes);
        $remarks      = $this->loadRemarks($codes);
        $notes        = $this->loadNotes($codes);
        $transactions = $this->loadTransactions($codes);
        $totals       = $this->loadOrderTotals($codes);
        $refunds      = Refund::summaries($codes);

        foreach ($rows as $code => $row)
        {
            $client      = $clients[$row->userid] ?? null;
            $transaction = $transactions[$code] ?? null;
            $kept        = $totals[$code] ?? null;
            $terms       = OrderTotals::keptTerms($kept) ?? OrderTotals::terms($code);

            // What the customer pays, worked out as OrderTotals::get() does it.
            $row->coupon = (string) ($kept->coupon ?? '');
            $tickets     = round((float) $row->tickets_amount, 2);

            // A coupon for certain tickets only: its discount over those tickets (rare, so one
            // extra look-up per such order).
            if ($row->coupon === '')
            {
                $discount = 0.0;
            }
            elseif (Coupon::ticketIds($kept->coupon_tickets ?? ''))
            {
                $discount = OrderTotals::get($code)->discount;
            }
            else
            {
                $discount = Coupon::discountFor($tickets, $kept->discount_type, $kept->discount_amount);
            }

            $subtotal    = round($tickets - $discount, 2);
            $total       = round($subtotal + OrderTotals::feesFor($subtotal, $terms), 2);

            $row->firstname       = $client->firstname ?? null;
            $row->name            = $client->name ?? null;
            $row->emailaddress    = $client->emailaddress ?? null;
            $row->orderprice      = $transaction && (float) $transaction->amount > 0 ? (float) $transaction->amount : $total;
            $row->transaction_pid = $transaction->pid ?? null;
            $row->remarks         = $remarks[$code] ?? '';
            $row->customer_note   = $notes[$code] ?? '';
            $row->events          = $events[$code] ?? [];
            $row->removed_auto    = false;
            $row->refunded        = $refunds[$code]->refunded ?? 0.0;
            $row->refund_attention = $refunds[$code]->attention ?? 0;
        }

        return $rows;
    }

    /**
     * The orders the ticketcleaner removed automatically (real DELETE, no row left) whose
     * removal was snapshotted to the History as event_type 'order_removed_auto', matching
     * the filters and search. This is what keeps them visible in the Box Office, while every
     * other part of the app (front-end availability, reports, ...) still just queries
     * #__ticketstation_orders and sees them as genuinely gone. Orders removed manually
     * (task=remove) are never snapshotted, so they never reappear here.
     *
     * The rows have the fields of loadLiveRows(), plus removed_auto, removed_reason and
     * removed_at. Client, event and ticket names are looked up live (those tables are never
     * touched by the cleaner), all at once for every ghost; only the order's own columns come
     * from the History snapshot.
     *
     * @return  array
     */
    private function getGhostRows()
    {
        if ($this->ghostRows !== null)
        {
            return $this->ghostRows;
        }

        $paid   = (int) $this->getState('filter.paid');
        $event  = (int) $this->getState('filter.event');
        $sent   = (int) $this->getState('filter.sent');
        $search = (string) $this->getState('filter.search');

        $candidates = [];

        foreach (History::getAutoRemovedGhosts() as $ordercode => $ghost)
        {
            $lines = array_map(static function ($line) {
                return (object) $line;
            }, $ghost['rows']);

            if (!$lines)
            {
                continue;
            }

            $first = $lines[0];

            // Mirrors the live list's "a.userid != 0" filter: an unfinished cart (userid 0)
            // never showed up here either, ghost or not.
            if (empty($first->userid))
            {
                continue;
            }

            $maxPaid = max(array_map(static function ($line) {
                return (int) $line->paid;
            }, $lines));

            if (isset(self::PAID_FILTER[$paid]) && $maxPaid !== self::PAID_FILTER[$paid])
            {
                continue;
            }

            // A removed order was never paid, so it has no refunds; only the old "refunded"
            // status can match.
            if (($paid === self::FILTER_REFUNDED && $maxPaid !== 2) || $paid === self::FILTER_REFUND_ATTENTION)
            {
                continue;
            }

            $eventIds = array_map(static function ($line) {
                return (int) $line->eventid;
            }, $lines);

            if ($event && !in_array($event, $eventIds, true))
            {
                continue;
            }

            $allSent = min(array_map(static function ($line) {
                return (int) ($line->pdfsent ?? 0);
            }, $lines)) === 1;

            if (($sent === 1 && !$allSent) || ($sent === 2 && $allSent))
            {
                continue;
            }

            $candidates[(string) $ordercode] = (object) [
                'ghost'   => $ghost,
                'lines'   => $lines,
                'first'   => $first,
                'paid'    => $maxPaid,
            ];
        }

        $this->ghostRows = [];

        if (!$candidates)
        {
            return $this->ghostRows;
        }

        $codes     = array_map('strval', array_keys($candidates));
        $allLines  = array_merge(...array_column($candidates, 'lines'));
        $clients   = $this->loadClients(array_column(array_column($candidates, 'first'), 'userid'));
        $eventInfo = $this->loadNames('#__ticketstation_events', 'eventid', ['eventname', 'eventdate'], array_column($allLines, 'eventid'));
        $ticketInfo = $this->loadNames('#__ticketstation_tickets', 'ticketid', ['ticketname'], array_column($allLines, 'ticketid'));
        $remarks   = $this->loadRemarks($codes);
        $notes     = $this->loadNotes($codes);
        $transactions = $this->loadTransactions($codes);

        foreach ($candidates as $ordercode => $candidate)
        {
            $ordercode = (string) $ordercode;
            $lines     = $candidate->lines;
            $client    = $clients[$candidate->first->userid] ?? null;

            if ($search !== '' && !$this->ghostMatchesSearch($search, $ordercode, $lines, $client, $remarks[$ordercode] ?? '', $notes[$ordercode] ?? ''))
            {
                continue;
            }

            $events = [];

            foreach ($lines as $line)
            {
                $eventid = (int) $line->eventid;

                if (!isset($events[$eventid]))
                {
                    $events[$eventid] = (object) [
                        'eventid'     => $eventid,
                        'eventname'   => $eventInfo[$eventid]->eventname ?? null,
                        'eventdate'   => $eventInfo[$eventid]->eventdate ?? null,
                        'tickets'     => 0,
                        'ticketnames' => [],
                    ];
                }

                $events[$eventid]->tickets++;
                $events[$eventid]->ticketnames[] = $ticketInfo[(int) $line->ticketid]->ticketname ?? '';
            }

            foreach ($events as $item)
            {
                $item->ticketnames = implode(', ', array_unique(array_filter($item->ticketnames)));
            }

            usort($events, static function ($a, $b) {
                return strcmp((string) $a->eventdate, (string) $b->eventdate);
            });

            $transaction = $transactions[$ordercode] ?? null;
            $tickets     = array_sum(array_map(static function ($line) {
                return (float) $line->price - (float) ($line->discount ?? 0);
            }, $lines));

            $count = static function ($field) use ($lines) {
                return count(array_filter($lines, static function ($line) use ($field) {
                    return (int) ($line->$field ?? 0) === 1;
                }));
            };

            $this->ghostRows[] = (object) [
                'ordercode'       => $ordercode,
                'orderdate'       => min(array_map(static function ($line) {
                    return (string) $line->orderdate;
                }, $lines)),
                'orderid'         => $candidate->first->orderid,
                'paid'            => $candidate->paid,
                'userid'          => $candidate->first->userid,
                'firstname'       => $client->firstname ?? null,
                'name'            => $client->name ?? null,
                'emailaddress'    => $client->emailaddress ?? null,
                'o_tickets'       => count($lines),
                'scanned_tickets' => $count('scanned'),
                'blocked_tickets' => $count('blacklisted'),
                'created_tickets' => $count('pdfcreated'),
                'sent_tickets'    => $count('pdfsent'),
                'downloaded'      => $count('downloaded') > 0 ? 1 : 0,
                'published'       => $candidate->first->published,
                'coupon'          => $candidate->first->coupon ?? null,
                'orderprice'      => $transaction && (float) $transaction->amount > 0 ? (float) $transaction->amount : round($tickets, 2),
                'transaction_pid' => $transaction->pid ?? null,
                'remarks'         => $remarks[$ordercode] ?? '',
                'customer_note'   => $notes[$ordercode] ?? '',
                'events'          => $events,
                'event_tickets'   => $event ? count(array_filter($lines, static function ($line) use ($event) {
                    return (int) $line->eventid === $event;
                })) : null,
                'removed_auto'    => true,
                'removed_reason'  => $candidate->ghost['reason'],
                'removed_at'      => $candidate->ghost['created'],
            ];
        }

        return $this->ghostRows;
    }

    /**
     * The search of getSearchConditions(), applied to a removed order in PHP.
     */
    private function ghostMatchesSearch(string $search, string $ordercode, array $lines, $client, string $remark, string $note)
    {
        if (ctype_digit($search) && strpos($ordercode, $search) === 0)
        {
            return true;
        }

        $texts = [
            trim(($client->firstname ?? '') . ' ' . ($client->name ?? '')),
            $client->emailaddress ?? '',
            $client->phonenumber ?? '',
            $remark,
            $note,
        ];

        foreach ($lines as $line)
        {
            if ((string) ($line->barcode ?? '') === $search)
            {
                return true;
            }

            if (!empty($line->seatid))
            {
                $texts[] = ($line->row_name ?? '') . $line->seatid;
            }
        }

        $words = preg_split('/\s+/', mb_strtolower($search));

        foreach ($texts as $text)
        {
            $text     = mb_strtolower((string) $text);
            $position = 0;

            foreach ($words as $word)
            {
                $found = mb_strpos($text, $word, $position);

                if ($found === false)
                {
                    continue 2;
                }

                $position = $found + mb_strlen($word);
            }

            return true;
        }

        return false;
    }

    /**
     * @param   array  $ids  clientids
     *
     * @return  array  clientid => client
     */
    private function loadClients(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids)
        {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['clientid', 'firstname', 'name', 'emailaddress', 'phonenumber'])
            ->from($db->quoteName('#__ticketstation_clients'))
            ->whereIn($db->quoteName('clientid'), $ids);

        $db->setQuery($query);

        return $db->loadObjectList('clientid');
    }

    /**
     * Looks up a few columns of the rows with the given ids in a table.
     *
     * @return  array  id => row
     */
    private function loadNames(string $table, string $key, array $columns, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids)
        {
            return [];
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array_merge([$key], $columns)))
            ->from($db->quoteName($table))
            ->whereIn($db->quoteName($key), $ids);

        $db->setQuery($query);

        return $db->loadObjectList($key);
    }

    /**
     * The events of each order, with the number and the names of its tickets per event, in
     * the order the events take place.
     *
     * @return  array  ordercode => list of (object) eventid, eventname, eventdate, tickets, ticketnames
     */
    private function loadEventsPerOrder(array $codes)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.ordercode'),
                $db->quoteName('a.eventid'),
                'MAX(e.eventname) AS eventname',
                'MAX(e.eventdate) AS eventdate',
                'COUNT(*) AS tickets',
                "GROUP_CONCAT(DISTINCT t.ticketname ORDER BY t.ticketname SEPARATOR ', ') AS ticketnames",
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('a.ticketid'))
            ->whereIn($db->quoteName('a.ordercode'), $codes, ParameterType::STRING)
            ->group([$db->quoteName('a.ordercode'), $db->quoteName('a.eventid')])
            ->order(['MAX(e.eventdate)', $db->quoteName('a.eventid')]);

        $db->setQuery($query);

        $events = [];

        foreach ($db->loadObjectList() as $row)
        {
            $events[(string) $row->ordercode][] = $row;
        }

        return $events;
    }

    /**
     * @return  array  ordercode => Order Reference
     */
    private function loadRemarks(array $codes)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('ordercode'), 'MAX(' . $db->quoteName('remarks') . ') AS ' . $db->quoteName('remarks')])
            ->from($db->quoteName('#__ticketstation_remarks'))
            ->whereIn($db->quoteName('ordercode'), $codes, ParameterType::STRING)
            ->group($db->quoteName('ordercode'));

        $db->setQuery($query);

        return $db->loadAssocList('ordercode', 'remarks');
    }

    /**
     * @return  array  ordercode => customer note
     */
    private function loadNotes(array $codes)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('ordercode'), $db->quoteName('note')])
            ->from($db->quoteName('#__ticketstation_customer_notes'))
            ->whereIn($db->quoteName('ordercode'), array_map('intval', $codes));

        $db->setQuery($query);

        return $db->loadAssocList('ordercode', 'note');
    }

    /**
     * The latest transaction of each order. An order can have more than one, for instance
     * after a duplicate payment; the latest is the one the order is shown with.
     *
     * @return  array  ordercode => (object) pid, amount, type
     */
    private function loadTransactions(array $codes)
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $latest = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('pid') . ')')
            ->from($db->quoteName('#__ticketstation_transactions'))
            ->where($db->quoteName('orderid') . ' IN (' . implode(',', array_map('intval', $codes)) . ')')
            ->group($db->quoteName('orderid'));

        $query = $db->getQuery(true)
            ->select([$db->quoteName('orderid'), $db->quoteName('pid'), $db->quoteName('amount'), $db->quoteName('type')])
            ->from($db->quoteName('#__ticketstation_transactions'))
            ->where($db->quoteName('pid') . ' IN (' . $latest . ')');

        $db->setQuery($query);

        return $db->loadObjectList('orderid');
    }

    /**
     * The rows of #__ticketstation_ordertotals: the service fee terms (fee_type NULL when none
     * are kept) and the coupon of each order.
     *
     * @return  array  ordercode => (object) fee_type, fee_rate, fee_fixed, coupon, discount_type, discount_amount
     */
    private function loadOrderTotals(array $codes)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['ordercode', 'fee_type', 'fee_rate', 'fee_fixed', 'coupon', 'discount_type', 'discount_amount', 'coupon_tickets']))
            ->from($db->quoteName('#__ticketstation_ordertotals'))
            ->whereIn($db->quoteName('ordercode'), $codes, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList('ordercode');
    }

    /**
     * The tickets of the order, grouped per event (in the order the events take place), then
     * per ticket and seat.
     *
     * @return  array
     */
    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['a.*', 't.ticketname', 't.ticketprice', 'e.eventname', 'e.eventdate', 'ot.coupon', 'ot.discount_type', 'ot.discount_amount',
            'ext.row_name', 'ext.seatid', 'u.name AS scanner_name']);
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('a.scanner') . ' = ' . $db->quoteName('u.id') . ')');
        $query->join('LEFT OUTER',
            $db->quoteName('#__ticketstation_ordertotals', 'ot') . ' ON (' . $db->quoteName('ot.ordercode') . ' = ' . $db->quoteName('a.ordercode') . ')');
        $query->join('LEFT OUTER',
            $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('a.orderid') . ' = ' . $db->quoteName('ext.orderid') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = ' . $db->quote((int) $this->id));
        $query->order([
            $db->quoteName('e.eventdate') . ' ASC', $db->quoteName('a.eventid') . ' ASC', $db->quoteName('a.ticketid') . ' ASC',
            $db->quoteName('ext.row_name') . ' ASC', 'CAST(' . $db->quoteName('ext.seatid') . ' AS UNSIGNED) ASC', $db->quoteName('ext.seatid') . ' ASC', $db->quoteName('a.orderid') . ' ASC',
        ]);

        $db->setQuery($query);
        $data = $db->loadObjectList();

        return $data;
    }

    /**
     * The latest transaction of the order (see loadTransactions()).
     *
     * @return  object|null  pid, amount, type
     */
    function getTransaction()
    {
        return $this->loadTransactions([(string) (int) $this->id])[(int) $this->id] ?? null;
    }

    /**
     * The invoice of the order, if it has one.
     *
     * @return  object|null
     */
    function getInvoice()
    {
        return (new Invoice)->getInvoiceByOrderode((int) $this->id) ?: null;
    }

    function getClient()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select(['c.*', 'a.*']);
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid') . ')');
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->id));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
    }

    function getHistory()
    {
        return (new History)->getForOrder($this->id);
    }

    /**
     * Loads (and caches) the History snapshot for the current order, if the ticketcleaner
     * auto-removed it and no live row exists for it any more.
     *
     * @return  array|false
     */
    private function getGhostSnapshot()
    {
        if ($this->ghostSnapshot !== null)
        {
            return $this->ghostSnapshot;
        }

        $ordercode = (string) (int) $this->id;
        $ghosts    = History::getAutoRemovedGhosts([$ordercode]);

        $this->ghostSnapshot = $ghosts[$ordercode] ?? false;

        return $this->ghostSnapshot;
    }

    /**
     * Whether the order being viewed only still exists as an auto-removal snapshot in the
     * History (real row deleted by the ticketcleaner). The Box Office detail view uses this
     * to show a read-only summary instead of the normal, interactive order form.
     *
     * @return  bool
     */
    public function isGhostOrder()
    {
        return (bool) $this->getGhostSnapshot();
    }

    /**
     * Builds a read-only overview of a ghost order from its History snapshot, enriched with
     * live client/event/ticket names (those tables are never touched by the cleaner).
     *
     * @return  object|null
     */
    public function getGhostOverview()
    {
        $ghost = $this->getGhostSnapshot();

        if ( ! $ghost)
        {
            return null;
        }

        $lines = array_map(static function ($line) {
            return (object) $line;
        }, $ghost['rows']);
        $first = $lines[0];

        $client  = $this->loadClients([$first->userid ?? 0])[(int) ($first->userid ?? 0)] ?? null;
        $events  = $this->loadNames('#__ticketstation_events', 'eventid', ['eventname', 'eventdate'], array_column($lines, 'eventid'));
        $tickets = $this->loadNames('#__ticketstation_tickets', 'ticketid', ['ticketname', 'ticketprice'], array_column($lines, 'ticketid'));

        $orderLines = [];
        $total      = 0;

        foreach ($lines as $line)
        {
            $orderLines[] = (object) [
                'orderid'     => $line->orderid,
                'eventid'     => (int) $line->eventid,
                'eventname'   => $events[(int) $line->eventid]->eventname ?? null,
                'eventdate'   => $events[(int) $line->eventid]->eventdate ?? null,
                'ticketname'  => $tickets[(int) $line->ticketid]->ticketname ?? null,
                'price'       => $line->price,
                'paid'        => $line->paid,
                'barcode'     => $line->barcode,
                'scanned'     => $line->scanned,
                'blacklisted' => $line->blacklisted,
                'seatid'      => $line->seatid ?? null,
                'row_name'    => $line->row_name ?? null,
            ];

            $total += (float) $line->price;
        }

        usort($orderLines, static function ($a, $b) {
            return [(string) $a->eventdate, $a->eventid, $a->orderid] <=> [(string) $b->eventdate, $b->eventid, $b->orderid];
        });

        return (object) [
            'ordercode'  => $this->id,
            'client'     => $client,
            'eventnames' => array_values(array_unique(array_filter(array_column($orderLines, 'eventname')))),
            'orderdate'  => min(array_map(static function ($line) {
                return (string) $line->orderdate;
            }, $lines)),
            'paid'       => $first->paid,
            'reason'     => $ghost['reason'],
            'removed_at' => $ghost['created'],
            'note'       => (new CustomerNote)->get($this->id),
            'lines'      => $orderLines,
            'total'      => $total,
        ];
    }

    function getRemark()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_remarks'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $this->id));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function removeOrderID($cid = [])
    {
        if ( ! count($cid))
        {
            return false;
        }

        ## Implode cids for more actions
        $cids = implode(',', $cid);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['o.*', 't.parent AS parentticket']);
        $query->from($db->quoteName('#__ticketstation_orders', 'o'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
        $query->where($db->quoteName('o.orderid') . ' IN (' . $cids . ')');

        $db->setQuery($query);
        $orderdata = $db->loadObjectList();

        $query = $db->getQuery(true);

        $conditions = [
            $db->quoteName('orderid') . ' IN (' . $cids . ')',
        ];

        $query->delete($db->quoteName('#__ticketstation_orders'));
        $query->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        foreach (array_unique(array_column($orderdata, 'ordercode')) as $affected_ordercode) {
            $count = count(array_filter($orderdata, function ($row) use ($affected_ordercode) {
                return $row->ordercode == $affected_ordercode;
            }));
            History::log($affected_ordercode, 'ticket_removed', $count . ' ticket(s) removed from order');

            // If that was the last remaining ticket line for this ordercode, the
            // order itself no longer exists - clean up anything still tied to it,
            // same as a full order delete via Box Office > Delete.
            $query = $db->getQuery(true);
            $query->select('COUNT(*)')
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote($affected_ordercode));

            $db->setQuery($query);
            $remaining = (int) $db->loadResult();

            if ($remaining === 0) {

                $query = $db->getQuery(true);
                $query->delete($db->quoteName('#__ticketstation_remarks'))
                    ->where($db->quoteName('ordercode') . ' = ' . $db->quote($affected_ordercode));

                $db->setQuery($query);
                $db->execute();

                (new Invoice)->remove($affected_ordercode);
                (new Transaction)->remove($affected_ordercode);
                Refund::remove($affected_ordercode);
                (new CustomerNote)->remove($affected_ordercode);
                OrderTotals::remove($affected_ordercode);
                History::remove($affected_ordercode);
                Wallet::forget($affected_ordercode);
            }
            else
            {
                // The discount of the order is spread over the tickets that are left.
                Coupon::refresh((int) $affected_ordercode);
            }
        }

        ## Set FTP credentials, if given
        ClientHelper::setCredentialsFromRequest('ftp');

        ## Tickets have been removed successfull
        ## Now we need to update the totals from the Object earlier this script.
        for ($i = 0, $n = count($orderdata); $i < $n; $i++)
        {

            $row = $orderdata[$i];

            ## Path to a single ticket is as below:
            $path_single = Tickets::singlePath($row->ordercode);

            ## Remove single ticket
            if (file_exists($path_single))
            {
                File::delete($path_single);
            }

            ## Bijbehorende QR-code uit de folder cache verwijderen:
            $file_qr = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/qrcodes/' . $row->barcode . '.png';

            if (file_exists( $file_qr ))
            {
                File::delete($file_qr);
            }

            ## Path to a multi ticket is as below:
            $path_multi = Tickets::combinedPath($row->ordercode);

            ## Remove single ticket
            if (file_exists($path_multi))
            {
                File::delete($path_multi);
            }

            if ($row->seat_sector != 0)
            {
                $query = $db->getQuery(true);

                $fields = [
                    // A blocked seat goes back to blocked, any other seat to free.
                    $db->quoteName('booked') . ' = ' . $db->quoteName('blocked'),
                    $db->quoteName('orderid') . ' = 0',
                ];

                $conditions = [
                    $db->quoteName('orderid') . ' = ' . $row->orderid,
                ];

                $query->update($db->quoteName('#__ticketstation_seatplancoords'))->set($fields)->where($conditions);

                $db->setQuery($query);

                $result = $db->execute();

                if ( ! $result)
                {
                    return false;
                }
            }
        }

        // The released tickets go to the waiting list first, as with a deleted order.
        (new WaitingList)->promote(array_column($orderdata, 'ticketid'));

        return true;
    }

    function resetScanstate($cid = [])
    {

        if (count($cid))
        {
            $cids = implode(',', $cid);

            $db = Factory::getContainer()->get('DatabaseDriver');

            $affected = $this->getOrdercodesForOrderIds($cids);

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('scanned') . ' = 0',
                $db->quoteName('scandate') . ' = 0',
                $db->quoteName('scanner') . ' = 0',

            ];

            $conditions = [
                $db->quoteName('orderid') . ' IN (' . $cids . ')',
            ];

            $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);

            if( ! $result = $db->execute()) {
                return false;
            } else {
                foreach ($affected as $row) {
                    History::log($row->ordercode, 'ticket_scan_reset', 'Scan status reset for ' . $row->total . ' ticket(s)');
                }
                return true;
            }

        }
    }

    function markasScanned($cid = [])
    {

        if (count($cid))
        {
            $cids = implode(',', $cid);
            $user 	= Factory::getApplication()->getIdentity();

            $tz       = new \DateTimeZone($user->getParam('timezone', Factory::getApplication()->get('offset', 'UTC')));
            $scandate = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');

            $db = Factory::getContainer()->get('DatabaseDriver');

            $affected = $this->getOrdercodesForOrderIds($cids);

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('scanner') . ' = ' . $db->quote($user->id),
                $db->quoteName('scanned') . ' = 1',
                $db->quoteName('scandate') . ' = ' . $db->quote($scandate),
            ];

            $conditions = [
                $db->quoteName('orderid') . ' IN (' . $cids . ')',
            ];

            $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);

            if( ! $result = $db->execute()) {
                return false;
            } else {
                foreach ($affected as $row) {
                    History::log($row->ordercode, 'ticket_scanned', $row->total . ' ticket(s) marked as scanned');
                }
                return true;
            }

        }
    }

    /**
     * Resolves the distinct ordercodes (with ticket counts) behind a list of orderid's,
     * so actions keyed by orderid (e.g. scan/blacklist) can still be logged per order.
     */
    private function getOrdercodesForOrderIds($cids)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['ordercode', 'COUNT(*) AS total'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('orderid') . ' IN (' . $cids . ')')
            ->group('ordercode');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function publish($cid = [], $publish = 1)
    {
        ## Count the cids
        if (count($cid))
        {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode(',', $cid);

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('published') . ' = ' . (int) $publish,
            ];

            $conditions = [
                $db->quoteName('ordercode') . ' IN (' . $cids . ')',
            ];

            $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if ( ! $result)
            {
                return false;
            }

            foreach ($cid as $ordercode) {
                History::log($ordercode, $publish ? 'order_published' : 'order_unpublished', $publish ? 'Order published' : 'Order unpublished');
            }
        }

        return true;
    }

    function updateOrderDetails($additionals, $post)
    {

        if (empty($additionals) || empty($post))
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('required_information') . ' = ' . $db->quote($additionals),
        ];

        $conditions = [
            $db->quoteName('orderid') . ' = ' . $post['orderid'],
        ];

        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        return true;
    }

    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = ' . $db->quote('1'));

        $db->setQuery($query);
        $config = $db->loadObject();

        return $config;
    }

    function fullprocessorder($cid = [])
    {

        ## Count the cids
        if (count($cid))
        {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            for ($i = 0, $n = count($cid); $i < $n; $i++)
            {
                $ordercode = $cid[$i];

                ## Start the API to process everything.
                require_once JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helper/PaymentAPI.php';
                $newProcess = new PaymentAPI((int) $ordercode);

                ## Set order to paid:
                $payment_state = $newProcess->updateOrder();

                ## Create th tickets:
                $ticket_creator = $newProcess->createTickets();

                ## if tickets has been created:
                if ($ticket_creator == true)
                {
                    $newProcess->sendTickets();
                }
            }

            return true;
        }

        return false;
    }

    function blacklistTicket($cid = [], $block = 1)
    {
        if ( ! count($cid))
        {
            return false;
        }

        $cids = implode(',', $cid);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $affected = $this->getOrdercodesForOrderIds($cids);

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('blacklisted') . ' = ' . (int) $block,
        ];

        $conditions = [
            $db->quoteName('orderid') . ' IN (' . $cids . ')',
        ];

        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        foreach ($affected as $row) {
            $event_type = $block ? 'ticket_blacklisted' : 'ticket_unblocked';
            $message    = $block ? 'ticket(s) blacklisted' : 'ticket(s) unblocked';
            History::log($row->ordercode, $event_type, $row->total . ' ' . $message);
            WalletUpdate::orderChanged($row->ordercode);
        }

        return true;
    }

    function paymentprocessor($ordercode, $paid = 1)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('paid') . ' = ' . (int) $paid,
        ];

        $conditions = [
            $db->quoteName('ordercode') . ' = '. $db->quote($ordercode),
        ];

        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        if ( ! $result)
        {
            return false;
        }

        // Same entries as the Actions menu writes (changePaymentState()).
        if ($paid == 1)
        {
            History::log($ordercode, 'order_paid', 'New order status: Paid');
        }
        else
        {
            History::log($ordercode, 'order_status_unpaid', 'New order status: Unpaid');
        }

        return true;
    }

    function changePaymentState($cid = [], $paid = 1)
    {

        if ( ! count($cid))
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $cids = implode(',', $cid);

        if ($paid == 3 || $paid == 2 || $paid == 1)
        {
            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('paid') . ' = ' . (int) $paid,
                $db->quoteName('published') . ' = 1',
            ];

            $conditions = [
                $db->quoteName('ordercode') . ' IN (' . $cids . ')',
            ];

            $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if ( ! $result)
            {
                return false;
            }

            $status_label = ($paid == 3) ? 'Pending' : (($paid == 2) ? 'Refunded' : 'Paid');
            $status_event = ($paid == 3) ? 'order_status_pending' : (($paid == 2) ? 'order_status_refunded' : 'order_paid');

            foreach ($cid as $ordercode) {
                History::log($ordercode, $status_event, 'New order status: ' . $status_label);
            }

            if ($paid == 3 || $paid == 2)
            {
                return true;
            }
        }

        return true;
    }

    /**
     * The refunds and chargebacks of the order being viewed.
     *
     * @return  array
     */
    public function getRefunds()
    {
        return Refund::forOrder((int) $this->id);
    }

    function paymentResender($cid = [])
    {

        $cids = implode(',', $cid);

        $config = $this->getConfig();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('ordercode');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' IN (' . $cids . ')');
        $query->group('ordercode');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++)
        {

            $row = $data[$i];

            ## Only an order that is still to be paid (not paid, or pending) gets a payment
            ## link: a paid or refunded order is left alone.
            $query = $db->getQuery(true);

            $query->select(['COUNT(orderid) AS total', 'MAX(userid) AS userid']);
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->whereIn($db->quoteName('paid'), [0, 3]);
            $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $row->ordercode));

            $db->setQuery($query);
            $item = $db->loadObject();

            if ($item->total == 0)
            {
                $this->skippedPaymentRequests[] = $row->ordercode;
            }
            else
            {
				## Update the paymentstate to Pending (3) and note when the payment was requested.
				## The ticketcleaner counts its removal period from that moment instead of from the
				## order date, so the customer has the full period to pay, while the order keeps its
				## real order date (in the list, on the tickets and in the mails).
				$query = $db->getQuery(true);

				$fields = [
					$db->quoteName('payment_requested') . ' = ' . $db->quote(date('Y-m-d H:i:s')),
                    $db->quoteName('paid') . ' = 3',
                    $db->quoteName('published') . ' = 1',
				];

				$conditions = [
					$db->quoteName('ordercode') . ' = ' . $db->quote((int) $row->ordercode),
				];

				$query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

				$db->setQuery($query);

				$db->execute();

                require_once JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helper/PaymentAPI.php';
                $processor = new PaymentAPI((int) $row->ordercode);
                $message   = new eTicketsMessage;

                $variables = eTicketsMessage::orderVariables((int) $row->ordercode);
                $variables['paymentlink'] = $processor->generatePaymentLink($row->ordercode);

                $message->id(3)
                    ->user($item->userid)
                    ->variables($variables)
                    ->send();

                History::log($row->ordercode, 'payment_reminder_sent', 'Payment request sent');
            }
        }

        return true;
    }

    function removeTickets($cid)
    {

        ## Count the cids
        if (count($cid))
        {

            $cids = implode(',', $cid);

            $db = Factory::getContainer()->get('DatabaseDriver');
            $config = $this->getConfig();

            $query = $db->getQuery(true);

            $query->select(['o.*', 't.parent AS parentticket']);
            $query->from($db->quoteName('#__ticketstation_orders', 'o'));
            $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('o.ticketid') . ' = ' .
                $db->quoteName('t.ticketid') . ')');
            $query->where($db->quoteName('o.ordercode') . ' IN (' . $cids . ')');

            $db->setQuery($query);
            $data = $db->loadObjectList();

            $query = $db->getQuery(true);

            $conditions = [
                $db->quoteName('ordercode') . ' IN (' . $cids . ')',
            ];

            $query->delete($db->quoteName('#__ticketstation_orders'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if ( ! $result)
            {
                return false;
            }

            // A ghost (an order the ticketcleaner removed) has no order rows left, only its
            // History snapshot. Deleting that history takes the ghost out of the list too.
            $removed = array_unique(array_merge(
                array_column($data, 'ordercode'),
                array_keys(History::getAutoRemovedGhosts($cid))
            ));

            foreach ($removed as $removed_ordercode) {
                // The history holds email addresses and, for a ghost, the IP address.
                History::remove($removed_ordercode);

                // Nor the wallet passes handed out for its tickets.
                Wallet::forget($removed_ordercode);

                // An invoice for a deleted order shouldn't survive it.
                (new Invoice)->remove($removed_ordercode);

                // Nor should its transaction, if one was ever recorded.
                (new Transaction)->remove($removed_ordercode);

                // And its refunds.
                Refund::remove($removed_ordercode);

                // And the note the customer added in the cart.
                (new CustomerNote)->remove($removed_ordercode);

                // And the terms of its service fee.
                OrderTotals::remove($removed_ordercode);
            }

            $ticket_helper = new Tickets;

            for ($i = 0, $n = count($data); $i < $n; $i++)
            {

                $row = $data[$i];

                // Removing the remark from the database if present:
                $query = $db->getQuery(true);

                $conditions = [
                    $db->quoteName('ordercode') . ' = (' . $row->ordercode . ')',
                ];

                $query->delete($db->quoteName('#__ticketstation_remarks'));
                $query->where($conditions);

                $db->setQuery($query);

                $db->execute();

                // Removing the ticket pysical:
                $ticket_helper->removeCombinedTicketFromServer($row->ordercode);
                $ticket_helper->removeTicketFromServer($row->ordercode, $row->barcode);
                $ticket_helper->removeMultiTicketFromServer($row->ordercode);

                // Check if there was a seat booked:
                if ($row->seat_sector != 0)
                {
                    $ticket_helper->resetSeatSate($row->orderid);
                }
            }

            // The released tickets go to the waiting list first.
            (new WaitingList)->promote(array_column($data, 'ticketid'));

            return true;
        }

        return false;
    }

    /**
     * Gives the ticked tickets of an order a new QR code, so the copies the customer has no
     * longer scan (a lost or stolen ticket), and makes the order's ticket files again. The
     * other tickets keep their codes.
     *
     * @param   int    $ordercode
     * @param   int[]  $cid        orderids of the tickets
     *
     * @return  bool
     */
    function renewTicketCodes($ordercode, $cid = [])
    {
        ArrayHelper::toInteger($cid);

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('orderid'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $ordercode))
            ->whereIn($db->quoteName('orderid'), $cid ?: [0]);

        $db->setQuery($query);
        $orderids = $db->loadColumn();

        if (!$orderids)
        {
            return false;
        }

        // The combined file of the order, with the new codes in it.
        $result = $this->ticketprocessor($ordercode, $orderids);

        History::log($ordercode, 'tickets_generated', 'New QR code for ' . count($orderids) . ' ticket(s)');

        // Passes already in a wallet get the new QR code (Google Wallet live updates)
        WalletUpdate::orderChanged($ordercode);

        return $result;
    }

    /**
     * Makes the ticket file of an order again: the ticket itself, or all tickets in one PDF.
     *
     * @param   int    $ordercode
     * @param   int[]  $newCodeIds  orderids of tickets that get a new QR code; the others keep theirs
     */
    function ticketprocessor($ordercode, array $newCodeIds = [])
    {
        $orderids = ticketcreator::validOrderIds((int) $ordercode);

        ticketcreator::createOrderFile((int) $ordercode, $orderids, array_map('intval', $newCodeIds));

        ## Renewing codes logs for itself (renewTicketCodes())
        if ($orderids && !$newCodeIds) {
            History::log($ordercode, 'tickets_generated', 'Tickets generated (QR codes unchanged)');
        }

        return true;
    }

    /**
     * Emails the tickets of the given orders to their customers, the way a completed payment
     * does (PaymentAPI::sendTickets(): with the invoice when that is sent automatically). Only
     * paid orders whose tickets have all been created get a mail; the others are listed in
     * $skippedTicketMails.
     *
     * @param   int[]  $cid  ordercodes
     *
     * @return  bool
     */
    function sendTicketsForOrders($cid = [])
    {
        if ( ! count($cid))
        {
            return false;
        }

        ArrayHelper::toInteger($cid);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('ordercode'),
                'MIN(' . $db->quoteName('paid') . ' = 1) AS ' . $db->quoteName('paid'),
                'MIN(COALESCE(' . $db->quoteName('pdfcreated') . ', 0)) AS ' . $db->quoteName('created'),
                'SUM(' . Refund::validSql() . ') AS ' . $db->quoteName('valid'),
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->whereIn($db->quoteName('ordercode'), $cid)
            ->group($db->quoteName('ordercode'));

        $db->setQuery($query);

        foreach ($db->loadObjectList() as $order)
        {
            if ((int) $order->paid !== 1 || (int) $order->created !== 1 || (int) $order->valid === 0)
            {
                $this->skippedTicketMails[] = $order->ordercode;
                continue;
            }

            (new PaymentAPI((int) $order->ordercode))->sendTickets();
        }

        return true;
    }

    function reSendTickets($eid)
    {

        $order = new SendTicketCopy((int) $eid);

        if ($order->send() === true)
        {
            History::log($eid, 'ticket_copy_sent', 'Ticket copy resent');
            return true;
        }
        else
        {
            return false;
        }
    }

    function sendInvoiceForOrder($eid)
    {
        return (new Invoice)->create((int) $eid);
    }

    function updateinsertRemark($eid, $remark)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        //Check if there is a remark for this ordercode already
        $remarkid = '';

        $query = $db->getQuery(true);

        $query->select('id', 'remarks');
        $query->from($db->quoteName('#__ticketstation_remarks'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid));

        $db->setQuery($query);
        $remark_id = $db->loadResult();

        if ($remark_id == '')
        {
            //Remark doesn't exist, insert
            $remarkinsert = new stdClass();
            $remarkinsert->ordercode = $eid;
            $remarkinsert->remarks = $remark;

            $result = $db
                ->insertObject('#__ticketstation_remarks', $remarkinsert);

            if( ! $result) {
                return 'FAIL';
            } else {
                History::log($eid, 'remark_updated', 'Order reference set to "' . $remark . '"', ['remark' => $remark]);
                return 'DONE_INSERT';
            }

        } else {

            //Remark exists, update if different
            $query = $db->getQuery(true);

            $query->select('remarks');
            $query->from($db->quoteName('#__ticketstation_remarks'));
            $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid));

            $db->setQuery($query);
            $existing_remark = $db->loadResult();

            if ($existing_remark == $remark) {

                return 'FAIL_NO_DIFF';

            } else {

                $query = $db->getQuery(true);

                $fields = [
                    $db->quoteName('remarks') . ' = ' . $db->quote($remark),
                ];

                $conditions = [
                    $db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid),
                ];

                $query->update($db->quoteName('#__ticketstation_remarks'))->set($fields)->where($conditions);

                $db->setQuery($query);

                if( ! $result = $db->execute()) {
                    return 'FAIL';
                } else {
                    History::log($eid, 'remark_updated', 'Order reference updated to "' . $remark . '"', ['remark' => $remark]);
                    return 'DONE_UPDATE';
                }
            }
        }
    }

    function deleteRemark($eid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        //Check if there is a remark for this ordercode already
        $remarkid = '';

        $query = $db->getQuery(true);

        $query->select(['id']);
        $query->from($db->quoteName('#__ticketstation_remarks'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid));

        $db->setQuery($query);
        $remarkid = $db->loadResult();

        if ($remarkid == '')
        {
            //Remark doesn't exist, return
            return 'NA';

        } else {

            //Remark exists, delete
            $query = $db->getQuery(true);

            $conditions = [
                $db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid),
            ];

            $query->delete($db->quoteName('#__ticketstation_remarks'))->where($conditions);

            $db->setQuery($query);

            if( $result = $db->execute()) {
                History::log($eid, 'remark_removed', 'Order reference removed');
                return 'DONE_DELETE';
            } else {
                return 'FAIL';
            }

        }
    }

}
