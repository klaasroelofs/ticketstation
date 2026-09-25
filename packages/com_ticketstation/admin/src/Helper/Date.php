<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;


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
        $user   = Factory::getUser();

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

    public static function getDateFormat()
    {
        $config = new Config;
        $config= $config->getPartialConfig(['dateformat', 'time_format']);

        return $config->dateformat;
    }
}