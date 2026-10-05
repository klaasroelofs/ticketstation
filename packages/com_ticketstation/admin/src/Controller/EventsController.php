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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Date\Date;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\EventCopy;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class EventsController extends BaseController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Events';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask( 'add' , 'edit' );
        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'events');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'event');
        parent::display();
    }

    function publish()
    {

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        ## Getting the task (publish/upnpublish)
        if ($this->getTask() == 'publish') {
            $publish = 1;
        } else {
            $publish = 0;
        }

        if (count( $cid ) < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_SELECT_ITEM'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&controller=events');
            return;
        }

        $model = $this->getModel('events');

        if($model->publish($cid, $publish))
        {
            if ($publish == 1) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=events', Text::_('COM_TICKETSTATION_EVENT_PUBLISHED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=events', Text::_('COM_TICKETSTATION_EVENT_UNPUBLISHED'));
            }

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_PUBLISHED_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=events');

        }

    }

    /**
     * Copies one event with its tickets, seat charts and design files (see EventCopy) and opens
     * the copy.
     */
    function duplicate()
    {
        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count($cid) !== 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENTS_COPY_ONE'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=events');

            return;
        }

        try
        {
            $newEvent = EventCopy::copy((int) $cid[0]);
        }
        catch (\RuntimeException $e)
        {
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_EVENT_COPY_FAILED', $e->getMessage()), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=events');

            return;
        }

        $this->setRedirect(
            'index.php?option=com_ticketstation&view=event&layout=edit&cid=' . $newEvent,
            Text::_('COM_TICKETSTATION_EVENTS_COPIED')
        );
    }

    function remove()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('events');

        if(!$model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_DELETE_EVENT'), 'error');
            $app->redirect('index.php?option=com_ticketstation&controller=events');
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_DELETED'));
        $app->redirect('index.php?option=com_ticketstation&controller=events');
    }



    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    public function tickets($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=tickets');
    }

}