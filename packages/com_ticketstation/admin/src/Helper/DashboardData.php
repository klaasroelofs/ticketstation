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
use Joomla\CMS\Mail\MailHelper;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * The figures of the Ticketstation dashboard, shared by the Control panel of the component and by
 * the Joomla dashboard module (mod_ticketstation_dashboard), so both always show the same numbers.
 * Everything that reads orders, waiting list entries and the like is scoped with
 * TestData::condition().
 */
class DashboardData
{
    /**
     * Timezone of the current user, falling back to the site timezone.
     *
     * @return  \DateTimeZone
     */
    private static function getZone()
    {
        $app = Factory::getApplication();

        return new \DateTimeZone($app->getIdentity()->getParam('timezone') ?: ($app->get('offset') ?: 'UTC'));
    }

    /**
     * "Now" (SQL format) for comparing with ticket dates (startdate, sale_stop, publish_date_time),
     * which are stored in the site's local time. See Date::localNow().
     *
     * @return  string
     */
    private static function getLocalNow($modify = null)
    {
        return Date::localNow('Y-m-d H:i:s', $modify);
    }

    /**
     * Start and end (UTC, SQL format) of the current and previous week (monday - sunday)
     * and of the current calendar month, based on the site/user timezone.
     *
     * orderdate is stored in UTC, so the local period boundaries are converted to UTC.
     *
     * @return  array
     */
    private static function getPeriods()
    {
        $zone = self::getZone();

        $weekStart     = new \DateTime('monday this week 00:00:00', $zone);
        $prevWeekStart = (clone $weekStart)->modify('-1 week');
        $monthStart    = new \DateTime('first day of this month 00:00:00', $zone);

        $toUtc = static function (\DateTime $date) {
            return (clone $date)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        };

        return [
            'week'      => [$toUtc($weekStart), null],
            'prev_week' => [$toUtc($prevWeekStart), $toUtc($weekStart)],
            'month'     => [$toUtc($monthStart), null],
        ];
    }

    /**
     * Paid tickets, orders and ticket revenue (price minus discount, excluding
     * administration costs) for a period. An empty end means "until now".
     *
     * @return  object  tickets, orders, revenue, fees (the service fees on top of the revenue)
     */
    private static function getSalesForPeriod($start, $end = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'COUNT(orderid) AS tickets',
                'COUNT(DISTINCT ordercode) AS orders',
                'SUM(COALESCE(price, 0) - COALESCE(discount, 0)) AS revenue',
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where(TestData::condition('test'))
            ->where($db->quoteName('paid') . ' = 1')
            ->where($db->quoteName('orderdate') . ' >= ' . $db->quote($start));

        if ($end)
        {
            $query->where($db->quoteName('orderdate') . ' < ' . $db->quote($end));
        }

        $db->setQuery($query);
        $sales = $db->loadObject();

        $sales->tickets = (int) $sales->tickets;
        $sales->orders  = (int) $sales->orders;
        $sales->revenue = (float) $sales->revenue;
        $sales->fees    = self::getFeesForPeriod($start, $end);

        return $sales;
    }

    /**
     * The service fees of the paid orders in a period. They aren't stored but worked out per
     * order from its paid rows (price minus the coupon discount on the row) and the terms kept
     * for it, as OrderTotals::get() does.
     *
     * @return  float
     */
    private static function getFeesForPeriod($start, $end = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('o.ordercode'),
                'SUM(COALESCE(' . $db->quoteName('o.price') . ', 0) - COALESCE(' . $db->quoteName('o.discount') . ', 0)) AS subtotal',
                'MAX(' . $db->quoteName('ot.fee_type') . ') AS fee_type',
                'MAX(' . $db->quoteName('ot.fee_rate') . ') AS fee_rate',
                'MAX(' . $db->quoteName('ot.fee_fixed') . ') AS fee_fixed',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->where(TestData::condition('o.test'))
            ->join('LEFT', $db->quoteName('#__ticketstation_ordertotals', 'ot') . ' ON ' . $db->quoteName('ot.ordercode') . ' = ' . $db->quoteName('o.ordercode'))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where($db->quoteName('o.orderdate') . ' >= ' . $db->quote($start))
            ->group($db->quoteName('o.ordercode'));

        if ($end)
        {
            $query->where($db->quoteName('o.orderdate') . ' < ' . $db->quote($end));
        }

        $db->setQuery($query);

        $fees = 0.0;

        foreach ($db->loadObjectList() as $order)
        {
            $terms = OrderTotals::keptTerms($order) ?? OrderTotals::terms($order->ordercode);
            $fees += OrderTotals::feesFor((float) $order->subtotal, $terms);
        }

        return round($fees, PaymentCurrencies::decimals());
    }

    /**
     * Key figures for the control panel: sales this week / previous week / this month,
     * and the number of events and tickets currently on sale.
     *
     * @return  array
     */
    public static function stats()
    {
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $periods = self::getPeriods();

        $stats = [
            'week'      => self::getSalesForPeriod(...$periods['week']),
            'prev_week' => self::getSalesForPeriod(...$periods['prev_week']),
            'month'     => self::getSalesForPeriod(...$periods['month']),
        ];

        $query = $db->getQuery(true)
            ->select(['COUNT(DISTINCT t.eventid) AS events', 'COUNT(t.ticketid) AS tickets'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.parent') . ' = 0')
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote(self::getLocalNow()));

        $db->setQuery($query);
        $onSale = $db->loadObject();

        $stats['on_sale_events']  = (int) $onSale->events;
        $stats['on_sale_tickets'] = (int) $onSale->tickets;

        return $stats;
    }

    /**
     * Paid tickets and ticket revenue per day for the last $days days (today included), in the
     * site/user timezone, oldest first. Days without sales are included with zeros.
     *
     * @return  array  objects with date (Y-m-d), tickets, revenue
     */
    public static function dailySales($days = 28)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $zone  = self::getZone();
        $first = (new \DateTime('today', $zone))->modify('-' . ((int) $days - 1) . ' days');
        $daily = [];

        for ($day = clone $first, $i = 0; $i < $days; $i++, $day->modify('+1 day'))
        {
            $daily[$day->format('Y-m-d')] = (object) ['date' => $day->format('Y-m-d'), 'tickets' => 0, 'revenue' => 0.0];
        }

        // orderdate is stored in UTC and shared by all rows of one order, so group on it
        // and assign each order to its local day here.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('orderdate'),
                'COUNT(orderid) AS tickets',
                'SUM(COALESCE(price, 0) - COALESCE(discount, 0)) AS revenue',
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where(TestData::condition('test'))
            ->where($db->quoteName('paid') . ' = 1')
            ->where($db->quoteName('orderdate') . ' >= ' . $db->quote((clone $first)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')))
            ->group($db->quoteName('orderdate'));

        $db->setQuery($query);

        foreach ($db->loadObjectList() as $row)
        {
            $date = (new \DateTime($row->orderdate, new \DateTimeZone('UTC')))->setTimezone($zone)->format('Y-m-d');

            if (isset($daily[$date]))
            {
                $daily[$date]->tickets += (int) $row->tickets;
                $daily[$date]->revenue += (float) $row->revenue;
            }
        }

        return array_values($daily);
    }

    /**
     * The first steps a new installation needs before it can sell tickets, each with whether it
     * is done and a link to the screen where it is done.
     *
     * @return  array  objects with key (language key), done, link
     */
    public static function setupSteps($config)
    {
        $provider = ProviderRegistry::active();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $has = static function ($table, $where = null) use ($db) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($table));

            if ($where)
            {
                $query->where($where);
            }

            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        };

        $steps = [
            ['COM_TICKETSTATION_CPANEL_START_COMPANY', !self::isCompanyIncomplete($config), 'index.php?option=com_ticketstation&view=configuration#company'],
            // With online payments switched off there is no payment account to set up.
            ['COM_TICKETSTATION_CPANEL_START_PAYMENTS', ProviderRegistry::activeId() === '' || ($provider !== null && $provider->isConfigured()), 'index.php?option=com_ticketstation&view=payments'],
            ['COM_TICKETSTATION_CPANEL_START_VENUE', $has('#__ticketstation_venues'), 'index.php?option=com_ticketstation&view=venues'],
            ['COM_TICKETSTATION_CPANEL_START_EVENT', $has('#__ticketstation_events'), 'index.php?option=com_ticketstation&view=events'],
            ['COM_TICKETSTATION_CPANEL_START_TICKET', $has('#__ticketstation_tickets', $db->quoteName('parent') . ' = 0'), 'index.php?option=com_ticketstation&view=tickets'],
        ];

        return array_map(static function ($step) {
            return (object) ['key' => $step[0], 'done' => $step[1], 'link' => $step[2]];
        }, $steps);
    }

    /**
     * Availability of the upcoming published tickets, computed by the Availability helper
     * exactly like the storefront does, so "left" matches the site.
     *
     * @return  array
     */
    public static function availability($limit = 10)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                't.ticketid', 't.ticketname', 't.startdate', 't.starting_total_tickets', 't.show_seatplans',
                'e.eventname',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.parent') . ' = 0')
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote(self::getLocalNow()))
            ->order($db->quoteName('t.startdate') . ' ASC');

        $db->setQuery($query, 0, (int) $limit);
        $rows = $db->loadObjectList();

        foreach ($rows as $row)
        {
            // Over all variants of a parent and following their counter settings (seated
            // tickets: free seats), the same figures as the storefront.
            $availability   = Availability::summary((int) $row->ticketid);
            $row->total     = $availability->capacity;
            $row->available = $availability->available;
            $row->sold      = max(0, $row->total - $row->available);
            $row->percentage_sold = $row->total > 0 ? min(100, round($row->sold / $row->total * 100)) : 100;
        }

        return $rows;
    }

    /**
     * Things that need the administrator's attention. Only items with a count above zero
     * are returned, each with a language key, a count and a link to the screen to act on it.
     *
     * @return  array
     */
    public static function attention($config)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $now   = self::getLocalNow();
        $soon  = self::getLocalNow('+7 days');
        $items = [];

        $add = static function ($key, $count, $link, $icon, $level = 'warning') use (&$items) {
            if ((int) $count > 0)
            {
                // The view the link opens, so the control panel can put a badge on that screen's button.
                parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

                $items[] = (object) [
                    'key'   => $key,
                    'count' => (int) $count,
                    'link'  => $link,
                    'icon'  => $icon,
                    'level' => $level,
                    'view'  => $query['view'] ?? '',
                ];
            }
        };

        // The payment provider's own warnings (test mode, a missing or wrong API key). None while
        // online payments are switched off: the site may have no payment account.
        $provider     = ProviderRegistry::active();
        $paymentsLink = 'index.php?option=com_ticketstation&view=payments';

        // A provider is chosen, but its plugin is switched off or removed: nobody can pay.
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_PROVIDER_MISSING', ProviderRegistry::activeId() !== '' && $provider === null ? 1 : 0,
            $paymentsLink, 'fa-exclamation-circle', 'danger');

        // The provider can't collect the payment currency: every payment would be refused.
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_CURRENCY_UNSUPPORTED',
            $provider !== null && !isset(PaymentCurrencies::forProvider($provider)[ProviderRegistry::currency()]) ? 1 : 0,
            $paymentsLink, 'fa-exclamation-circle', 'danger');

        foreach ($provider ? $provider->getHealthWarnings() : [] as $warning)
        {
            $add($warning['key'], 1, $paymentsLink, 'fa-exclamation-circle', $warning['level']);
        }

        // Company details and mail sender, both on the Company tab of the Configuration.
        $companyLink = 'index.php?option=com_ticketstation&view=configuration#company';

        $add('COM_TICKETSTATION_CPANEL_ATTENTION_COMPANY_INCOMPLETE', self::isCompanyIncomplete($config) ? 1 : 0,
            $companyLink, 'fa-building');

        // The sender is resolved as in eTicketsMessage: the Sender Mail Address, else the company email.
        // Without a valid address the mailer keeps Joomla's global sender.
        $sender = trim((string) $config->from_email) ?: trim((string) $config->email);

        $add('COM_TICKETSTATION_CPANEL_ATTENTION_MAIL_JOOMLA_SENDER', MailHelper::isEmailAddress($sender) ? 0 : 1,
            $companyLink, 'fa-at');

        // Payments still pending (Mollie status open/pending).
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ordercode)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where(TestData::condition('test'))
            ->where($db->quoteName('paid') . ' = 3');
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_PENDING', $db->loadResult(),
            self::boxofficeLink(4), 'fa-clock');

        // Refunds and chargebacks made in the Mollie dashboard that wait for a decision about the
        // tickets, and refunds that failed after the tickets were dealt with. One opens straight
        // away; more open the Box Office filtered on them.
        $refunds = Refund::attention();
        $order   = 'index.php?option=com_ticketstation&view=boxoffice&controller=boxoffice&task=';

        $add('COM_TICKETSTATION_CPANEL_ATTENTION_REFUND_DECISION', $refunds->decision->count,
            $refunds->decision->count === 1
                ? $order . 'refundform&cid=' . $refunds->decision->ordercode . '&refund=' . $refunds->decision->id
                : self::boxofficeLink(5), 'fa-reply', 'danger');
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_REFUND_FAILED', $refunds->failed->count,
            $refunds->failed->count === 1 ? $order . 'edit&cid=' . $refunds->failed->ordercode : self::boxofficeLink(5),
            'fa-exclamation-circle', 'danger');

        // Paid orders for upcoming tickets whose tickets were never e-mailed.
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT o.ordercode)')
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->where(TestData::condition('o.test'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where($db->quoteName('o.pdfsent') . ' = 0')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($now));
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_NOT_SENT', $db->loadResult(),
            self::boxofficeLink(1, 2), 'fa-envelope', 'danger');

        // Unfinished orders that will not be cleaned up automatically.
        if ($config->remove_unfinished != 1)
        {
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ordercode)')
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where(TestData::condition('test'))
                ->where($db->quoteName('paid') . ' = 0');
            $db->setQuery($query);
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_UNFINISHED', $db->loadResult(),
                self::boxofficeLink(2), 'fa-shopping-cart', 'secondary');
        }

        // Confirmed waitinglist entries that have not been processed yet.
        if (WaitingList::anywhere())
        {
            $query = $db->getQuery(true)
                ->select('COUNT(id)')
                ->from($db->quoteName('#__ticketstation_waitinglist'))
                ->where(TestData::condition('test'))
                ->where($db->quoteName('confirmed') . ' = 1')
                ->where($db->quoteName('processed') . ' = 0');
            $db->setQuery($query);
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_WAITINGLIST', $db->loadResult(),
                'index.php?option=com_ticketstation&view=waitinglist', 'fa-hourglass-half', 'secondary');
        }

        // Published coupons that expire within 7 days, or have used 90% or more of their limit.
        $query = $db->getQuery(true)
            ->select('COUNT(c.coupon_id)')
            ->from($db->quoteName('#__ticketstation_coupons', 'c'))
            ->where($db->quoteName('c.published') . ' = 1')
            ->where('((' . $db->quoteName('c.coupon_valid_to') . ' BETWEEN ' . $db->quote(substr($now, 0, 10)) . ' AND ' . $db->quote(substr($soon, 0, 10)) . ')'
                . ' OR (' . $db->quoteName('c.coupon_limit') . ' > 0 AND ' . Coupon::usageSql('c.coupon_code') . ' >= 0.9 * ' . $db->quoteName('c.coupon_limit') . '))');
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_COUPONS', $db->loadResult(),
            'index.php?option=com_ticketstation&view=coupons', 'fa-percent', 'secondary');

        // Tickets whose sale stops, or that get published automatically, within 7 days.
        $query = $db->getQuery(true)
            ->select('COUNT(ticketid)')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where('((' . $db->quoteName('use_sale_stop') . ' = 1 AND ' . $db->quoteName('published') . ' = 1'
                . ' AND ' . $db->quoteName('sale_stop') . ' BETWEEN ' . $db->quote($now) . ' AND ' . $db->quote($soon) . ')'
                . ' OR (' . $db->quoteName('use_auto_publish') . ' = 1 AND ' . $db->quoteName('published') . ' = 0'
                . ' AND ' . $db->quoteName('publish_date_time') . ' BETWEEN ' . $db->quote($now) . ' AND ' . $db->quote($soon) . '))');
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_SCHEDULED', $db->loadResult(),
            'index.php?option=com_ticketstation&view=tickets', 'fa-calendar-alt', 'secondary');

        return $items;
    }

    /**
     * The events that are today, with how far the entrance is: the tickets that count (paid, not
     * blocked and not made invalid by a refund, as the scanner counts them), how many of those are
     * scanned, and when the last one was. Events without a sold ticket are listed with zeros.
     * Today is the site's day, as ticket dates are stored in site time.
     *
     * @return  array  objects with eventid, eventname, start (the first start today), sold, scanned,
     *                 left, percentage, lastscan (the scan date as stored, or null)
     */
    public static function doorToday(): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $today = self::getLocalNow();
        $from  = substr($today, 0, 10) . ' 00:00:00';
        $to    = (new \DateTime($from))->modify('+1 day')->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->select([
                'e.eventid',
                'e.eventname',
                'MIN(t.startdate) AS start',
                'COUNT(o.orderid) AS sold',
                'COALESCE(SUM(o.scanned), 0) AS scanned',
                'MAX(o.scandate) AS lastscan',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid'))
            ->join(
                'LEFT',
                $db->quoteName('#__ticketstation_orders', 'o') . ' ON ' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid')
                . ' AND ' . $db->quoteName('o.paid') . ' = 1'
                . ' AND COALESCE(' . $db->quoteName('o.blacklisted') . ', 0) <> 1'
                . ' AND ' . Refund::validSql('o')
                . ' AND ' . TestData::condition('o.test')
            )
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($from))
            ->where($db->quoteName('t.startdate') . ' < ' . $db->quote($to))
            ->group([$db->quoteName('e.eventid'), $db->quoteName('e.eventname')])
            ->order('MIN(t.startdate) ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $row)
        {
            $row->eventid    = (int) $row->eventid;
            $row->sold       = (int) $row->sold;
            $row->scanned    = (int) $row->scanned;
            $row->left       = max(0, $row->sold - $row->scanned);
            $row->percentage = $row->sold > 0 ? min(100, (int) round($row->scanned / $row->sold * 100)) : 0;
        }

        return $rows;
    }

    /**
     * The latest paid orders, newest first, one per order code: when, who, which event, how many
     * tickets and the total of the order (the tickets minus a coupon, plus the service fee).
     *
     * @param   int  $limit  How many orders.
     *
     * @return  array  objects with ordercode, orderdate (UTC, as stored), name, eventname, events
     *                 (the number of different events), tickets, total
     */
    public static function latestOrders(int $limit = 8): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('o.ordercode'),
                'MAX(' . $db->quoteName('o.orderdate') . ') AS orderdate',
                'MIN(' . $db->quoteName('c.name') . ') AS name',
                'MIN(' . $db->quoteName('e.eventname') . ') AS eventname',
                'COUNT(DISTINCT ' . $db->quoteName('o.eventid') . ') AS events',
                'COUNT(' . $db->quoteName('o.orderid') . ') AS tickets',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid'))
            ->where(TestData::condition('o.test'))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->group($db->quoteName('o.ordercode'))
            ->order('MAX(' . $db->quoteName('o.orderdate') . ') DESC')
            ->order('MAX(' . $db->quoteName('o.orderid') . ') DESC');

        $db->setQuery($query, 0, max(1, $limit));
        $rows = $db->loadObjectList();

        foreach ($rows as $row)
        {
            $row->events  = (int) $row->events;
            $row->tickets = (int) $row->tickets;
            $row->total   = (float) OrderTotals::get($row->ordercode)->total;
        }

        return $rows;
    }

    /**
     * The number of attention items that point at one screen, the number on that screen's button
     * in the Control panel (the Box Office, for the counter on its quick link).
     *
     * @param   string  $view  The view the items link to, for instance 'boxoffice'
     *
     * @return  int
     */
    public static function attentionCount(string $view): int
    {
        $count = 0;

        foreach (self::attention((new Config())->get()) as $item)
        {
            if ($item->view === $view)
            {
                $count += $item->count;
            }
        }

        return $count;
    }

    /**
     * Whether the company details still lack what invoices and mails need: an empty name, address,
     * postcode, city or email, or example data that a fresh install seeds (install.mysql.utf8.sql).
     *
     * @return  bool
     */
    private static function isCompanyIncomplete($config)
    {
        foreach (['companyname', 'address1', 'zipcode', 'city', 'email'] as $field)
        {
            if (trim((string) $config->$field) === '')
            {
                return true;
            }
        }

        $examples = [
            'companyname' => 'Company',
            'address1'    => 'My Contact Adres 12',
            'city'        => 'YourCity',
            'phone'       => '0123-456789',
        ];

        foreach ($examples as $field => $example)
        {
            if (trim((string) $config->$field) === $example)
            {
                return true;
            }
        }

        // Seeded email and website: info@yourdomain.com and https://www.yourdomain.com
        return stripos($config->email . ' ' . $config->website, 'yourdomain.com') !== false;
    }

    /**
     * A link to the Box Office showing exactly the orders an attention item counts: every
     * filter is set, so a search or event filter left from earlier doesn't hide any.
     *
     * @param   int  $paid  Payment status filter (1 paid, 2 not paid, 3 refunded, 4 pending)
     * @param   int  $sent  Tickets sent filter (1 sent, 2 not sent)
     *
     * @return  string
     */
    private static function boxofficeLink(int $paid, int $sent = 0)
    {
        return 'index.php?option=com_ticketstation&view=boxoffice&searchbox=&filter_ordering_event=0'
            . '&filter_ordering_paid=' . $paid . '&filter_ordering_sent=' . $sent;
    }

    /**
     * The screen buttons of the Control panel: language key of the group heading => rows of
     * [view, icon, label key], limited to the screens this user may open (see AclGate). Groups that
     * end up empty are left out.
     *
     * @return  array
     */
    public static function tileGroups(): array
    {
        $groups = [
            'COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT' => [
                ['boxoffice', 'fa-money-bill-alt', 'COM_TICKETSTATION_BOXOFFICE'],
                ['clients', 'fa-users', 'COM_TICKETSTATION_CUSTOMERS'],
                ['transactions', 'fa-credit-card', 'COM_TICKETSTATION_TRANSACTIONS'],
                ['invoices', 'fa-file-invoice', 'COM_TICKETSTATION_INVOICES'],
                ['coupons', 'fa-percent', 'COM_TICKETSTATION_COUPONS'],
                ['reservation', 'fa-calendar-plus', 'COM_TICKETSTATION_RESERVATION_CPANEL_BUTTON'],
            ],
            'COM_TICKETSTATION_CPANEL_HEADER_TICKETMANAGEMENT' => [
                ['tickets', 'fa-ticket-alt', 'COM_TICKETSTATION_TICKETS'],
                ['events', 'fa-calendar-alt', 'COM_TICKETSTATION_EVENTS'],
                ['venues', 'fa-solid fa-hotel', 'COM_TICKETSTATION_VENUES'],
                ['seatplans', 'fa-chair', 'COM_TICKETSTATION_SEATPLANS'],
            ],
            'COM_TICKETSTATION_CPANEL_HEADER_CONFIGURATION' => [
                ['scanners', 'fa-qrcode', 'COM_TICKETSTATION_TICKETSCANNING'],
                ['templates', 'fa-envelope', 'COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'],
                ['configuration', 'fa-cog', 'COM_TICKETSTATION_CONFIGURATION'],
                ['payments', 'fa-credit-card', 'COM_TICKETSTATION_PAYMENTS_CONFIG'],
                ['docs', 'fa-book', 'COM_TICKETSTATION_VIEW_DOCS_TITLE'],
            ],
        ];

        if (WaitingList::anywhere())
        {
            $groups['COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT'][] = ['waitinglist', 'fa-hourglass-half', 'COM_TICKETSTATION_WAITINGLIST'];
        }

        foreach ($groups as $key => $tiles)
        {
            $groups[$key] = array_values(array_filter($tiles, static fn($tile) => AclGate::canOpen($tile[0])));
        }

        return array_filter($groups);
    }

    /**
     * Everything the "Quick links" widget of the dashboard module can offer this user, by key: the
     * screens of the Control panel plus the scanning page. Events and Tickets also carry the link
     * to add one (the plus button of Joomla's own quick icons). Only what the user may use is
     * returned, so a link is never a refusal.
     *
     * @return  array  key => object with link, icon (a Font Awesome class), label (language key of
     *                 the module), newTab, add (link to add a record, or null), counter (address of the
     *                 counter of what needs attention, or null)
     */
    public static function quickLinks(): array
    {
        $input = Factory::getApplication()->getInput();
        $links = [];

        $add = static function ($key, $link, $icon, $newTab = false, $addLink = null) use (&$links) {
            $links[$key] = (object) [
                'link'   => $link,
                'icon'   => $icon,
                'label'  => 'MOD_TICKETSTATION_DASHBOARD_LINK_' . strtoupper($key),
                'newTab' => $newTab,
                'add'    => $addLink,
                // The Box Office button shows how much needs attention there (see ControlpanelController::quickicon)
                'counter' => $key === 'boxoffice'
                    ? 'index.php?option=com_ticketstation&amp;controller=controlpanel&amp;task=quickicon&amp;key=boxoffice&amp;format=json'
                    : null,
            ];
        };

        if (AclGate::canOpen('controlpanel'))
        {
            $add('controlpanel', 'index.php?option=com_ticketstation&view=controlpanel', 'fa-home');
        }

        foreach (self::tileGroups() as $tiles)
        {
            foreach ($tiles as [$view, $icon])
            {
                $addLink = null;

                if (in_array($view, ['events', 'tickets'], true) && AclGate::allows($view, 'add', $input))
                {
                    $addLink = 'index.php?option=com_ticketstation&controller=' . $view . '&task=add';
                }

                $add($view, 'index.php?option=com_ticketstation&view=' . $view, $icon, false, $addLink);
            }
        }

        // The scanning page is on the website, for staff who are logged in there.
        if (AclGate::can('ticketstation.boxoffice'))
        {
            $add('scanpage', \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_ticketstation&view=ticketscanning', 'fa-qrcode', true);
        }

        return $links;
    }
}
