<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;

/**
 * Ticketstation Ticketscanner Model
 * @since 0.3.0
 */
class TicketscannerModel extends BaseDatabaseModel
{
    protected $eventid;
    protected $ticketid;

    function __construct($config = [], $factory = null)
    {
        parent::__construct($config, $factory);

        $jinput   = Factory::getApplication()->getInput();
        $this->eventid = $jinput->get('eventid', '0', 'int');
        $this->ticketid = $jinput->get('ticketid', '0', 'int');
    }

    function getEventdata()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_events'));
        $query->where($db->quoteName('eventid') . ' = '. $db->quote((int) $this->eventid));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function getTicketdata()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(['t.*', 'e.*']);
        $query->from($db->quoteName('#__ticketstation_tickets', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('t.eventid') . ' = ' . $db->quoteName('e.eventid'));
        $query->where($db->quoteName('t.ticketid') . ' = '. $db->quote((int) $this->ticketid));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function getSoldbyEvent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(orderid)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('paid') . ' = ' . $db->quote(1))
            ->where($db->quoteName('eventid') . ' = '. $db->quote((int) $this->eventid));

        $db->setQuery($query);

        return $db->loadResult();
    }

    function getSoldbyticket()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(orderid)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('paid') . ' = ' . $db->quote(1))
            ->where($db->quoteName('ticketid') . ' = '. $db->quote((int) $this->ticketid));

        $db->setQuery($query);

        return $db->loadResult();
    }

    /**
     * Scanner assignment of the current user (null when the user isn't a scanner).
     *
     * @return object|null
     *
     * @since 2.2.1
     */
    function getScanner()
    {
        return Scanner::getByUserId((int) $this->getCurrentUser()->id);
    }

}
