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
 * Ticketstation Ticket Model
 * @since 0.0.1
 */
class TicketModel extends AdminModel
{
    /**
     * @var int|\Joomla\Database\DatabaseInterface|mixed
     */
    private $ticketid;

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
        $form = $this->loadForm('com_ticketstation.ticket', 'ticket', array('control' => 'jform', 'load_data' => $loadData));

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
        $data = $app->getUserState('com_ticketstation.edit.ticket.data', array());

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
                $data 			= $this->getItem($this->ticketid);
            }
        }

        $this->preprocessData('com_ticketstation.ticket', $data);

        return $data;
    }

    function getItem($pk = null)
    {

        $app  			= Factory::getApplication();
        $input      	= $app->getInput()->get('cid', array(0), 'array');
        $this->ticketid	= (int)$input[0];

        return parent::getItem($this->ticketid);

    }

    function store($data)
    {

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $table = $this->getTable();

        // Bind the data.
        if (!$table->bind($data)) {
            $app->enqueueMessage('Bind failed', 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            $app->enqueueMessage('Check failed', 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            $app->enqueueMessage('Store failed ' . $table->getError(), 'error');
            //$app->enqueueMessage('<pre>' . print_r($data, 1) . '</pre>', 'error');
            //$this->setError($table->getError());
            return false;
        }

        if ($data['ticketid'] != 0) {
            $this->ticketid = $data['ticketid'];
        } else {
            $this->ticketid = $this->_db->insertid();
        }

        // Store files on server
        $files = $jinput->files->get('jform');

        // Ticket Template (layout)
        $ticket_design  = $files['designfile'];
        $path 		    = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/';
        $uploaded 	    = false;

        if ($ticket_design['name']) {

            $ticket_design['name'] = File::makeSafe($ticket_design['name']);
            ## The link to the previous saved data.
            $link = 'index.php?option=com_ticketstation&view=ticket&layout=edit&cid='.$this->ticketid;

            // Check if submitted filetype is supported
            $allowed = array('image/jpeg','image/JPG','image/jpg','application/pdf');
                if (!in_array($ticket_design['type'], $allowed)) {
                $app->enqueueMessage($ticket_design['name'].' '.Text::_( 'COM_TICKETSTATION_ONLY_JPG_PDF_ALLOWED'), 'error');
                $app->redirect($link);
            }

            $ticket = 'eTicket-'.$this->ticketid;

            $image_ext = array('image/jpeg','image/JPG','image/jpg');

            if (in_array($ticket_design['type'], $image_ext)) {
                // The submitted file is an image

                chmod ($ticket_design['tmp_name'], 0755);

                // Moving the file to the destination folder
                if (!File::upload($ticket_design['tmp_name'], $path . $ticket . '.jpg')) {
                    $app->enqueueMessage($ticket_design['name'].' '.Text::_( 'COM_TICKETSTATION_COULD_NOT_MOVE_FILE'), 'error');
                    $app->redirect($link);
                }

                // Delete PDF design, if present
                $file = 'eTicket-' . $this->ticketid . '.pdf';

                if (file_exists($path . $file)) {
                    File::delete( $path . $file );
                }

                $uploaded = true;

            } else {
                // The submitted file is a PDF

                chmod ($ticket_design['tmp_name'], 0755);

                // Moving the file to the destination folder
                if (!File::upload($ticket_design['tmp_name'], $path . $ticket . '.pdf'))
                {
                    $app->enqueueMessage($ticket_design['name'].' '.Text::_( 'COM_TICKETSTATION_COULD_NOT_MOVE_FILE'), 'error');
                    $app->redirect($link);
                }

                // Delete JPG design, if present
                $file = 'eTicket-' . $this->ticketid . '.jpg';

                if (file_exists($path . $file))
                {
                    File::delete( $path . $file );
                }

                $uploaded = true;
            }
        }

        // Ticket Background Image (for view=Upcoming frontend)
        $ticket_bg  = $files['backgroundupcomingfile'];
        $path 		= JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/';

        if ($ticket_bg['name']) {

            $ticket_bg['name'] = File::makeSafe($ticket_bg['name']);
            ## The link to the previous saved data.
            $link = 'index.php?option=com_ticketstation&view=ticket&layout=edit&cid='.$this->ticketid;

            // Check if submitted filetype is supported
            $allowed = array('image/jpeg','image/JPG','image/jpg');
            if (!in_array($ticket_bg['type'], $allowed)) {
                $app->enqueueMessage($ticket_bg['name'].' '.Text::_( 'COM_TICKETSTATION_ONLY_JPG_ALLOWED'), 'error');
                $app->redirect($link);
            }

            $ticket = 'ticket'.$this->ticketid;


            chmod ($ticket_bg['tmp_name'], 0755);

            // Moving the file to the destination folder
            if (!File::upload($ticket_bg['tmp_name'], $path . $ticket . '.jpg')) {
                $app->enqueueMessage($ticket_bg['name'].' '.Text::_( 'COM_TICKETSTATION_COULD_NOT_MOVE_FILE'), 'error');
                $app->redirect($link);
            }

        }


        ## Copy ticket if file is exists:
        if( $data['copy_ticket'] == 1 && $data['jquerselect'] > 0 && $uploaded == false  )
        {

            if (file_exists($path.'eTicket-'.$data['jquerselect'].'.pdf'))
            {
                File::copy($path.'eTicket-'.$data['jquerselect'].'.pdf', $path.'eTicket-'.$this->ticketid.'.pdf');
            }
            else if(file_exists($path.'eTicket-'.$data['jquerselect'].'.jpg'))
            {
                File::copy($path.'eTicket-'.$data['jquerselect'].'.jpg', $path.'eTicket-'.$this->ticketid.'.jpg');
            }
            else
            {
                return true;
            }
        }

        return true;
    }

    function getTicketID()
    {
        return $this->ticketid;
    }

    function copyTicket($id)
    {
        if (empty($id))
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . (int) $id);

        $db->setQuery($query);

        $data = $db->loadAssoc();

        unset($data['ticketid']);

        $data['ticketname'] 	= $data['ticketname'] . ' ' . Text::_('COM_TICKETSTATION_COPIED');
        $data['totaltickets'] 	= $data['starting_total_tickets'];

        return parent::save($data);

    }

    function getTicketLayout($id)
    {
        if (empty($id))
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . (int) $id);

        $db->setQuery($query);

        return $db->loadObject();
    }

}