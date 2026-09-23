<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

/**
 * Ticketstation ControlPanel Model
 * @since 0.0.8
 */
class ControlpanelModel extends BaseDatabaseModel
{
    /**
     * @var mixed
     * @since version
     */
    public $data;

    /**
     * @var mixed
     * @since version
     */

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
                    ->select(array('manifest_cache'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote('Ticketstation'));

        $db->setQuery($query);
        $this->data = json_decode($db->loadResult(), true);

        return $this->data;
    }
	
	function getMollie() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Making the query for showing all the clients in list function
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        return $db->loadObject();
    }

    function getConfig() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Timezone of the current user, falling back to the site timezone.
     *
     * @return  \DateTimeZone
     */
    private function getZone()
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
    private function getLocalNow($modify = null)
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
    private function getPeriods()
    {
        $zone = $this->getZone();

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
     * @return  object  tickets, orders, revenue
     */
    private function getSalesForPeriod($start, $end = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'COUNT(orderid) AS tickets',
                'COUNT(DISTINCT ordercode) AS orders',
                'SUM(COALESCE(price, 0) - COALESCE(discount, 0)) AS revenue',
            ])
            ->from($db->quoteName('#__ticketstation_orders'))
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

        return $sales;
    }

    /**
     * Key figures for the control panel: sales this week / previous week / this month,
     * and the number of events and tickets currently on sale.
     *
     * @return  array
     */
    function getStats()
    {
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $periods = $this->getPeriods();

        $stats = [
            'week'      => $this->getSalesForPeriod(...$periods['week']),
            'prev_week' => $this->getSalesForPeriod(...$periods['prev_week']),
            'month'     => $this->getSalesForPeriod(...$periods['month']),
        ];

        $query = $db->getQuery(true)
            ->select(['COUNT(DISTINCT t.eventid) AS events', 'COUNT(t.ticketid) AS tickets'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->where($db->quoteName('t.parent') . ' = 0')
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($this->getLocalNow()));

        $db->setQuery($query);
        $onSale = $db->loadObject();

        $stats['on_sale_events']  = (int) $onSale->events;
        $stats['on_sale_tickets'] = (int) $onSale->tickets;

        return $stats;
    }

    /**
     * Availability of the upcoming published tickets. Counts every order row for the ticket,
     * exactly like the frontend does (Ticket::getTicketsSoldById()), so "left" matches the site.
     *
     * @return  array
     */
    function getAvailability($limit = 10)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $sold = $db->getQuery(true)
            ->select(['ticketid', 'COUNT(orderid) AS sold'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->group($db->quoteName('ticketid'));

        $query = $db->getQuery(true)
            ->select([
                't.ticketid', 't.ticketname', 't.startdate', 't.starting_total_tickets', 't.show_seatplans',
                'e.eventname', 'COALESCE(s.sold, 0) AS sold',
            ])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('INNER', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'))
            ->join('LEFT', '(' . $sold . ') AS s ON ' . $db->quoteName('s.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('t.parent') . ' = 0')
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($this->getLocalNow()))
            ->order($db->quoteName('t.startdate') . ' ASC');

        $db->setQuery($query, 0, (int) $limit);
        $rows = $db->loadObjectList();

        foreach ($rows as $row)
        {
            $row->total     = (int) $row->starting_total_tickets;
            $row->sold      = (int) $row->sold;
            $row->available = max(0, $row->total - $row->sold);
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
    function getAttention($config)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $now   = $this->getLocalNow();
        $soon  = $this->getLocalNow('+7 days');
        $items = [];

        $add = static function ($key, $count, $link, $icon, $level = 'warning') use (&$items) {
            if ((int) $count > 0)
            {
                $items[] = (object) ['key' => $key, 'count' => (int) $count, 'link' => $link, 'icon' => $icon, 'level' => $level];
            }
        };

        // Payments still pending (Mollie status open/pending).
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ordercode)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('paid') . ' = 3');
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_PENDING', $db->loadResult(),
            'index.php?option=com_ticketstation&view=boxoffice&filter_ordering_paid=4', 'fa-clock');

        // Paid orders for upcoming tickets whose tickets were never e-mailed.
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT o.ordercode)')
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('o.ticketid') . ' = ' . $db->quoteName('t.ticketid'))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where($db->quoteName('o.pdfsent') . ' = 0')
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($now));
        $db->setQuery($query);
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_NOT_SENT', $db->loadResult(),
            'index.php?option=com_ticketstation&view=boxoffice&filter_ordering_paid=1', 'fa-envelope', 'danger');

        // Unfinished orders that will not be cleaned up automatically.
        if ($config->remove_unfinished != 1)
        {
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ordercode)')
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('paid') . ' = 0');
            $db->setQuery($query);
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_UNFINISHED', $db->loadResult(),
                'index.php?option=com_ticketstation&view=boxoffice', 'fa-shopping-cart', 'secondary');
        }

        // Confirmed waitinglist entries that have not been processed yet.
        if ($config->show_waitinglist == 1)
        {
            $query = $db->getQuery(true)
                ->select('COUNT(id)')
                ->from($db->quoteName('#__ticketstation_waitinglist'))
                ->where($db->quoteName('confirmed') . ' = 1')
                ->where($db->quoteName('processed') . ' = 0');
            $db->setQuery($query);
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_WAITINGLIST', $db->loadResult(),
                'index.php?option=com_ticketstation&view=waitinglist', 'fa-hourglass-half', 'secondary');
        }

        // Published coupons that expire within 7 days, or have used 90% or more of their limit.
        $query = $db->getQuery(true)
            ->select('COUNT(coupon_id)')
            ->from($db->quoteName('#__ticketstation_coupons'))
            ->where($db->quoteName('published') . ' = 1')
            ->where('((' . $db->quoteName('coupon_valid_to') . ' BETWEEN ' . $db->quote(substr($now, 0, 10)) . ' AND ' . $db->quote(substr($soon, 0, 10)) . ')'
                . ' OR (' . $db->quoteName('coupon_limit') . ' > 0 AND ' . $db->quoteName('coupon_used') . ' >= 0.9 * ' . $db->quoteName('coupon_limit') . '))');
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
}