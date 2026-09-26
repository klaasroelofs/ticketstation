<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


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


        //decode JSON data received from AJAX POST request
        $data = json_decode($this->input->post->get('data', '', 'raw'));

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

    ## The seat edit form: seat number, row, position, section and status. A sold seat keeps
    ## its section and status; those change with its order.
    function loadSeat(){

        $seatid = Factory::getApplication()->getInput()->getInt('seatid', 0);

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $sql = 'SELECT c.*, o.ordercode
				FROM #__ticketstation_seatplancoords AS c
				LEFT JOIN #__ticketstation_orders AS o ON o.orderid = c.orderid AND c.orderid > 0
				WHERE c.id = '.(int)$seatid;

        $db->setQuery($sql);
        $data = $db->loadObject();

        if (!$data) {
            echo '<div class="alert alert-warning">' . Text::_('COM_TICKETSTATION_SEAT_NOT_FOUND') . '</div>';
            return;
        }

        $owner = SeatplanSettings::owner($data);
        $sold  = (int) $data->orderid > 0;
        $esc   = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $query = $db->getQuery(true)
            ->select(['ticketid', 'ticketname'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('parent') . ' = ' . $owner)
            ->order('ticketname ASC');

        $db->setQuery($query);

        $sections = [HTMLHelper::_('select.option', $owner, Text::_('COM_TICKETSTATION_SEAT_KIND_FREE'), 'value', 'text')];

        foreach ($db->loadObjectList() as $child) {
            $sections[] = HTMLHelper::_('select.option', (int) $child->ticketid, Text::sprintf('COM_TICKETSTATION_SEAT_KIND_SECTION', $child->ticketname), 'value', 'text');
        }

        $disabled = $sold ? ' disabled' : '';

        $lists['section'] = HTMLHelper::_('select.genericList', $sections, 'section', ' class="form-select"' . $disabled, 'value', 'text', (int) $data->ticketid);

        $status = [
            HTMLHelper::_('select.option', '0', Text::_('COM_TICKETSTATION_FREE'), 'value', 'text'),
            HTMLHelper::_('select.option', '1', Text::_('COM_TICKETSTATION_SEAT_STATUS_BLOCKED'), 'value', 'text'),
        ];

        $lists['state'] = HTMLHelper::_('select.genericList', $status, 'blocked', ' class="form-select"', 'value', 'text', (int) $data->blocked);

        echo '<h3 id="editSeatChanger">'.Text::_( 'COM_TICKETSTATION_EDIT_SEATNUMBER' ).' '.(int)$seatid.'</h3>';
        echo '<form class="form" action = "index.php" method="POST" name="adminForm" id="adminForm1">';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="seatid">'.Text::_( 'COM_TICKETSTATION_NEW_SEATID' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="seatid" name="seatid" placeholder="Seat Number" value="'.$esc($data->seatid).'">';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="row_name">'.Text::_( 'COM_TICKETSTATION_NEW_ROW_NAME' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="row_name" name="row_name" placeholder="Row Name" maxlength="5" value="'.$esc($data->row_name).'">';
        echo '    </div>';
        echo '  </div>';

        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="x_pos">'.Text::_( 'COM_TICKETSTATION_X_POS_NEW' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="x_pos" name="x_pos" placeholder="" value="'.$esc($data->x_pos).'">';
        echo '    </div>';
        echo '  </div>';
        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="y_pos">'.Text::_( 'COM_TICKETSTATION_Y_POS_NEW' ).'</label>';
        echo '    <div class="controls">';
        echo '      <input class="form-control" type="text" id="y_pos" name="y_pos" placeholder="" value="'.$esc($data->y_pos).'">';
        echo '    </div>';
        echo '  </div>';

        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="section">'.Text::_( 'COM_TICKETSTATION_SEAT_SECTION' ).'</label>';
        echo '    <div class="controls">';
        echo 		 $lists['section'];
        echo '    </div>';
        echo '  </div>';

        echo '  <div class="control-group">';
        echo '    <label class="control-label" for="blocked">'.Text::_( 'COM_TICKETSTATION_BOOKING_STATUS' ).'</label>';
        echo '    <div class="controls">';

        if ($sold) {
            echo '      <p class="form-control-plaintext">'.Text::sprintf('COM_TICKETSTATION_SEAT_SOLD_TO', $esc($data->ordercode)).'</p>';
        } else {
            echo 		 $lists['state'];
        }

        echo '    </div>';
        echo '  </div>';

        echo '  <button type="submit" class="btn btn-primary">'.Text::_( 'COM_TICKETSTATION_SAVE_CHANGES' ).'</button>';

        echo '  <input type="hidden" name="id" value="'.(int)$seatid.'" />';
        echo '  <input type="hidden" name="option" value="com_ticketstation" />';
        echo '  <input type="hidden" name="task" value="saveSeatChanges" />';
        echo '  <input type="hidden" name="controller" value="seatplans" />';
        echo '  ' . HTMLHelper::_('form.token');

        echo '</form>';

        echo '<div style="clear:both;"></div>';

    }

    ## Saves the seat edit form. The section must be one of the chart owner's child tickets
    ## (or the owner itself, for a free seat). A sold seat keeps its section and status.
    function saveSeatChanges() {

        $post = $this->input->post;
        $id   = $post->getInt('id', 0);
        $db   = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT * FROM #__ticketstation_seatplancoords WHERE id = ' . $id);
        $seat = $db->loadObject();

        if (!$seat) {
            $this->setRedirect('index.php?option=com_ticketstation&view=seatplans', Text::_('COM_TICKETSTATION_SEAT_NOT_FOUND'), 'error');
            return;
        }

        $owner = SeatplanSettings::owner($seat);

        $update = (object) [
            'id'       => $id,
            'seatid'   => $post->getInt('seatid', (int) $seat->seatid),
            'row_name' => mb_substr(trim($post->getString('row_name', '')), 0, 5),
            'x_pos'    => $post->getInt('x_pos', (int) $seat->x_pos),
            'y_pos'    => $post->getInt('y_pos', (int) $seat->y_pos),
        ];

        if ((int) $seat->orderid === 0) {

            $section = $post->getInt('section', (int) $seat->ticketid);

            ## Anything but the owner or one of its child tickets leaves the section unchanged.
            if ($section !== $owner) {
                $db->setQuery('SELECT parent FROM #__ticketstation_tickets WHERE ticketid = ' . $section);

                if ((int) $db->loadResult() !== $owner) {
                    $section = (int) $seat->ticketid;
                }
            }

            $update->ticketid = $section;
            $update->parent   = $section === $owner ? 0 : $owner;
            $update->blocked  = $post->getInt('blocked', 0) === 1 ? 1 : 0;
            $update->booked   = $update->blocked;
        }

        $db->updateObject('#__ticketstation_seatplancoords', $update, 'id');

        $this->setRedirect('index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid=' . $owner);
    }

    ## Adds one seat to a chart: a free seat when $ticketid is the chart owner itself, or a
    ## section seat when it is one of the owner's child tickets. Seats are numbered per chart
    ## and row, continuing from the highest number so far (or starting at 1 with new_seat).
    function getRecord() {

        $input      = Factory::getApplication()->getInput();
        $ticketid   = $input->getInt('ticketid', 0);
        $row_name   = mb_substr(trim($input->getString('row_name', '')), 0, 5);
        $new_seat   = $input->getInt('new_seat', 0);

        $db     = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT parent FROM #__ticketstation_tickets WHERE ticketid = ' . $ticketid);
        $parent = (int) $db->loadResult();
        $owner  = $parent > 0 ? $parent : $ticketid;

        ## Load the dimensions of this seat from the chart owner's settings
        $db->setQuery('SELECT * FROM #__ticketstation_seatplansettings WHERE ticketid = ' . $owner . ' ORDER BY id');
        $seat = $db->loadObject();

        ## Fall back to sane defaults when the seatplan settings row is missing
        ## or has an empty type/width/height (all three are nullable columns).
        $seat_type   = (!empty($seat) && $seat->type !== null && $seat->type !== '') ? (int) $seat->type : 1;
        $seat_width  = (!empty($seat) && $seat->seat_width !== null && $seat->seat_width !== '') ? (int) $seat->seat_width : 22;
        $seat_height = (!empty($seat) && $seat->seat_height !== null && $seat->seat_height !== '') ? (int) $seat->seat_height : 22;

        $seatid = 1;

        if ($new_seat != 1) {
            $query = $db->getQuery(true)
                ->select('MAX(seatid)')
                ->from($db->quoteName('#__ticketstation_seatplancoords'))
                ->where('(' . $db->quoteName('ticketid') . ' = ' . $owner . ' OR ' . $db->quoteName('parent') . ' = ' . $owner . ')')
                ->where($db->quoteName('row_name') . ' = ' . $db->quote($row_name));

            $db->setQuery($query);
            $seatid = (int) $db->loadResult() + 1;
        }

        $record = (object) [
            'orderid'  => 0,
            'x_pos'    => 10,
            'y_pos'    => 10,
            'ticketid' => $ticketid,
            'seatid'   => $seatid,
            'booked'   => 0,
            'type'     => $seat_type,
            'parent'   => $parent,
            'width'    => $seat_width,
            'height'   => $seat_height,
            'row_name' => $row_name,
        ];

        $db->insertObject('#__ticketstation_seatplancoords', $record, 'id');

        $arr = array('seatid' => $seatid, 'id' => (int) $record->id, 'seat_width' => $seat_width, 'seat_height' => $seat_height, 'row_name' => $row_name, 'new_seatnr' => (string) $new_seat);

        echo json_encode($arr);

    }

    function BatchAdds(){


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


        $db = Factory::getContainer()->get('DatabaseDriver');

        $targetid   = Factory::getApplication()->getInput()->get('targetid', 0);
        $sourceid     = Factory::getApplication()->getInput()->get('sourceid', 0);

        // Checked here and not only in the page: a seat may have been sold since the chart was opened.
        $db->setQuery('SELECT COUNT(*) FROM #__ticketstation_seatplancoords WHERE (ticketid = ' . (int) $targetid . ' OR parent = ' . (int) $targetid . ') AND orderid > 0');

        if ((int) $db->loadResult() > 0) {
            echo json_encode(['result' => 'sold']);
            exit();
        }

        // First, we need to delete all current seats for this ticket
        $query = 'DELETE FROM #__ticketstation_seatplancoords WHERE ticketid = '.(int)$targetid.' OR parent = '.(int)$targetid;

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