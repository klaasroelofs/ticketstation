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
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class TicketsController extends BaseController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Tickets';

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
        $jinput->set('view', 'tickets');
        parent::display();
    }

    public function seatplans($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=seatplans');
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'ticket');
        $jinput->set('hidemainmenu', '0');
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
            $this->setRedirect('index.php?option=com_ticketstation&view=Tickets');
            return;
        }

        $model = $this->getModel('tickets');

        if($model->publish($cid, $publish))
        {
            if ($publish == 1) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_PUBLISHED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_UNPUBLISHED'));
            }

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_PUBLISHED_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets');

        }

    }

    function remove()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('tickets');

        if(!$model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_DELETE_EVENT'), 'error');
            $app->redirect('index.php?option=com_ticketstation&view=Tickets');
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_DELETED'));
        $app->redirect('index.php?option=com_ticketstation&view=Tickets');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    function duplicate()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('ticket');

        foreach($cid as $item)
        {
            $model->copyTicket($item);
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_COPIED'));
        $app->redirect('index.php?option=com_ticketstation&view=Tickets');
    }

    function resetscanstate()
    {

        $app    = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_A_TICKET'), 'error');
            $app->redirect('index.php?option=com_ticketstation&view=Tickets');
        }

        $model = $this->getModel('tickets');

        if(!$model->resetScanstate($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SCANSTATUS_NOT_CHANGED'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SCANSTATUS_RESET'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&view=Tickets');
    }

}