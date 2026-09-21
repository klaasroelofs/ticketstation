<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


class SeatplansController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Seatplans';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'seatplans');
        parent::display();
    }

    function displaychart($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'chart');
        $jinput->set('view', 'seatplans');
        parent::display();
    }

    function editsettings($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'seatplansettings');
        parent::display();
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    public function tickets($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=tickets');
    }

    function saveRecord() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        //decode JSON data received from AJAX POST request
        $data = json_decode($_POST["data"]);

        foreach($data->coords as $item) {

            ##Extract X number for panel
            $x_coord = preg_replace('/[^\d\s]/', '', $item->coordTop);
            ##Extract Y number for panel
            $y_coord = preg_replace('/[^\d\s]/', '', $item->coordLeft);

            ## Insert PDF into DB
            $db     = Factory::getContainer()->get('DatabaseDriver');
            $sql = 'UPDATE #__ticketstation_seatplancoords SET x_pos = "'.$x_coord.'", y_pos = "'.$y_coord.'" WHERE id = "'.$item->coordId.'" ';

            $db->setQuery($sql);

            if (!$db->execute() ){
                echo "failed";
            }

        }

        echo "success";
    }

    function loadSeat(){

        $seatid = Factory::getApplication()->getInput()->get('seatid', 0);

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT *
				FROM #__ticketstation_seatplancoords
				WHERE id ='.(int)$seatid.'';

        $db->setQuery($sql);
        $data = $db->loadObject();

        $booking_state = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_FREE' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_TAKEN' )),
        );

        $lists['state'] = HTMLHelper::_('select.genericList', $booking_state, 'booked', ' class="form-select" '. '', 'value', 'text', $data->booked );

        echo '<h3 id="editSeatChanger">'.Text::_( 'COM_TICKETSTATION_EDIT_SEATNUMBER' ).' '.$seatid.'</h3>';
        echo '<form class="form" action = "index.php" method="POST" name="adminForm" id="adminForm1">';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="seatid">'.Text::_( 'COM_TICKETSTATION_NEW_SEATID' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="seatid" name="seatid" placeholder="Seat Number" value="'.$data->seatid.'">';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="row_name">'.Text::_( 'COM_TICKETSTATION_NEW_ROW_NAME' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="row_name" name="row_name" placeholder="Row Name"value="'.$data->row_name.'">';
        echo '    </div>';
        echo '  </div>';

        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="x_pos">'.Text::_( 'COM_TICKETSTATION_X_POS_NEW' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="x_pos" name="x_pos" placeholder="" value="'.$data->x_pos.'">';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="y_pos">'.Text::_( 'COM_TICKETSTATION_Y_POS_NEW' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="y_pos" name="y_pos" placeholder="" value="'.$data->y_pos.'">';
        echo '    </div>';
        echo '  </div>';

        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="booked">'.Text::_( 'COM_TICKETSTATION_BOOKING_STATUS' ).'</label>';
        echo '    <div class="controls">';
        echo 		 $lists['state'];
        echo '    </div>';
        echo '  </div>';

        echo '  <button type="submit" class="btn btn-primary">'.Text::_( 'COM_TICKETSTATION_SAVE_CHANGES' ).'</button>';

        echo '  <input type="hidden" name="id" value="'.(int)$seatid.'" />';
        echo '  <input type="hidden" name="ticketid" value="'.(int)$data->ticketid.'" />';
        echo '  <input type="hidden" name="option" value="com_ticketstation" />';
        echo '  <input type="hidden" name="task" value="saveSeatChanges" />';
        echo '  <input type="hidden" name="controller" value="seatplans" />';
        echo '  ' . HTMLHelper::_('form.token');

        echo '</form>';

        echo '<div style="clear:both;"></div>';

    }

    function saveSeatChanges() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        //$post = Factory::getApplication()->getInput()->get('post');
        $post   	= Factory::getApplication()->getInput()->post->getArray();

        $model	= $this->getModel('seatplans');

        if($post['booked'] == 0){
            $post['orderid'] = 0;
        }

        $message = $model->saveSeat($post);

        if($message['succes'] == true){
            $link = 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid='.$message['return'];
            $this->setRedirect($link);
        }else{
            $link = 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid='.$post['ticketid'];
            $this->setRedirect($link);
        }

    }

    ## adding a seat which is not a multiseat.
    function newSeat() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ticketid = Factory::getApplication()->getInput()->get('ticketid', 0);

        $db     = Factory::getContainer()->get('DatabaseDriver');

        ## Load the dimensions of this seat
        $sql = 'SELECT * FROM #__ticketstation_seatplansettings
				WHERE ticketid = '.(int)$ticketid.'';

        $db->setQuery($sql);
        $seat = $db->loadObject();

        ## Loading the latest coord.
        $sql = 'SELECT * FROM #__ticketstation_seatplancoords
				WHERE ticketid = '.(int)$ticketid.'
				ORDER BY seatid DESC LIMIT 0,1';

        $db->setQuery($sql);
        $item = $db->loadObject();

        ## selecting the parent.
        $sql = 'SELECT parent FROM #__ticketstation_tickets
				WHERE ticketid = '.(int)$ticketid.'';

        $db->setQuery($sql);
        $result = $db->loadObject();

        if(empty($item)){

            $query = "INSERT INTO #__ticketstation_seatplancoords (orderid, x_pos, y_pos, ticketid, seatid, booked, type, parent, width, height)
					  VALUES (0, 10, 10, ".$ticketid.", 1, 0, ".$seat->type.", ".$result->parent.", ".$seat->seat_width.", ".$seat->seat_height." )";

            $db->setQuery( $query );
            $db->execute();

            $dataid = $db->insertid();
            $seatid = 1;


        }else{

            $seatid = $item->seatid+1;

            $query = "INSERT INTO #__ticketstation_seatplancoords (orderid, x_pos, y_pos, ticketid, seatid, booked, type, parent, width, height)
						VALUES (0, 10, 10, ".$ticketid.", ".(int)$seatid.", 0, ".$seat->type.", ".$result->parent.", ".$seat->seat_width.", ".$seat->seat_height.")";

            $db->setQuery( $query );
            $db->execute();

            $dataid = $db->insertid();

        }

        $arr = array('seatid' => $seatid, 'id' => $dataid, 'seat_width' => $seat->seat_width, 'seat_height' => $seat->seat_height);
        echo json_encode($arr);

    }

    ## adding multi ticket seats.
    function getRecord() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $input      = Factory::getApplication()->getInput();
        $ticketid   = $input->get('ticketid', 0);
        $row_name   = $input->get('row_name', 0);
        $new_seat   = $input->get('new_seat', 0);

        $db     = Factory::getContainer()->get('DatabaseDriver');

        ## Load the dimensions of this seat
        $sql = 'SELECT * FROM #__ticketstation_seatplansettings 
				WHERE ticketid = '.(int)$ticketid.'';

        $db->setQuery($sql);
        $seat = $db->loadObject();

        if($row_name){

            ## Loading the latest coord.
            $sql = 'SELECT * FROM #__ticketstation_seatplancoords
					WHERE ticketid = '.(int)$ticketid.'
					AND row_name = '.$db->quote($row_name).'
					ORDER BY seatid DESC LIMIT 0,1';

            $db->setQuery($sql);
            $item = $db->loadObject();

        }else{

            ## Loading the latest coord.
            $sql = 'SELECT * FROM #__ticketstation_seatplancoords 
					WHERE ticketid = '.(int)$ticketid.' 
					ORDER BY seatid DESC LIMIT 0,1';

            $db->setQuery($sql);
            $item = $db->loadObject();

        }

        ## selecting the parent.
        $sql = 'SELECT parent FROM #__ticketstation_tickets 
				WHERE ticketid = '.(int)$ticketid.'';

        $db->setQuery($sql);
        $result = $db->loadObject();

        if($new_seat == 1){

            $query = "INSERT INTO #__ticketstation_seatplancoords (orderid, x_pos, y_pos, ticketid, seatid, booked, type, parent, width, height, row_name)
						VALUES (0, 10, 10, ".$ticketid.", 1, 0, ".$seat->type.", ".$result->parent.", ".$seat->seat_width.", ".$seat->seat_height.", ".$db->quote($row_name)." )";

            $db->setQuery( $query );
            $db->execute();

            $dataid = $db->insertid();
            $seatid = 1;

        }else{

            if(empty($item)){

                $query = "INSERT INTO #__ticketstation_seatplancoords (orderid, x_pos, y_pos, ticketid, seatid, booked, type, parent, width, height, row_name) 
							VALUES (0, 10, 10, ".$ticketid.", 1, 0, ".$seat->type.", ".$result->parent.", ".$seat->seat_width.", ".$seat->seat_height.", ".$db->quote($row_name)." )";

                $db->setQuery( $query );
                $db->execute();

                $dataid = $db->insertid();
                $seatid = 1;

            }else{

                $seatid = $item->seatid+1;

                $query = "INSERT INTO #__ticketstation_seatplancoords (orderid, x_pos, y_pos, ticketid, seatid, booked, type, parent, width, height, row_name) 
							VALUES (0, 10, 10, ".$ticketid.", ".(int)$seatid.", 0, ".$seat->type.", ".$result->parent.", ".$seat->seat_width.", ".$seat->seat_height.", ".$db->quote($row_name).")";

                $db->setQuery( $query );
                $db->execute();

                $dataid = $db->insertid();

            }

        }

        $arr = array('seatid' => $seatid, 'id' => $dataid, 'seat_width' => $seat->seat_width, 'seat_height' => $seat->seat_height, 'row_name' => $row_name, 'new_seatnr' => $new_seat);

        echo json_encode($arr);

    }

    function BatchAdds(){

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $jinput = Factory::getApplication()->getInput();

        $start_seat 	= $jinput->get('database_id', '1390', 'INT');
        $seats_to_add 	= $jinput->get('seat_amount', '5', 'INT');
        $direction 		= $jinput->get('direction', '2', 'INT');
        $seat_margin 	= $jinput->get('seat_margin', '3', 'INT');
        $seat_counter 	= $jinput->get('seat_counter', '1', 'INT');
        $up_down 		= $jinput->get('up_down', 'up', 'WORD');


        $model = $this->getModel('seatplans');

        if(!$json_object = $model->seatBatch($start_seat, $seats_to_add, $seat_margin, $direction, $up_down, $seat_counter)) {
            $link = 'index.php?option=com_ticketstation&view=seatplans';
            $this->setRedirect($link, Text::_( 'COM_TICKETSTATION_FAILED_BATCHING_SEATS'));
        }

        echo $json_object;
        exit();

    }

    function removerecord() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $id = Factory::getApplication()->getInput()->get('id', 0);

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## Make the query to delete one of the categories.
        $query = 'DELETE FROM #__ticketstation_seatplancoords WHERE id ='.(int)$id.'';
        $db->setQuery($query);

        if (!$db->execute() ){
            $arr = array('result' => '0', 'id' => $id);
        }else{
            $arr = array('result' => '1', 'id' => $id);
        }

        echo json_encode($arr);

    }

    function copyfromsource() {

        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $db = Factory::getContainer()->get('DatabaseDriver');

        $targetid   = Factory::getApplication()->getInput()->get('targetid', 0);
        $sourceid     = Factory::getApplication()->getInput()->get('sourceid', 0);

        // First, we need to delete all current seats for this ticket
        $query = 'DELETE FROM #__ticketstation_seatplancoords WHERE ticketid ='.(int)$targetid.'';

        $db->setQuery($query);

        if (!$db->execute() ) {

            $arr = array('result' => '0');

            echo json_encode($arr);
            exit();

        } else {

            $model = $this->getModel('seatplans');

            if (!$json_object = $model->copyFromSource($sourceid, $targetid)) {
                $link = 'index.php?option=com_ticketstation&view=seatplans';
                $this->setRedirect($link, Text::_('COM_TICKETSTATION_FAILED_COPY_FROM_SOURCE'));
            }

            echo $json_object;
            exit();
        }



    }

}