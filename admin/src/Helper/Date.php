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
    public static function _($date = null, $format = '')
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

        return $date->format($format, true, false);
    }

    public static function getDateFormat()
    {
        $config = new Config;
        $config= $config->getPartialConfig(['dateformat', 'time_format']);

        return $config->dateformat;
    }
}