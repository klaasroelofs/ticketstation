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
}