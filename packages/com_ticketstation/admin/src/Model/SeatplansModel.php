<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;

/**
 * The list of seat charts: parent tickets with a seat chart. The editor itself works through
 * the SeatplanLayout helper.
 * @since 0.0.1
 */
class SeatplansModel extends BaseDatabaseModel
{
    function __construct(){

        parent::__construct();

        $app 	= Factory::getApplication();

        // Get the pagination request variables
        $limit        = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
        $limitstart = $app->getUserStateFromRequest('limitstart', 'limitstart', 0, 'int');

        // In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination;
    }

    function getTotal() {

        if (empty($this->_total)) {

            $where = $this->_buildContentWhere();

            ## Making the query for showing all the clients in list function
            $query = 'SELECT a.*, b.eventname, b.eventcode
					  FROM #__ticketstation_tickets AS a, #__ticketstation_events AS b'
                .$where;
            $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_total;
    }

    function _buildContentWhere() {

        $app 	= Factory::getApplication();

        $filter_order = $app->getUserStateFromRequest( 'filter_ordering_t','filter_ordering_t','a.eventid','cmd' );

        $where = array();

        $where[] = 'a.eventid = b.eventid';
        $where[] = 'a.parent = 0';
        $where[] = 'a.show_seatplans = 1';


        if($filter_order == 0) {
            $where[] = 'a.eventid > 0';
        }else{
            $where[] = 'a.eventid = '.$filter_order;
        }


        $where 		= ( count( $where ) ? ' WHERE '. implode( ' AND ', $where ) : '' );

        return $where;
    }

    ## The charts, with their venue and how many seats each has (sold: sold or in a basket).
    function getList() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            $where = $this->_buildContentWhere();

            $sql = 'SELECT a.*, b.eventname, b.eventcode, v.venue AS venuename,
                        (SELECT COUNT(*) FROM #__ticketstation_seatplancoords AS c WHERE c.ticketid = a.ticketid OR c.parent = a.ticketid) AS seats,
                        (SELECT COUNT(*) FROM #__ticketstation_seatplancoords AS c WHERE (c.ticketid = a.ticketid OR c.parent = a.ticketid) AND NOT (c.orderid = 0 AND c.booked = c.blocked)) AS sold
					FROM #__ticketstation_tickets AS a
					LEFT JOIN #__ticketstation_venues AS v ON v.id = a.venue, #__ticketstation_events AS b'
                .$where.' ORDER BY a.ticketid';

            $db->setQuery($sql, $this->getState('limitstart'), $this->getState('limit' ));
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

}
