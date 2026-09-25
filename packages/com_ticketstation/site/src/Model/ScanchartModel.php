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
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;

/**
 * Ticketstation Scanchart Model
 * @since 1.4.5
 */
class ScanchartModel extends BaseDatabaseModel
{
    function __construct()
    {
        parent::__construct();

        $jinput   = Factory::getApplication()->getInput();
        $this->id = $jinput->get('id', '0', 'int');

    }

    function getItems() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## The seats of this ticket, or - for a parent with Multi Seat = No - of all its child
        ## tickets. chart_ticketid is the ticket that owns the chart and its background image.
        $sql = 'SELECT t.*, e.*, ' . SeatplanSettings::COLUMNS . ', c.*, o.scanned,
                    IF(c.parent > 0, c.parent, c.ticketid) AS chart_ticketid
                FROM #__ticketstation_seatplancoords AS c
                INNER JOIN #__ticketstation_tickets AS t ON t.ticketid = c.ticketid
                INNER JOIN #__ticketstation_events AS e ON e.eventid = t.eventid'
            . SeatplanSettings::JOINS . '
                LEFT JOIN #__ticketstation_orders AS o ON o.seat_sector = c.id
                WHERE (c.ticketid = '.(int)$this->id.' OR c.parent = '.(int)$this->id.')';

        $db->setQuery($sql);
        $this->data = $db->loadObjectList();

        return $this->data;
    }

}