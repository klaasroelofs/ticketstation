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
use Joomla\Filesystem\File;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Ticketstation Seatplansettings Model
 * @since 0.0.1
 */
class SeatplansettingsModel extends AdminModel
{
    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form. [optional]
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not. [optional]
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_ticketstation.seatplansettings', 'seatplansettings', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form))
        {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_ticketstation.edit.seatplansettings.data', array());

        if (empty($data))
        {
            $session = $app->getSession();
            $session_postdata = $session->get('postdata');
            if (!empty($session_postdata)) {
                $data = (object) $session_postdata;
                $session->clear('postdata');
            } else {
                $input      	= $app->getInput()->get('cid', array(0), 'array');
                $this->ticketid	= (int)$input[0];
                $data 			= $this->getData($this->ticketid);
            }
        }

        $this->preprocessData('com_ticketstation.seatplansettings', $data);

        return $data;
    }

    function getData()
    {
        $app    	= Factory::getApplication();
        $db         = Factory::getContainer()->get('DatabaseDriver');
        $array      = $app->getInput()->get('cid', array(0), 'array');
        $ticketid  = (int)$array[0];

        $query = $db->getQuery(true);

        $query->select(array('s.*', 't.ticketid', 't.ticketname', 't.ticketcode'));
        $query->from($db->quoteName('#__ticketstation_tickets', 't'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_seatplansettings', 's') . ' ON (' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('s.ticketid').')');
        $query->where($db->quoteName('t.ticketid') . ' = '. $db->quote((int)$ticketid));

        $db->setQuery($query);
        return $db->loadObject();

    }

    function store($data) //TODO: Replace deprecated getError
    {
        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $table  = $this->getTable();

        $ticketid = (int)$data['ticketid'];

        // Save the data.
        if (!$table->save($data)) {
            $app->enqueueMessage('Store failed ' . $table->getError(), 'error');
            return false;
        }

        // Store files on server
        $files = $jinput->files->get('jform');

        // Ticket Background Image (for view=Upcoming frontend)
        $seatchart_bg   = $files['jpgbackground'];
        $path 		    = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts/';

        if ($seatchart_bg['name']) {

            $seatchart_bg['name'] = File::makeSafe($seatchart_bg['name']);
            ## The link to the previous saved data.
            $link = 'index.php?option=com_ticketstation&controller=seatplans&task=editsettings&cid='.$ticketid;

            // Check if submitted filetype is supported
            $allowed = array('image/jpeg','image/JPG','image/jpg','image/png');
            if (!in_array($seatchart_bg['type'], $allowed)) {
                $app->enqueueMessage($seatchart_bg['name'].' '.Text::_( 'COM_TICKETSTATION_ONLY_JPG_ALLOWED'), 'error');
                $app->redirect($link);
            }

            $seatchart = 'seatchart'.$ticketid;
            if ($seatchart_bg['type'] == 'image/png') {
                $file_extension = '.png';
            } else {
                $file_extension = '.jpg';
            }

            chmod ($seatchart_bg['tmp_name'], 0755);

            // Moving the file to the destination folder
            if (!File::upload($seatchart_bg['tmp_name'], $path . $seatchart . $file_extension)) {
                $app->enqueueMessage($seatchart_bg['name'].' '.Text::_( 'COM_TICKETSTATION_COULD_NOT_MOVE_FILE'), 'error');
                $app->redirect($link);
            }

        }

        return true;

    }

}