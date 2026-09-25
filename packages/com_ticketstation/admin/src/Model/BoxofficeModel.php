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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;
use stdClass;
use Ticketstation\Component\Ticketstation\Administrator\Helper\QueryHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Invoice;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SendonPayment;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SendTicketCopy;
use Ticketstation\Component\Ticketstation\Administrator\Helper\ticketcreator;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Tickets;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Transaction;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * Ticketstation Box Office Model
 * @since 0.3.2
 */
class BoxofficeModel extends ListModel
{
    /**
     * @var int
     */
    protected $id;

    /**
     * Cache for getMergedList() (live + ghost rows, sorted).
     *
     * @var array|null
     */
    private $mergedList;

    /**
     * Cache for getGhostSnapshot(): false once looked up and not a ghost, an array snapshot
     * otherwise, null before the first lookup.
     *
     * @var array|false|null
     */
    private $ghostSnapshot;

    function __construct()
    {

        parent::__construct();
        
        $app = Factory::getApplication();

        // Get the pagination request variables
        $limit      = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest('limitstart', 'limitstart', 0, 'int');

        // In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array    = $app->getInput()->get('cid', [0], 'array');
        $this->id = (int) $array[0];
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination;
    }

    function getTotal()
    {
        $this->_total = count($this->getMergedList());

        return $this->_total;
    }

    function getList()
    {
        $merged = $this->getMergedList();

        $limitstart = (int) $this->getState('limitstart');
        $limit      = (int) $this->getState('limit');

        $this->data = $limit > 0 ? array_slice($merged, $limitstart, $limit) : array_slice($merged, $limitstart);

        return $this->data;
    }

    /**
     * The live list, plus orders that the ticketcleaner removed automatically (real DELETE,
     * no row left) but whose removal was snapshotted to the History as event_type
     * 'order_removed_auto'. This is what keeps them visible in the Box Office, while every
     * other part of the app (front-end availability, reports, ...) still just queries
     * #__ticketstation_orders and sees them as genuinely gone. Orders removed manually
     * (task=remove) are never snapshotted, so they never reappear here.
     *
     * @return  array
     */
    private function getMergedList()
    {
        if ($this->mergedList !== null)
        {
            return $this->mergedList;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getListQuery());
        $live = $db->loadObjectList();

        $merged = array_merge($live, $this->buildGhostListRows());

        usort($merged, static function ($a, $b) {
            return strcmp((string) $b->orderdate, (string) $a->orderdate);
        });

        $this->mergedList = $merged;

        return $this->mergedList;
    }

    /**
     * Reconstructs display rows for ghost (auto-removed) orders, applying the same
     * paid/event/search filters the live list query applies, so they behave consistently
     * with the visible filter/search state. Client, event and ticket names are looked up
     * live (those tables are never touched by the cleaner) - only the order's own columns
     * come from the History snapshot.
     *
     * @return  array
     */
    private function buildGhostListRows()
    {
        $app = Factory::getApplication();

        $filter_paid  = $app->getUserStateFromRequest('filter_ordering_paid', 'filter_ordering_paid', '0', 'int');
        $filter_event = $app->getUserStateFromRequest('filter_ordering_event', 'filter_ordering_event', '0', 'int');
        $search       = strtolower($app->getUserStateFromRequest('searchbox', 'searchbox', '', 'string'));

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $ghosts = History::getAutoRemovedGhosts();
        $rows   = [];

        foreach ($ghosts as $ordercode => $ghost)
        {
            $lines = $ghost['rows'];

            if (empty($lines))
            {
                continue;
            }

            $first = (object) $lines[0];

            // Mirrors the live list's "a.userid != 0" filter: an unfinished cart (userid 0)
            // never showed up here either, ghost or not.
            if (empty($first->userid))
            {
                continue;
            }

            if ($filter_event != 0 && (int) $first->eventid !== (int) $filter_event)
            {
                continue;
            }

            $paidValues = array_map(static function ($line) {
                return (int) ((object) $line)->paid;
            }, $lines);
            $maxPaid = max($paidValues);

            $paidFilterMap = [1 => 1, 2 => 0, 3 => 2, 4 => 3];

            if ($filter_paid != 0 && isset($paidFilterMap[$filter_paid]) && $maxPaid !== $paidFilterMap[$filter_paid])
            {
                continue;
            }

            $query = $db->getQuery(true)->select('*')
                ->from($db->quoteName('#__ticketstation_clients'))
                ->where($db->quoteName('clientid') . ' = ' . $db->quote($first->userid));
            $db->setQuery($query);
            $client = $db->loadObject();

            if ($search)
            {
                $needle    = str_replace(' ', '', $search);
                $haystack  = strtolower($ordercode . ' ' . ($client->name ?? '') . ' ' . ($client->firstname ?? '') . ' ' . ($client->emailaddress ?? ''));
                $haystack  = str_replace(' ', '', $haystack);

                if (strpos($haystack, $needle) === false)
                {
                    continue;
                }
            }

            $query = $db->getQuery(true)->select(['eventid', 'eventname'])
                ->from($db->quoteName('#__ticketstation_events'))
                ->where($db->quoteName('eventid') . ' = ' . $db->quote($first->eventid));
            $db->setQuery($query);
            $event = $db->loadObject();

            $ticketName = null;
            if (!empty($first->ticketid))
            {
                $query = $db->getQuery(true)->select('ticketname')
                    ->from($db->quoteName('#__ticketstation_tickets'))
                    ->where($db->quoteName('ticketid') . ' = ' . $db->quote($first->ticketid));
                $db->setQuery($query);
                $ticketName = $db->loadResult();
            }

            $query = $db->getQuery(true)->select('remarks')
                ->from($db->quoteName('#__ticketstation_remarks'))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));
            $db->setQuery($query);
            $remarks = $db->loadResult();

            $query = $db->getQuery(true)->select('SUM(amount)')
                ->from($db->quoteName('#__ticketstation_transactions'))
                ->where($db->quoteName('orderid') . ' = ' . $db->quote($ordercode));
            $db->setQuery($query);
            $transactionAmount = (float) $db->loadResult();

            $orderprice = array_sum(array_map(static function ($line) {
                return (float) ((object) $line)->price;
            }, $lines));

            $rows[] = (object) [
                'orderdate'           => $first->orderdate,
                'orderid'             => $first->orderid,
                'ordercode'           => $ordercode,
                'pdfsent'             => $first->pdfsent,
                'pdfcreated'          => $first->pdfcreated,
                'published'           => $first->published,
                'coupon'              => $first->coupon,
                'discount'            => array_sum(array_column($lines, 'discount')),
                'discount_type'       => $first->discount_type,
                'discount_amount'     => $first->discount_amount,
                'total_fees'          => array_sum(array_column($lines, 'fees')),
                'ticketname'          => $ticketName,
                'o_tickets'           => count($lines),
                'name'                => $client->name ?? null,
                'firstname'           => $client->firstname ?? null,
                'address'             => $client->address ?? null,
                'city'                => $client->city ?? null,
                'emailaddress'        => $client->emailaddress ?? null,
                'eventname'           => $event->eventname ?? null,
                'scanned'             => $first->scanned,
                'downloaded'          => $first->downloaded,
                'blacklisted'         => $first->blacklisted,
                'orderprice'          => $orderprice,
                'remarks'             => $remarks,
                'remarkid'            => null,
                'transaction_amount'  => $transactionAmount,
                'paid'                => $maxPaid,
                'removed_auto'        => true,
                'removed_reason'      => $ghost['reason'],
                'removed_at'          => $ghost['created'],
            ];
        }

        return $rows;
    }

    protected function getListQuery()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $app    = Factory::getApplication();
        $config = $this->getConfig();

        // User input (dropdown + search)
        $filter_paid  = $app->getUserStateFromRequest('filter_ordering_paid', 'filter_ordering_paid', '0', 'int');
        $filter_event  = $app->getUserStateFromRequest('filter_ordering_event', 'filter_ordering_event', '0', 'int');
        $search = $app->getUserStateFromRequest('searchbox', 'searchbox', '', 'string');
        $search = strtolower($search);

        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select([
            'MAX(a.orderdate) AS orderdate',
            'MAX(a.orderid) AS orderid',
            'a.ordercode',
            'MAX(a.paid) AS paid', 'a.pdfsent', 'a.pdfcreated', 'a.published', 'a.coupon', 'SUM(a.discount) AS discount', 'a.discount_type', 'a.discount_amount', 'SUM(a.fees) AS total_fees', 't.ticketname',
            'COUNT(DISTINCT a.orderid) AS o_tickets', 'c.name', 'c.firstname', 'c.address', 'c.city', 'c.emailaddress', 'e.eventname', 'a.scanned', 'a.downloaded',
            'a.blacklisted', 'SUM(a.price) AS orderprice', 'r.remarks', 'r.id AS remarkid', 'tt.amount AS transaction_amount',
        ]);

        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_remarks', 'r') . ' ON (' . $db->quoteName('a.ordercode') . ' = ' . $db->quoteName('r.ordercode') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_transactions', 'tt') . ' ON (' . $db->quoteName('a.ordercode') . ' = ' . $db->quoteName('tt.orderid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
		$query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'co') . ' ON (' . $db->quoteName('a.orderid') . ' = ' . $db->quoteName('co.orderid') . ')');

        $query->where($db->quoteName('a.userid') . ' != 0');

        if ($filter_paid == 0)
        {
            $query->where($db->quoteName('a.paid') . ' >= 0');
        }
        if ($filter_paid == 1)
        {
            $query->where($db->quoteName('a.paid') . ' = 1');
        }
        if ($filter_paid == 2)
        {
            $query->where($db->quoteName('a.paid') . ' = 0');
        }
        if ($filter_paid == 3)
        {
            $query->where($db->quoteName('a.paid') . ' = 2');
        }
        if ($filter_paid == 4)
        {
            $query->where($db->quoteName('a.paid') . ' = 3');
        }

        if ($filter_event != 0)
        {
            $query->where($db->quoteName('a.eventid') . ' = ' . $db->quote($filter_event));
        }
        
        if ($search)
        {
            $like_filter = ' LIKE ' . $db->quote('%' . str_replace(' ', '%', $search) . '%');

            $where = [
                $db->quoteName('a.ordercode') . ' = ' . (int) $search,
                $db->quoteName('c.name') . $like_filter,
                $db->quoteName('c.firstname') . $like_filter,
                //$db->quoteName('c.address') . $like_filter,
                //$db->quoteName('c.city') . $like_filter,
                //$db->quoteName('c.zipcode') . $like_filter,
                $db->quoteName('c.emailaddress') . $like_filter,
                $db->quoteName('co.seatid') . $like_filter,
                $db->quoteName('co.row_name') . $like_filter,
                $db->quoteName('r.remarks') . $like_filter,
            ];

            $query->where('(' . implode(' OR ', $where) . ')');            
        }

        $query->group('a.ordercode');
        $query->order('a.orderid DESC');

        $query->setLimit(1000);

        return $query;
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['a.*', 't.ticketname', 't.ticketprice', 'e.eventname', 'c.*', 'cp.coupon_type', 'cp.coupon_discount', 'tt.amount AS transaction_amount', 'tt.pid', 'tt.details', 'u.name AS scanner_name']);
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('a.scanner') . ' = ' . $db->quoteName('u.id') . ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_transactions', 'tt') . ' ON (' . $db->quoteName('a.ordercode') . ' = ' . $db->quoteName('tt.orderid') . ')');
        $query->join('LEFT OUTER',
            $db->quoteName('#__ticketstation_coupons', 'cp') . ' ON (' . $db->quoteName('cp.coupon_code') . ' = ' . $db->quoteName('a.coupon') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = ' . $db->quote((int) $this->id));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        return $data;
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

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $lines = $ghost['rows'];
        $first = (object) $lines[0];

        $client = null;

        if ( ! empty($first->userid))
        {
            $query = $db->getQuery(true)->select('*')
                ->from($db->quoteName('#__ticketstation_clients'))
                ->where($db->quoteName('clientid') . ' = ' . $db->quote($first->userid));
            $db->setQuery($query);
            $client = $db->loadObject();
        }

        $event = null;

        if ( ! empty($first->eventid))
        {
            $query = $db->getQuery(true)->select(['eventid', 'eventname'])
                ->from($db->quoteName('#__ticketstation_events'))
                ->where($db->quoteName('eventid') . ' = ' . $db->quote($first->eventid));
            $db->setQuery($query);
            $event = $db->loadObject();
        }

        $orderLines = [];
        $total      = 0;

        foreach ($lines as $line)
        {
            $line = (object) $line;

            $ticket = null;

            if ( ! empty($line->ticketid))
            {
                $query = $db->getQuery(true)->select(['ticketname', 'ticketprice'])
                    ->from($db->quoteName('#__ticketstation_tickets'))
                    ->where($db->quoteName('ticketid') . ' = ' . $db->quote($line->ticketid));
                $db->setQuery($query);
                $ticket = $db->loadObject();
            }

            $orderLines[] = (object) [
                'orderid'     => $line->orderid,
                'ticketname'  => $ticket->ticketname ?? null,
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

        return (object) [
            'ordercode'  => $this->id,
            'client'     => $client,
            'eventname'  => $event->eventname ?? null,
            'orderdate'  => $first->orderdate,
            'paid'       => $first->paid,
            'reason'     => $ghost['reason'],
            'removed_at' => $ghost['created'],
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

    function getExtData()
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        QueryHelper::enableBigSelects($db);

        $query = $db->getQuery(true);

        $query->select(['ext.*', 'a.*', 't.ticketname', 't.ticketprice', 'e.eventname', 'c.*', 'cp.coupon_type', 'cp.coupon_discount', 'tt.amount AS transaction_amount', 'tt.pid', 'tt.details', 'u.name AS scanner_name']);
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON (' . $db->quoteName('a.userid') . ' = ' . $db->quoteName('c.clientid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('e.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' . $db->quoteName('a.ticketid') . ' = ' . $db->quoteName('t.ticketid') . ')');
        $query->join('LEFT', $db->quoteName('#__users', 'u') . ' ON (' . $db->quoteName('a.scanner') . ' = ' . $db->quoteName('u.id') . ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_transactions', 'tt') . ' ON (' . $db->quoteName('a.ordercode') . ' = ' . $db->quoteName('tt.orderid') . ')');
        $query->join('LEFT OUTER',
            $db->quoteName('#__ticketstation_coupons', 'cp') . ' ON (' . $db->quoteName('cp.coupon_code') . ' = ' . $db->quoteName('a.coupon') . ')');
        $query->join('LEFT OUTER',
            $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('a.orderid') . ' = ' . $db->quoteName('ext.orderid') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = ' . $db->quote((int) $this->id));
        $query->order($db->quoteName('a.ticketid') . 'ASC');
        $query->order($db->quoteName('ext.seatid') . 'ASC');

        $db->setQuery($query);
        $data = $db->loadObjectList();

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
            $path_single = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTicket-' . $row->orderid . '.pdf';

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
            $path_multi = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/eTickets-' . $row->ordercode . '.pdf';

            ## Remove single ticket
            if (file_exists($path_multi))
            {
                File::delete($path_multi);
            }

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('totaltickets') . ' = totaltickets+1',
            ];

            $conditions = [
                $db->quoteName('ticketid') . ' = ' . $row->ticketid,
            ];

            $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if ( ! $result)
            {
                return false;
            }

            ## This is for the parent ticket. (there is a parent available)
            ## If not, then the query won't have to run as there is no parent.
            if ($row->parentticket != 0)
            {

                $query = $db->getQuery(true);

                $fields = [
                    $db->quoteName('totaltickets') . ' = totaltickets+1',
                ];

                $conditions = [
                    $db->quoteName('ticketid') . ' = ' . $row->parentticket,
                ];

                $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

                $db->setQuery($query);

                $result = $db->execute();

                if ( ! $result)
                {
                    return false;
                }
            }

            if ($row->seat_sector != 0)
            {
                $query = $db->getQuery(true);

                $fields = [
                    $db->quoteName('booked') . ' = 0',
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

    function paymentResender($cid = [])
    {
		
        $cids = implode(',', $cid);

        $config = $this->getConfig();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' IN (' . $cids . ')');
        $query->group('ordercode');

        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++)
        {

            $row = $data[$i];

            ## Getting the order amount. 
            $total = (new getAmount)->_getAmount($row->ordercode, 1);
            $price = TicketstationFunctions::showprice($config->priceformat, $total, $config->valuta);

            $query = $db->getQuery(true);

            $query->select(['COUNT(orderid) AS total', 'userid']);
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('paid') . ' != ' . $db->quote('1'));
            $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $row->ordercode));

            $db->setQuery($query);
            $item = $db->loadObject();

            if ($item->total > 0)
            {
				## Update the paymentstate to Pending (3) and the orderdate to now
				## The latter is done to prevent premature removal of unpaid orders by the ticketcleaner. We should give the customer some time to complete payment.
				$new_orderdate = date('Y-m-d H:i:s');

				$query = $db->getQuery(true);

				$fields = [
					$db->quoteName('orderdate') . ' = ' . $db->quote($new_orderdate),
                    $db->quoteName('paid') . ' = 3',
                    $db->quoteName('published') . ' = 1',
				];

				$conditions = [
					$db->quoteName('ordercode') . ' = ' . $db->quote((int) $row->ordercode),
				];

				$query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

				$db->setQuery($query);

				$db->execute();
				
                ## Getting the order amount. 
                $total = (new getAmount)->_getAmount($row->ordercode, 1);
                $price = TicketstationFunctions::showprice($config->priceformat, $total, $config->valuta);


                require_once JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helper/PaymentAPI.php';
                $processor = new PaymentAPI((int) $row->ordercode);
                $message   = new eTicketsMessage;

                $variables = [
                    'orderlist'   => $processor->getOrderList(),
                    'ordercode'   => $row->ordercode,
                    'price'       => $price,
                    'paymentlink' => $processor->generatePaymentLink($row->ordercode),
                ];

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

            if ($config->show_waitinglist == 1)
            {
                $waiting = new WaitingList;
                $waiting->processList($cid);
            }

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

            foreach (array_unique(array_column($data, 'ordercode')) as $removed_ordercode) {
                History::log($removed_ordercode, 'order_removed', 'Order removed');

                // An invoice for a deleted order shouldn't survive it.
                (new Invoice)->remove($removed_ordercode);

                // Nor should its transaction, if one was ever recorded.
                (new Transaction)->remove($removed_ordercode);
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
                $ticket_helper->removeTicketFromServer($row->orderid, $row->barcode);
                $ticket_helper->removeMultiTicketFromServer($row->ordercode);

                // Increasing ticket totals:
                $ticket_helper->increaseTicketTotals($row->ticketid);

                // Increasing parentticket totals:
                if ($row->parentticket != 0)
                {
                    $ticket_helper->increaseTicketTotals($row->parentticket);
                }

                // Check if there was a seat booked:
                if ($row->seat_sector != 0)
                {
                    $ticket_helper->resetSeatSate($row->orderid);
                }
            }

            return true;
        }

        return false;
    }

    function ticketprocessor($ordercode)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = '. $db->quote($ordercode));

        ## Do the query now
        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++)
        {
            $row = $data[$i];

            if ($row->orderid)
            {
                $creator = new ticketcreator($row->orderid);
                $creator->doPDF();
            }
        }

        if (count($data)) {
            History::log($ordercode, 'tickets_generated', 'Tickets generated');
        }

        ## creating the combined ticket
        $query = $db->getQuery(true);

        $query->select(
            array('a.*', 'c.name', 'c.emailaddress', 'c.firstname', 'e.eventname', 't.ticket_size', 't.ticket_orientation')
        );
        $query->from($db->quoteName('#__ticketstation_orders', 'a'));
        $query->join('LEFT',$db->quoteName('#__ticketstation_clients', 'c') . ' ON ('.$db->quoteName('a.userid').' = '.$db->quoteName('c.clientid') .')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' .$db->quoteName('e.eventid') . ' = ' . $db->quoteName('a.eventid') . ')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_tickets', 't') . ' ON (' .$db->quoteName('t.ticketid'). ' = ' .$db->quoteName('a.ticketid'). ')');
        $query->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON (' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('a.orderid') . ')');
        $query->where($db->quoteName('a.ordercode') . ' = '. $db->quote($ordercode));
        $query->order($db->quoteName('ext.seatid') . ' ASC');

        $db->setQuery($query);
        $info = $db->loadObjectList();

        if(count($info)>1)
        {
            $creator = new SendonPayment( $ordercode );
            $creator->combinetickets($info);
        }

        return true;
    }

    function sendtickets($cid = [])
    {
        if (count($cid))
        {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode(',', $cid);

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select(['orderid']);
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('ordercode') . ' IN (' . $cids . ')');
            $query->where($db->quoteName('pdfcreated') . ' = ' . $db->quote(1));
            $query->where($db->quoteName('pdfsent') . ' = ' . $db->quote(0));
            $query->where($db->quoteName('paid') . ' = ' . $db->quote(1));

            ## Do the query now	
            $db->setQuery($query);
            $data = $db->loadObjectList();

            $counttickets = count($data);

            ## OK, pickup tickets where all cids are selcted
            $query = $db->getQuery(true);

            $query->select('*');
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('ordercode') . ' IN (' . $cids . ')');

            ## Do the query now	
            $db->setQuery($query);
            $items = $db->loadObjectList();

            $countalltickets = count($items);

            ## Let's do the check if both counts are equal? 
            ## If they're not equal? DO NOT SEND THE TICKETS.
            if ($counttickets != $countalltickets)
            {
                $error = $countalltickets - $counttickets;

                if ($error > 1)
                {
                    $this->setError(Text::_('COM_TICKETMASTER_ERROR_BEFORE_SENDING_NEW'));
                    return false;
                }
                else
                {
                    $this->setError(Text::_('COM_TICKETMASTER_ERROR_BEFORE_SENDING_NEW'));
                    return false;
                }
            }

            ## OK, all checks have been done. We do now know the next things:
            ## An PDF file has been created, the payments are done, and these tickets haven't been sent.
            ## Let's group it again by order and send the tickets to the customer.
            $query = $db->getQuery(true);

            $query->select(['orderid', 'ticketid']);
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('ordercode') . ' IN (' . $cids . ')');
            $query->where($db->quoteName('pdfcreated') . ' = ' . $db->quote(1));
            $query->where($db->quoteName('pdfsent') . ' = ' . $db->quote(0));
            $query->where($db->quoteName('paid') . ' = ' . $db->quote(1));
            $query->group('ordercode');

            ## Do the query now	
            $db->setQuery($query);
            $data = $db->loadObjectList();

            ## Let's send the order one by one into another function to send them
            for ($i = 0, $n = count($data); $i < $n; $i++)
            {
                $row = $data[$i];
                $this->_sendTickets($row->ordercode);
                $this->_updateTickets($row->ordercode);
                History::log($row->ordercode, 'tickets_sent', 'Tickets sent');
            }
        }
    }

    function _sendTickets($eid)
    {

        $order = new SendonPayment((int) $eid);
        $order->send();
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

    function _updateTickets($eid)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['ticketid']);
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode') . ' = ' . $db->quote((int) $eid));

        $db->setQuery($query);
        $data = $db->loadObjectList();

        for ($i = 0, $n = count($data); $i < $n; $i++)
        {

            $row = $data[$i];

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('ticketssent') . ' = ticketssent+1',
            ];

            $conditions = [
                $db->quoteName('ticketid') . ' = ' . $row->ticketid,
            ];

            $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if ( ! $result)
            {
                return false;
            }
        }

        return true;
    }

}