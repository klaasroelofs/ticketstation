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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Updater\Updater;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Availability;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Coupon;
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

    /**
     * The newer package version that Joomla's update system offers, or null when there is none.
     *
     * Uses Joomla's own update check for pkg_ticketstation, with the cache time and minimum
     * stability from the Joomla Update settings (com_installer), so the answer matches
     * System → Update → Extensions. The update server is only contacted when the last
     * check is older than that cache time.
     *
     * @return  string|null
     */
    function getAvailableUpdate()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_ticketstation'));

        $db->setQuery($query);
        $package = $db->loadObject();

        if (!$package)
        {
            return null;
        }

        $params = ComponentHelper::getParams('com_installer');

        try
        {
            Updater::getInstance()->findUpdates(
                (int) $package->extension_id,
                3600 * (int) $params->get('cachetimeout', 6),
                (int) $params->get('minimum_stability', Updater::STABILITY_STABLE)
            );
        }
        catch (\Throwable $e)
        {
            // Update server unreachable: fall back to what an earlier check stored.
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('version'))
            ->from($db->quoteName('#__updates'))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $package->extension_id);

        $db->setQuery($query);
        $versions = $db->loadColumn();

        $installed = json_decode((string) $package->manifest_cache, true)['version'] ?? '0';
        $newest    = null;

        foreach ($versions as $version)
        {
            if (version_compare($version, $newest ?? $installed, '>'))
            {
                $newest = $version;
            }
        }

        return $newest;
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
     * Availability of the upcoming published tickets, computed by the Availability helper
     * exactly like the storefront does, so "left" matches the site.
     *
     * @return  array
     */
    function getAvailability($limit = 10)
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
            ->where($db->quoteName('t.startdate') . ' >= ' . $db->quote($this->getLocalNow()))
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
    function getAttention($config, $mollie)
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

        // Mollie configuration: bypass mode, test mode and the live API key each get their own, specific warning.
        $mollieLink = 'index.php?option=com_ticketstation&view=mollie';

        $add('COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_BYPASS', $mollie->bypass_mode == '1' ? 1 : 0,
            $mollieLink, 'fa-exclamation-circle', 'danger');
        $add('COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_TEST', $mollie->test_mode == '1' ? 1 : 0,
            $mollieLink, 'fa-exclamation-circle', 'danger');

        if ($mollie->api_key == '')
        {
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_MISSING', 1, $mollieLink, 'fa-exclamation-circle', 'danger');
        }
        elseif (substr($mollie->api_key, 0, 5) === 'test_')
        {
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_IS_TEST', 1, $mollieLink, 'fa-exclamation-circle', 'danger');
        }
        elseif (substr($mollie->api_key, 0, 5) !== 'live_')
        {
            $add('COM_TICKETSTATION_CPANEL_ATTENTION_MOLLIE_KEY_INVALID', 1, $mollieLink, 'fa-exclamation-circle', 'danger');
        }

        // Company details and mail sender, both on the Company tab of the Configuration.
        $companyLink = 'index.php?option=com_ticketstation&view=configuration#company';

        $add('COM_TICKETSTATION_CPANEL_ATTENTION_COMPANY_INCOMPLETE', $this->isCompanyIncomplete($config) ? 1 : 0,
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
     * Whether the company details still lack what invoices and mails need: an empty name, address,
     * postcode, city or email, or example data that a fresh install seeds (install.mysql.utf8.sql).
     *
     * @return  bool
     */
    private function isCompanyIncomplete($config)
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
}