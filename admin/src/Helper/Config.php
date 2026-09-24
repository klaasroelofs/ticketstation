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
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

class Config
{
    public function get($request = '*')
    {
        if (is_array($request))
        {
            return $this->getPartialConfig($request);
        }

        return $this->getFullConfig();
    }

    private function getFullConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function getPartialConfig($request)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($request)
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . " = 1");

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * The address customers are pointed to when something needs checking: the company email
     * (Configuration > Company), else the sender address, else Joomla's global sender address.
     *
     * @return  string
     */
    public function getContactEmail()
    {
        $config = $this->getPartialConfig(['email', 'from_email']);

        foreach ([$config->email ?? '', $config->from_email ?? ''] as $email)
        {
            if (trim($email) !== '')
            {
                return trim($email);
            }
        }

        return (string) Factory::getApplication()->get('mailfrom');
    }

    /**
     * Makes a link from the configuration usable on every page: a full URL (https://...) or a
     * root-relative path (/...) is kept as is, anything else is taken relative to the site root.
     *
     * @param   string  $link
     *
     * @return  string  Empty when no link has been configured
     */
    public static function toAbsoluteLink($link)
    {
        $link = trim((string) $link);

        if ($link === '' || preg_match('#^(https?:)?//#i', $link) || $link[0] === '/')
        {
            return $link;
        }

        return Uri::root() . $link;
    }
}