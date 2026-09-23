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
            $query = 'SELECT priceformat, valuta, show_venue FROM #__ticketstation_config WHERE configid = 1';

            $db->setQuery($query);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getTicketdetails()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['a.*', 'b.eventname', 'b.eventdate', 'b.closingdate', 'v.*', 'v.id AS vid', 'a.published'])
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

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT c.*, t.ticketname, tt.background_color, tt.font_color, tt.border_color
					FROM #__ticketstation_seatplancoords AS c,  #__ticketstation_tickets AS t, #__ticketstation_seatplansettings AS tt
					WHERE c.parent ='.(int)$this->id.'
					AND c.ticketid = t.ticketid
					AND c.ticketid = tt.ticketid';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getNochilds() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT t.ticketname, tt.background_color, c.*, tt.font_color, tt.border_color
					FROM #__ticketstation_seatplancoords AS c,  #__ticketstation_tickets AS t, #__ticketstation_seatplansettings AS tt
					WHERE c.ticketid = t.ticketid
					AND c.ticketid = tt.ticketid
					AND c.ticketid = '.(int)$this->id.'';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getCheckChilds() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## This query checks if multi ticket has childs or not?
            $sql = 'SELECT COUNT(ticketid) AS total
					FROM #__ticketstation_tickets
					WHERE parent = '.(int)$this->id.'';

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getData() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT * 
					FROM #__ticketstation_seatplansettings
					WHERE ticketid ='.(int)$this->id.'';

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function _buildContentWhereTicket() {

        $where = array();

        $where[] = 'a.eventid = e.eventid';
        $where[] = 'a.ticketid = t.ticketid';
        $where[] = 'a.ticketid = tt.ticketid';
        $where[] = 'a.ordercode = '.(int)$this->ordercode;


        $where 		= ( count( $where ) ? ' WHERE '. implode( ' AND ', $where ) : '' );

        return $where;
    }

    function getCart() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');
            $where = $this->_buildContentWhereTicket();

            ## Making the query for showing all the clients in list function
            $sql='SELECT a.*, t.ticketname, t.ticketprice, t.startdate, e.eventname, tt.*, COUNT(a.orderid) AS total
			      FROM #__ticketstation_orders AS a, #__ticketstation_events AS e, #__ticketstation_tickets AS t, #__ticketstation_seatplansettings AS tt'
                .$where.' GROUP BY a.ticketid' ;

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getCartNoChilds() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');
            $where = $this->_buildContentWhereTicket();

            ## Making the query for showing all the clients in list function
            $sql='SELECT a.*,COUNT(a.orderid) AS total, t.ticketname 
				  FROM #__ticketstation_orders AS a, #__ticketstation_tickets AS t
				  WHERE a.ordercode = '.(int)$this->ordercode.'
				  AND a.ticketid = t.ticketid
				  AND t.parent = '.(int)$this->id.'
				  GROUP BY ticketid';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getCartItems() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql='SELECT *
			      FROM #__ticketstation_orders AS a, #__ticketstation_seatplancoords AS c, #__ticketstation_seatplansettings AS tt
				  WHERE a.orderid = c.orderid
				  AND c.ticketid = tt.ticketid
				  AND a.ordercode = '.(int)$this->ordercode;

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }
}