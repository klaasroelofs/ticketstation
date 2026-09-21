<?php

namespace Ticketstation\Component\Ticketstation\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseQuery;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

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

        ## Making the query for showing all the clients in list function
        $sql = 'SELECT c.*, t.*, e.*, tt.background_color, o.scanned
                FROM (#__ticketstation_seatplancoords AS c,  #__ticketstation_tickets AS t, #__ticketstation_seatplansettings AS tt, #__ticketstation_events AS e)
                LEFT JOIN #__ticketstation_orders as o
                ON (c.id = o.seat_sector)
                WHERE c.ticketid = t.ticketid
                AND c.ticketid = tt.ticketid
                AND t.eventid = e.eventid
                AND c.ticketid = '.(int)$this->id.'';

        $db->setQuery($sql);
        $this->data = $db->loadObjectList();

        return $this->data;
    }

}