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
 * Ticketstation Seatedevent Model
 * @since 0.2.10
 */
class SeatedeventModel extends BaseDatabaseModel {

    function __construct(){
        parent::__construct();

        $jinput   = Factory::getApplication()->getInput();
        $this->ticketid = $jinput->get('cid', '0', 'int');
        $this->id       = $jinput->get('cid', array(0), '', 'array');

        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();
        $this->ordercode = $session->get('ordercode');

    }

    function getConfig() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $query = 'SELECT priceformat, valuta, show_venue, show_venue_address, show_venue_description, show_venue_website FROM #__ticketstation_config WHERE configid = 1';

            $db->setQuery($query);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getTicketdetails()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 'b.eventname', 'b.eventdescription', 'b.eventdate', 'b.closingdate', 'v.*', 'v.id AS vid', 'a.published'])
            ->from($db->quoteName('#__ticketstation_tickets', 'a'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'b') . ' ON ' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('b.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('a.venue') . ' = ' . $db->quoteName('v.id'))
            ->where($db->quoteName('a.published') . " = 1")
            ->where($db->quoteName('b.published') . " = 1")
            ->where($db->quoteName('a.ticketid') . " = " . (int) $this->ticketid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    function getTickets() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            ##$query = 'SELECT *
			##		  FROM #__ticketstation_orders
			##		  WHERE ordercode = '.$this->ordercode.'
			##		  GROUP BY eventid';

            $query = $db->getQuery(true)
                ->select(['*'])
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('ordercode') . ' = '. $db->quote($this->ordercode))
                ->group($db->quoteName('eventid'));

            $db->setQuery($query);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }


    function getSeats() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## All seats of this chart: free seats (ticketid = this ticket) and section seats
            ## (parent = this ticket), with the name and price of the ticket they sell. The chart settings come from this ticket; a section's
            ## own settings row only overrides the colours.
            $sql = 'SELECT c.*, t.ticketname, t.ticketprice, ' . SeatplanSettings::COLUMNS . '
					FROM #__ticketstation_seatplancoords AS c
					INNER JOIN #__ticketstation_tickets AS t ON t.ticketid = c.ticketid'
                . SeatplanSettings::JOINS . '
					WHERE (c.ticketid = '.(int)$this->ticketid.' OR c.parent = '.(int)$this->ticketid.')';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getData() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT * 
					FROM #__ticketstation_seatplansettings
					WHERE ticketid ='.(int)$this->id.'
					ORDER BY id';

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getCartItems() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## The seats already picked in this order, with the colours they have on the chart and
            ## the chart they are on (owner: the parent ticket of a section seat), so the page can
            ## tell this chart's seats from those on other charts.
            $sql='SELECT a.seat_sector, c.seatid, c.row_name, IF(c.parent > 0, c.parent, c.ticketid) AS owner,
                    o.ticketname AS owner_ticketname, e.eventname, o.startdate AS owner_startdate, ' . SeatplanSettings::COLUMNS . '
			      FROM #__ticketstation_orders AS a
			      INNER JOIN #__ticketstation_seatplancoords AS c ON c.orderid = a.orderid
			      LEFT JOIN #__ticketstation_tickets AS o ON o.ticketid = IF(c.parent > 0, c.parent, c.ticketid)
			      LEFT JOIN #__ticketstation_events AS e ON e.eventid = a.eventid'
                . SeatplanSettings::JOINS . '
				  WHERE a.ordercode = '.(int)$this->ordercode . '
				  ORDER BY a.orderid';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }
}