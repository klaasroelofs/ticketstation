<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;

/**
 * Ticketstation Seatplans Model
 * @since 0.0.1
 */
class SeatplansModel extends BaseDatabaseModel
{
    /**
     * @var int
     */
    protected $id;

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

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
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

    function getList() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            $where = $this->_buildContentWhere();

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT a.*, b.eventname, b.eventcode
					FROM #__ticketstation_tickets AS a, #__ticketstation_events AS b'
                .$where.' ORDER BY a.ticketid';

            $db->setQuery($sql, $this->getState('limitstart'), $this->getState('limit' ));
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function removeSeatsModel($cid = array()) {

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $query = 'DELETE FROM #__ticketstation_seatplancoords WHERE ticketid IN ( '.$cids.' )';

            ## Do the query now
            $this->_db->setQuery( $query );

            ## When query goes wrong.. Show message with error.
            if (!$this->_db->query()) {
                $this->setError($this->_db->getErrorMsg());
                return false;
            }
        }
        return true;
    }

    function setSeatsToFreeModel($cid = array()) {

        ## Count the cids
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $query = 'UPDATE #__ticketstation_seatplancoords SET booked = 0 WHERE ticketid IN ( '.$cids.' )';

            ## Do the query now
            $this->_db->setQuery( $query );

            ## When query goes wrong.. Show message with error.
            if (!$this->_db->query()) {
                $this->setError($this->_db->getErrorMsg());
                return false;
            }
        }
        return true;
    }

    function getSettings() {

        if (empty($this->_data)) {

            $db     = Factory::getContainer()->get('DatabaseDriver');
            $app    = Factory::getApplication();
            $filter_order = $app->getUserStateFromRequest( 'filter_ordering_t','filter_ordering_t','a.eventid','cmd' );

            if($filter_order == 0) {
                $where = 'AND a.eventid > 0';
            }else{
                $where = 'AND a.eventid = '.$filter_order;
            }

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT COUNT(tt.id) AS total 
					FROM #__ticketstation_seatplansettings AS tt, #__ticketstation_tickets AS t
					WHERE tt.ticketid = t.ticketid '. $where;

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getChilds() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            $where = $this->_buildChildsWhere();

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT a.*, b.eventname, b.eventcode
					FROM #__ticketstation_tickets AS a, #__ticketstation_events AS b'
                .$where.' ORDER BY a.ticketprice ASC';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function _buildChildsWhere() {

        $app = Factory::getApplication();

        $filter_order     = $app->getUserStateFromRequest( 'filter_ordering_t','filter_ordering_t','a.eventid','cmd' );

        $where = array();

        $where[] = 'a.eventid = b.eventid';
        $where[] = 'a.parent != 0';

        if($filter_order == 0) {
            $where[] = 'a.eventid > 0';
        }else{
            $where[] = 'a.eventid = '.$filter_order;
        }


        $where 		= ( count( $where ) ? ' WHERE '. implode( ' AND ', $where ) : '' );

        return $where;
    }

    function getPreferences() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = "SELECT *	
					FROM #__ticketstation_seatplansettings
					WHERE ticketid =".(int)$this->id."";

            $db->setQuery($sql);
            $item = $db->loadObject();

            if(!$item->ticketid) {

                $query = "INSERT INTO #__ticketstation_seatplansettings (id, ticketid, seat_width, seat_height) 
							VALUES ( '', ".$this->id.", 22, 22 )";

                $db->setQuery( $query );
                $db->query();

            }

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT ext.*, t.ticketname, t.parent	
					FROM #__ticketstation_seatplansettings AS ext, #__ticketstation_tickets AS t
					WHERE ext.ticketid ='.(int)$this->id.'
					AND ext.ticketid = t.ticketid';

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function getSeats() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT t.*, tt.*, c.*
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
            $sql = 'SELECT c.*, t.*, tt.background_color, o.scanned
					FROM (#__ticketstation_seatplancoords AS c,  #__ticketstation_tickets AS t, #__ticketstation_seatplansettings AS tt)
					LEFT JOIN #__ticketstation_orders as o
					ON (c.id = o.seat_sector)
					WHERE c.ticketid = t.ticketid
					AND c.ticketid = tt.ticketid
					AND c.ticketid = '.(int)$this->id.'';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getTotals() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT c . * , COUNT( c.ticketid ) AS total, COUNT(booked) as totalbooked
					FROM #__ticketstation_seatplancoords AS c, #__ticketstation_tickets AS t
					WHERE c.ticketid = t.ticketid
					GROUP BY c.ticketid';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getTotalbooked() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT ticketid, COUNT(booked) as totalbooked
					FROM #__ticketstation_seatplancoords
					WHERE booked = 1
					GROUP BY ticketid';

            $db->setQuery($sql);
            $this->data = $db->loadObjectList();
        }
        return $this->data;
    }

    function getData() {

        if (empty($this->_data)) {

            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Making the query for showing all the clients in list function
            $sql = 'SELECT ss.*, t.*, e.* 
					FROM #__ticketstation_seatplansettings AS ss, #__ticketstation_tickets AS t, #__ticketstation_events AS e
					WHERE ss.ticketid ='.(int)$this->id.'
					AND ss.ticketid = t.ticketid
					AND t.eventid = e.eventid';

            $db->setQuery($sql);
            $this->data = $db->loadObject();
        }
        return $this->data;
    }

    function store($data) {

        $table = $this->getTable('seatplansettings');

        ## Bind the form fields to the web link table
        if (!$table->bind($data)) {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        ## Make sure the web link table is valid
        if (!$table->check()) {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        ## Store the web link table to the database
        if (!$table->store()) {
            $this->setError($this->_db->getErrorMsg());
            return false;
        }

        return true;
    }

    function saveSeat($data) {

        $table = $this->getTable('seatplancoords');

        $message = array();
        $message['succes'] = false;

        ## Bind the form fields to the table
        if (!$table->bind($data)) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        ## Make sure the table is valid
        if (!$table->check()) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        ## Store the table to the database
        if (!$table->store()) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Making the query for showing all the clients in list function
        $sql = 'SELECT *
				FROM #__ticketstation_seatplancoords
				WHERE id ='.(int)$data['id'].'';

        $db->setQuery($sql);
        $data = $db->loadObject();

        $message['succes'] = true;

        if($data->parent != 0){
            $message['return'] = $data->parent;
        }else{
            $message['return'] = $data->ticketid;
        }

        return $message;
    }

    function saveSeatBatch($data) {

        $table = $this->getTable('seatplancoords');

        $message = array();
        $message['succes'] = false;

        ## Bind the form fields to the web link table
        if (!$table->bind($data)) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        ## Make sure the web link table is valid
        if (!$table->check()) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        ## Store the web link table to the database
        if (!$table->store()) {
            //$this->setError($this->_db->getErrorMsg());
            return $message;
        }

        $message['succes'] = true;

        $id = $this->_db->insertid();

        return $id;
    }

    function seatBatch($start_seat, $seats_to_add, $pixels, $direction, $up_down, $seat_counter){

        $db = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT *
				FROM #__ticketstation_seatplancoords
				WHERE id ='.(int)$start_seat.'';

        $db->setQuery($sql);
        $data = $db->loadObject();

        ## Setting some global seat information:
        $seatid 	= $data->seatid;
        $ticketid 	= $data->ticketid;
        $row_name 	= $data->row_name;
        $parent 	= $data->parent;
        $width 		= $data->width;
        $height 	= $data->height;
        $x_pos		= $data->x_pos;
        $y_pos		= $data->y_pos;
        $type		= $data->type;

        $json_object = array();
        $seatnumber = $seatid;

        ## Add batch from left to right in the screen:
        for ($i = 0, $n = $seats_to_add; $i < $n; $i++ ){

            $batch=array();

            if($direction==0){

                ## Create new seat number:
                if($up_down == 'up'){
                    $batch['seatid'] = $seatnumber+$seat_counter;
                    $seatnumber = $seatnumber+$seat_counter;
                }else{
                    $batch['seatid'] = $seatnumber-$seat_counter;
                    $seatnumber = $seatnumber-$seat_counter;
                }

                ## Move the seat seat_width+$pixels to the right:
                $batch['x_pos'] 	= $x_pos+($width+$pixels);
                ## Position for height can be the same:
                $batch['y_pos'] 	= $y_pos;
                ## New x position needs to be set:
                $x_pos = $x_pos+($width+$pixels);

            }else if($direction==1){

                ## Create new seat number:
                if($up_down == 'up'){
                    $batch['seatid'] = $seatnumber+$seat_counter;
                    $seatnumber = $seatnumber+$seat_counter;
                }else{
                    $batch['seatid'] = $seatnumber-$seat_counter;
                    $seatnumber = $seatnumber-$seat_counter;
                }

                ## Move the seat seat_width+$pixels to the right:
                $batch['x_pos'] 	= $x_pos-($width+$pixels);
                ## Position for height can be the same:
                $batch['y_pos'] 	= $y_pos;
                ## New x position needs to be set:
                $x_pos = $x_pos-($width+$pixels);

            }else if($direction==2){

                ## Create new seat number:
                if($up_down == 'up'){
                    $batch['seatid'] = $seatnumber+$seat_counter;
                    $seatnumber = $seatnumber+$seat_counter;
                }else{
                    $batch['seatid'] = $seatnumber-$seat_counter;
                    $seatnumber = $seatnumber-$seat_counter;
                }

                ## Move the seat seat_width+$pixels to the right:
                $batch['x_pos'] 	= $x_pos;
                ## Position for height can be the same:
                $batch['y_pos'] 	= $y_pos+($height+$pixels);
                ## New x position needs to be set:
                $y_pos = $y_pos+($height+$pixels);


            }else if($direction==3){

                ## Create new seat number:
                if($up_down == 'up'){
                    $batch['seatid'] = $seatnumber+$seat_counter;
                    $seatnumber = $seatnumber+$seat_counter;
                }else{
                    $batch['seatid'] = $seatnumber-$seat_counter;
                    $seatnumber = $seatnumber-$seat_counter;
                }

                ## Move the seat seat_width+$pixels to the right:
                $batch['x_pos'] 	= $x_pos;
                ## Position for height can be the same:
                $batch['y_pos'] 	= $y_pos-($height+$pixels);
                ## New x position needs to be set:
                $y_pos = $y_pos-($height+$pixels);


            }

            ## Ticket id can be the same:
            $batch['ticketid'] 	= $ticketid;
            ## Ticket id can be the same:
            $batch['parent'] 	= $parent;
            ## Row name can be the same:
            $batch['row_name'] 	= $row_name;
            ## Height of the seat:
            $batch['height'] 	= $height;
            ## Height of the seat:
            $batch['width'] 	= $width;
            ## type of the seat:
            $batch['type'] 		= $type;

            ## Prepared all new seat information:
            $new_database_id = self::saveSeatBatch($batch);

            $batch['added_database_id'] = $new_database_id;

            ## Push data into json object array
            array_push($json_object, $batch);

            ## unset the seat data:
            unset($batch);

        }

        $seats = json_encode($json_object);

        return $seats;
    }

    function copyFromSource($sourceid, $targetid){

        $db = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT *
				FROM #__ticketstation_seatplancoords
				WHERE ticketid ='.(int)$sourceid.'';

        $db->setQuery($sql);
        $data = $db->loadObjectList();

        $json_object = array();

        for ($i = 0, $n = count($data); $i < $n; $i++ ){

            $insert = array();
            $row    = $data[$i];

            $insert['x_pos'] = $row->x_pos;
            $insert['y_pos'] = $row->y_pos;
            $insert['ticketid'] = $targetid;
            $insert['seatid'] = $row->seatid;
            $insert['type'] = $row->type;
            $insert['width'] = $row->width;
            $insert['height'] = $row->height;
            $insert['row_name'] = $row->row_name;

            ## Prepared all new seat information:
            $new_database_id = self::saveSeatBatch($insert);

            $insert['added_database_id'] = $new_database_id;

            ## Push data into json object array
            array_push($json_object, $insert);

            ## unset the seat data:
            unset($insert);

        }

        return json_encode($json_object);

    }

}