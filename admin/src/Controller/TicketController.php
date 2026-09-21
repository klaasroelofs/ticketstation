<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketPreviewCreator;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


class TicketController extends FormController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Ticket';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        //$this->registerTask( 'add' , 'edit' );
        //$this->registerTask('unpublish','publish');
        $this->registerTask('apply','apply' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'ticket');
        $jinput->set('hidemainmenu', 1);
        parent::display();
    }

    function apply()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app 	    = Factory::getApplication();
        $model	    = $this->getModel('ticket');
        $data       = $this->input->post->get('jform', array(), 'array');

        $data['startdate'] = date('Y-m-d H:i:s', strtotime($data['startdate']));
        $data['enddate'] = date('Y-m-d H:i:s', strtotime($data['enddate']));
        $data['publish_date_time'] = date('Y-m-d H:i:s', strtotime($data['publish_date_time']));
        $data['sale_stop'] = date('Y-m-d H:i:s', strtotime($data['sale_stop']));
        $data['parent'] = intval($data['parent']);
        $data['ticketprice'] = floatval($data['ticketprice']);
        $data['vat_percentage'] = floatval($data['vat_percentage']);

        if ((empty($data['ticketname'])) || (empty($data['ticketcode'])) || (empty($data['eventid'])) || (empty($data['venue']))) {

            if (empty($data['ticketname'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETNAME_MISSING'), 'error');
            } elseif (empty($data['ticketcode'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETCODE_MISSING'), 'error');
            } elseif (empty($data['eventid'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_MISSING'), 'error');
            } elseif (empty($data['venue'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_VENUE_MISSING'), 'error');
            }

            // Save the data in the session.
            $session = $app->getSession();
            $session->set('postdata', $data);

            // Redirect back to the edit screen.
            $this->display();
            if ($this->getTask() == 'save') {
                return false;
            }

        } else {

            if ($model->store($data)) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=ticket&layout=edit&cid=' . $model->getTicketID(), Text::_('COM_TICKETSTATION_TICKET_SAVED'));
                return true;

            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_SAVED_FAILED'));
                return false;
            }

        }

        return false;

    }

    public function save($cachable = false, $urlparams = [])
    {
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_SAVED'));
        }
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets');
    }

    function removeDesign()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $jinput = $app->input;
        $ticketid = $jinput->get('ticketid', '0', 'INT');

        if($ticketid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.jpg';
        $pdf_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.pdf';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        if(file_exists($pdf_background))
        {
            File::delete($pdf_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_DESIGN_REMOVED'), 'info');
        $app->redirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$ticketid);
    }

    function removeBackground()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $jinput = $app->input;
        $ticketid = $jinput->get('ticketid', '0', 'INT');

        if($ticketid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/ticket' . $ticketid . '.jpg';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TICKETBG_REMOVED'), 'info');
        $app->redirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$ticketid);
    }

    function TicketLayout()
    {
        $app = Factory::getApplication();
        $jinput = $app->input;
        $ticketid = $jinput->get('ticketid', '0', 'INT');
        $model	    = $this->getModel('ticket');

        $ticketdata = $model->getTicketLayout($ticketid);

        echo json_encode($ticketdata);
    }

    /**
     * Renders a preview PDF of the ticket using the field formatting (colour, size,
     * position, ticket size/orientation) currently entered in the "Ticket Layout"
     * tab - including any not-yet-saved changes - combined with dummy content, and
     * streams it back inline so it can be shown in the preview modal.
     */
    function PreviewTicket()
    {
        $app 	  = Factory::getApplication();
        $jinput   = $app->input;
        $ticketid = $jinput->get('ticketid', 0, 'INT');
        $data     = $jinput->post->get('jform', array(), 'array');

        $preview = new TicketPreviewCreator();
        $pdf     = $preview->generate($ticketid, $data);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ticket-preview.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf;
        exit();
    }

}