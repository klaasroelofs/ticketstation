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
            $db->quoteName('publish_date_time') . ' <= ' . $db->quote( Date::localNow() ),
            ## A ticket past its end is never put on sale again; unpublishTickets() would take it off again at once.
            'NOT ' . $this->endedSql($db)
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

        ## A ticket goes off sale at its sales stop, and at the latest when its end date and time has passed.
        $conditions = array(
            $db->quoteName('published') . ' = 1',
            '((' . $db->quoteName('use_sale_stop') . ' = 1 AND ' . $db->quoteName('sale_stop') . ' < ' . $db->quote( Date::localNow() ) . ')'
                . ' OR ' . $this->endedSql($db) . ')'
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

    /**
     * SQL condition for a ticket whose end date and time has passed (site-local time). An end that
     * was never filled in (stored as 1970-01-01 by older versions) doesn't count as passed, so
     * such a ticket is not taken off sale on the strength of a date nobody entered.
     */
    private function endedSql($db)
    {
        return '(' . $db->quoteName('enddate') . ' > ' . $db->quote('1970-01-02 00:00:00')
            . ' AND ' . $db->quoteName('enddate') . ' < ' . $db->quote( Date::localNow() ) . ')';
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