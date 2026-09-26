<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Seatplans;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;

/**
 * Ticketstation Seatplans Admin View
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var int
     */
    protected $id;

    /**
     * Display the Ticketstation Seatplans view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'chart') {
            $this->_displayChart($tpl);
            return;
        }

        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_TITLE'), 'fa fa-chair');
        ToolbarHelper::custom('tickets', 'icon-ticket-alt', '', 'COM_TICKETSTATION_TICKETS', false,false);
        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $items      = $this->get('list');
        $config     = $this->get('config');

        $this->items = $items;
        $this->config = $config;

        parent::display($tpl);

    }

    function _displayChart($tpl = null)
    {

        $app        = Factory::getApplication();
        $input      = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$input[0];

        $data	= $this->get('data');

        ## The seat plan settings row is only ever created once the settings
        ## screen has been opened/saved for the (parent) ticket. Bail out
        ## with a friendly notice instead of letting a missing/incomplete
        ## row crash the chart further down.
        if (empty($data)) {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SEATPLANSETTINGS_NOT_SET'), 'warning');
            $app->redirect('index.php?option=com_ticketstation&controller=seatplans&task=editsettings&cid=' . $this->id);
            return;
        }

        if ($data->seat_width === null || $data->seat_width === '' || $data->seat_height === null || $data->seat_height === '') {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SEATPLANSETTINGS_INCOMPLETE'), 'warning');
        }

        $items = $this->get('seats');

        $db = Factory::getContainer()->get('DatabaseDriver');

        ## What a new seat becomes: a free seat of this ticket (the customer picks a price
        ## category) or a section seat of one of its child tickets (fixed price).
        $query = $db->getQuery(true)
            ->select(['ticketid AS id', 'ticketname AS name'])
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('parent') . " = " . (int) $this->id)
            ->order('ticketname ASC');

        $db->setQuery($query);
        $children = $db->loadObjectList();

        $ticketlist[]	  = HTMLHelper::_('select.option',  (int) $this->id, Text::_( 'COM_TICKETSTATION_SEAT_KIND_FREE' ), 'id', 'name' );

        foreach ($children as $child) {
            $ticketlist[] = HTMLHelper::_('select.option', (int) $child->id, Text::sprintf('COM_TICKETSTATION_SEAT_KIND_SECTION', $child->name), 'id', 'name');
        }

        $lists['childtickets'] = HTMLHelper::_('select.genericlist',  $ticketlist, 'single', 'class="form-select" style="width:100%;" ', 'id', 'name', (int) $this->id );

        $type = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_NO' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_YES' )),
        );
        $lists['type'] = HTMLHelper::_('select.genericList', $type, 'new_seat', ' class="form-select" style="width:100%;"'. '', 'value', 'text', 0 );

        ## Which child tickets are sections and which are price categories, for the hint in
        ## the editor: a child with seats is a section, a published child without seats a
        ## price category.
        $this->categories = SeatplanSettings::priceCategories((int) $this->id);
        $this->freeSeats  = 0;

        foreach ($items as $item) {
            if ((int) $item->parent === 0) {
                $this->freeSeats++;
            }
        }

        ## Source tickets for copy functionality
        $query = $db->getQuery(true)
            ->select(['t.ticketid AS id', 'CONCAT(e.eventcode, " | ", t.ticketname) AS name'])
            ->from($db->quoteName('#__ticketstation_tickets', 't'))
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON (' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('t.eventid') . ')')
            ->where($db->quoteName('t.ticketid') . " != " . (int) $this->id)
            ->where($db->quoteName('t.show_seatplans') . " = 1")
            ->where($db->quoteName('t.parent') . " = 0")
            ->order('name ASC');

        $db->setQuery($query);

        $sourcelist[]	  = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_PLS_SELECT' ), 'id', 'name' );
        $sourcelist	      = array_merge( $sourcelist, $db->loadObjectList() );
        $lists['sourcetickets'] = HTMLHelper::_('select.genericlist',  $sourcelist, 'source', 'class="form-select" style="width:100%;" ', 'id', 'name', intval(0) );

        ## BATCH MODE OPTIONS ##
        $direction = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_LTR' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_RTL' )),
            '2' => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_UP_DOWN' )),
            '3' => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_DOWN_UP' )),
        );
        $lists['direction'] = HTMLHelper::_('select.genericList', $direction, 'direction', ' class="form-select"  style="width:100%;" '. '', 'value', 'text', 0 );

        $seat_counter = array(
            '1' => array('value' => '1', 'text' => '1'),
            '2' => array('value' => '2', 'text' => '2'),
            '3' => array('value' => '3', 'text' => '3'),
            '4' => array('value' => '4', 'text' => '4'),
            '5' => array('value' => '5', 'text' => '5'),
        );
        $lists['seat_counter'] = HTMLHelper::_('select.genericList', $seat_counter, 'seat_counter', ' class="form-select" style="width:100%;" '. '', 'value', 'text', 1 );

        $up_down = array(
            '1' => array('value' => 'up', 'text' => Text::_( 'COM_TICKETSTATION_INCREASE_SEATNUMBER' )),
            '2' => array('value' => 'down', 'text' => Text::_( 'COM_TICKETSTATION_DECREASE_SEATNUMBER' )),
        );
        $lists['up_down'] = HTMLHelper::_('select.genericList', $up_down, 'up_down', ' class="form-select" style="width:100%;" '. '', 'value', 'text', 1 );

        $this->items    = $items;
        $this->data     = $data;
        $this->lists    = $lists;

        // Set up the toolbar
        $title_text = empty($this->data->ticketid) ? Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_CHART') : Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_CHART') . ' ' . $this->data->ticketname . ' <small>(' . $this->data->ticketcode . ')</small>';
        ToolbarHelper::title($title_text, 'fa fa-chair');
        ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }

}
