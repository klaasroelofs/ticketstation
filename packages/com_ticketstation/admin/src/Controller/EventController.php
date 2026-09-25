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
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class EventController extends FormController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Event';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        //$this->registerTask( 'add' , 'edit' );
        //$this->registerTask('unpublish','publish');
        $this->registerTask('apply','apply' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'event');
        //$jinput->set('hidemainmenu', 1);
        parent::display();
    }

    function apply()
    {

        $model	    = $this->getModel('event');
        $data       = $this->input->post->get('jform', array(), 'array');

        if (!empty($data['closingdate'])) {
            $data['closingdate'] = date('Y-m-d H:i:s', strtotime($data['closingdate']));
        } else {
            $data['closingdate'] = null;
        }
        if (!empty($data['startdate'])) {
            $data['startdate'] = date('Y-m-d H:i:s', strtotime($data['startdate']));
        } else {
            $data['startdate'] = null;
        }
        if (!empty($data['eventdate'])) {
            $data['eventdate'] = date('Y-m-d', strtotime($data['eventdate']));
        } else {
            $data['eventdate'] = null;
        }

        if ($model->store($data)) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=event&layout=edit&cid=' . $model->getEventID(), Text::_('COM_TICKETSTATION_EVENT_SAVED'));
        } else {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events', Text::_('COM_TICKETSTATION_EVENT_SAVED_FAILED'));
        }

    }

    public function save($cachable = false, $urlparams = [])
    {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events', Text::_('COM_TICKETSTATION_EVENT_SAVED'));
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }

    function removeBackground()
    {
        // Reached via a plain GET link in admin/tmpl/event/edit.php - check the
        // token as a query param, not just POST.

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $eventid = $jinput->get('eventid', '0', 'INT');

        if($eventid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=event&task=edit');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $eventid . '.jpg';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TICKETBG_EVENT_REMOVED'), 'info');
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=event&layout=edit&cid='.$eventid);
    }

}