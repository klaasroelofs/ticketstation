<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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


class ClientsController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Clients';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'default');
        $jinput->set('view', 'clients');
        parent::display();
    }

    function edit($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'form');
        $jinput->set('view', 'clients');
        parent::display();
    }

    function apply() {


        $app 	= Factory::getApplication();
        $jinput = $app->input;
        $post 	= $jinput->post->getArray();

        $cid = $this->input->get('clientid');
        ArrayHelper::toInteger($cid);

        $model	= $this->getModel('clients');

        if ($model->store($post))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=clients&task=edit&cid=' . $cid, Text::_('COM_TICKETSTATION_CLIENT_SAVED'));

        } else {

            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=clients&task=edit&cid=' . $cid, Text::_('COM_TICKETSTATION_CLIENT_NOTSAVED'));

        }

    }

    public function save($cachable = false, $urlparams = []) {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Clients', Text::_('COM_TICKETSTATION_CLIENT_SAVED'));
    }

    function publish(){


        $cid    = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        ## Getting the task (publish/upnpublish)
        if ($this->getTask() == 'publish') {
            $publish = 1;
        } else {
            $publish = 0;
        }

        if (count( $cid ) < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_VISITOR'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&controller=clients');
            return;
        }

        $model = $this->getModel('clients');

        if($model->publish($cid, $publish))
        {
            if ($publish == 1) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=clients', Text::_('COM_TICKETSTATION_CLIENT_PUBLISHED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=clients', Text::_('COM_TICKETSTATION_CLIENT_UNPUBLISHED'));
            }

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_CLIENT_PUBLISHED_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=clients');

        }

    }

    function remove()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('clients');

        if(!$model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_CLIENTS_REMOVED_FAILED'), 'error');
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_CLIENT_REMOVED'));
        $app->redirect('index.php?option=com_ticketstation&controller=clients');
    }

    public function cancel($cachable = false, $urlparams = []) {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Clients');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

}