<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;


defined('_JEXEC') or die('Restricted access');

class Date
{
    /**
     * A UTC date in the user's (or site's) timezone. Day and month names (l, D, F, M) stay
     * English unless $translate is true.
     */
    public static function _($date = null, $format = '', $translate = false)
    {
        if ($date == '0000-00-00')
        {
            return '';
        }

        if ( ! $date || $date == 'NOW' || $date == 'now')
        {
            return Factory::getDate()->toSql();
        }

        $format = $format ?: self::getDateFormat();

        $config = Factory::getConfig();
        $user   = Factory::getApplication()->getIdentity();

        // Get a date object based on the correct timezone.
        $date = Factory::getDate($date, 'UTC');
        $date->setTimezone(new \DateTimeZone($user->getParam('timezone', $config->get('offset'))));

        return $date->format($format, true, $translate);
    }

    /**
     * "Now" in the site's timezone (Global Configuration > Website Time Zone).
     *
     * Ticket and event dates (startdate, enddate, publish_date_time, sale_stop, eventdate,
     * closingdate, coupon_valid_to) are stored exactly as entered in the forms, i.e. in the
     * site's local time - unlike orderdate/invoicedate, which are stored in UTC via date().
     * Compare those local dates against this, never against plain date() (UTC on the server).
     *
     * @param   string       $format  date() format of the result
     * @param   string|null  $modify  optional relative modifier, e.g. '+7 days'
     *
     * @return  string
     */
    public static function localNow($format = 'Y-m-d H:i:s', $modify = null)
    {
        $offset = Factory::getApplication()->get('offset') ?: 'UTC';
        $now    = new \DateTime('now', new \DateTimeZone($offset));

        if ($modify)
        {
            $now->modify($modify);
        }

        return $now->format($format);
    }

    /**
     * A ticket or event date (stored in site-local time) written out in the active site
     * language, e.g. "zaterdag 3 oktober 2026" / "Saturday 3 October 2026", optionally
     * followed by the time ("zaterdag 3 oktober 2026, 20:00 uur").
     *
     * @param   string   $localDate  date as stored, e.g. '2026-10-03 20:00:00'
     * @param   boolean  $withTime   append the time
     *
     * @return  string
     */
    public static function long($localDate, $withTime = false)
    {
        if (!$localDate || str_starts_with((string) $localDate, '0000-00-00')) {
            return '';
        }

        // Stored and formatted in the same (PHP) timezone, so the date is never shifted.
        $timestamp = strtotime($localDate);
        $formatter = datefmt_create(
            str_replace('-', '_', Factory::getApplication()->getLanguage()->getTag()),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            date_default_timezone_get(),
            \IntlDateFormatter::GREGORIAN,
            'EEEE d MMMM yyyy'
        );

        $day = datefmt_format($formatter, $timestamp);

        return $withTime ? Text::sprintf('COM_TICKETSTATION_DATE_AT_TIME', $day, date('H:i', $timestamp)) : $day;
    }

    /**
     * A time typed by an admin ("20:00", "20.00", "2000", "8:30") as "HH:MM", or '' when empty
     * or not a valid time.
     */
    public static function normalizeTime($value): string
    {
        $value = trim((string) $value);

        if (!preg_match('/^(\d{1,2})(?:[:.]?(\d{2}))?(?::\d{2})?$/', $value, $match)) {
            return '';
        }

        $hours   = (int) $match[1];
        $minutes = (int) ($match[2] ?? 0);

        if ($hours > 23 || $minutes > 59) {
            return '';
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * The moment the doors open, in the same site-local format as the start date: the time of
     * "Doors open" on the date of the ticket. Doors that would open after the start (a late show
     * starting just after midnight) open the evening before. '' without a valid doors time.
     *
     * @param   string  $startdate  date as stored, e.g. '2026-10-03 20:00:00'
     * @param   string  $doors      time as stored, e.g. '19:00'
     */
    public static function doorsOpen($startdate, $doors): string
    {
        $doors = self::normalizeTime($doors);
        $start = $startdate && !str_starts_with((string) $startdate, '0000-00-00') ? strtotime((string) $startdate) : false;

        if ($doors === '' || $start === false) {
            return '';
        }

        $opens = strtotime(date('Y-m-d', $start) . ' ' . $doors . ':00');

        if ($opens > $start) {
            $opens = strtotime('-1 day', $opens);
        }

        return date('Y-m-d H:i:s', $opens);
    }

    public static function getDateFormat()
    {
        $config = new Config;
        $config= $config->getPartialConfig(['dateformat', 'time_format']);

        return $config->dateformat;
    }
}