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

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


## no direct access
defined('_JEXEC') or die('Restricted access');

    class Ticketstarter
{
    function publishTickets()
    {
        $now = Factory::getDate();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('published') . ' = 1'
        );

        $conditions = array(
            $db->quoteName('use_auto_publish') . ' = 1',
            $db->quoteName('published') . ' = 0',
            $db->quoteName('publish_date_time') . ' <= ' . $db->quote( Date::localNow() )
        );

        $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        ## When query goes wrong.. Show message with error.
        if (!$result)
        {
            return false;
        }

        return true;
    }

    ## Function to unpublish tickets:
    function unpublishTickets()
    {
        ## Getting the current date (NOW)
        $now = Factory::getDate();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('published') . ' = 0'
        );

        $conditions = array(
            $db->quoteName('use_sale_stop') . ' = 1',
            $db->quoteName('sale_stop') . ' < ' . $db->quote( Date::localNow() )
        );

        $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        ## When query goes wrong.. Show message with error.
        if (!$result)
        {
            return false;
        }

        return true;
    }

    function unpublishEvent()
    {
        ## Getting the current date (NOW)
        $now = Factory::getDate();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('published') . ' = 0'
        );

        $conditions = array(
            $db->quoteName('automatic_change_state') . ' = 1',
            $db->quoteName('closingdate') . ' < ' . $db->quote( Date::localNow() )
        );

        $query->update($db->quoteName('#__ticketstation_events'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        ## When query goes wrong.. Show message with error.
        if (!$result)
        {
            return false;
        }

        return true;
    }

    function publishEvent()
    {
        $now = Factory::getDate();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('published') . ' = 1'
        );

        $conditions = array(
            $db->quoteName('automatic_change_state') . ' = 1',
            $db->quoteName('startdate') . ' <= ' . $db->quote( Date::localNow() )
        );

        $query->update($db->quoteName('#__ticketstation_events'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $result = $db->execute();

        ## When query goes wrong.. Show message with error.
        if (!$result)
        {
            return false;
        }

        return true;
    }
}