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
use Joomla\Filesystem\File;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;


/**
 * Ticketstation Event Model
 * @since 0.0.1
 */
class EventModel extends AdminModel
{
    /**
     * @var int|\Joomla\Database\DatabaseInterface|mixed
     */
    private $eventid;

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form. [optional]
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not. [optional]
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since 1.0
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_ticketstation.event', 'event', array('control' => 'jform', 'load_data' => $loadData));

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
        $data = $app->getUserState('com_ticketstation.edit.event.data', array());

        if (empty($data))
        {
            $session = $app->getSession();
            $session_postdata = $session->get('postdata');
            if (!empty($session_postdata)) {
                $data = (object) $session_postdata;
                $session->clear('postdata');
            } else {
                $input      	= $app->getInput()->get('cid', array(0), 'array');
                $this->eventid 	= (int)$input[0];
                $data 			= $this->getItem($this->eventid);
            }
        }

        $this->preprocessData('com_ticketstation.event', $data);

        return $data;
    }

    function getItem($pk = null)
    {

        $app  			= Factory::getApplication();
        $input      	= $app->getInput()->get('cid', array(0), 'array');
        $this->eventid 	= (int)$input[0];

        return parent::getItem($this->eventid);

    }

    public function store($data)
    {
        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $table  = $this->getTable();

        if (!$table->save($data)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_SAVE_FAILED'), 'error');
            $this->setError($table->getError());
            return false;
        }


//        // Bind the data.
//        if (!$table->bind($data)) {
//            Factory::getApplication()->enqueueMessage('Bind failed', 'error');
//            //$this->setError($table->getError());
//            return false;
//        }
//
//        // Check the data.
//        if (!$table->check()) {
//            Factory::getApplication()->enqueueMessage('Check failed', 'error');
//            //$this->setError($table->getError());
//            return false;
//        }
//
//        // Store the data.
//        try {
//            $table->store($data);
//        }
//        catch (\Exception $e) {
//            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
//            return false;
//        }


//        if (!$table->store()) {
//            Factory::getApplication()->enqueueMessage('Store failed', 'error');
//            $this->setError($table->getError());
//            return false;
//        }

        if ($data['eventid'] != 0)
        {
            $this->eventid = $data['eventid'];
        }
        else
        {
            $this->eventid = $this->_db->insertid();
        }

        // Store files on server
        $files = $jinput->files->get('jform');

        // Ticket Background Image (for view=Upcoming frontend)
        $event_bg = $files['backgroundupcomingfile'];
        $path 		= JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/';

        if ($event_bg['name']) {

            $event_bg['name'] = File::makeSafe($event_bg['name']);
            ## The link to the previous saved data.
            $link = 'index.php?option=com_ticketstation&view=event&layout=edit&cid='.$this->eventid;

            // Check if submitted filetype is supported
            $allowed = array('image/jpeg','image/JPG','image/jpg');
            if (!in_array($event_bg['type'], $allowed)) {
                $app->enqueueMessage($event_bg['name'].' '.Text::_( 'COM_TICKETSTATION_ONLY_JPG_ALLOWED'), 'error');
                $app->redirect($link);
            }

            $event = 'event'.$this->eventid;


            chmod ($event_bg['tmp_name'], 0755);

            // Moving the file to the destination folder
            if (!File::upload($event_bg['tmp_name'], $path . $event . '.jpg')) {
                $app->enqueueMessage($event_bg['name'].' '.Text::_( 'COM_TICKETSTATION_COULD_NOT_MOVE_FILE'), 'error');
                $app->redirect($link);
            }

        }

        return true;

    }

    function getEventID()
    {
        return $this->eventid;
    }

}