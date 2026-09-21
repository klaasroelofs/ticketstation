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


class VenuesController extends BaseController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Venues';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask( 'add' , 'edit' );
        $this->registerTask('unpublish','publish');
        //$this->registerTask('apply','save' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'default');
        $jinput->set('view', 'venues');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'form');
        $jinput->set('view', 'venues');
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
            $this->setRedirect('index.php?option=com_ticketstation&controller=venues');
            return;
        }

        $model = $this->getModel('venues');

        if($model->publish($cid, $publish))
        {
            if ($publish == 1) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=venues', Text::_('COM_TICKETSTATION_VENUE_PUBLISHED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=venues', Text::_('COM_TICKETSTATION_VENUE_UNPUBLISHED'));
            }

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_VENUE_PUBLISHED_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=venues');

        }

    }

    function remove()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('venues');

        if(!$model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_DELETE_VENUE'), 'error');
            $app->redirect('index.php?option=com_ticketstation&controller=venues');
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_VENUE_DELETED'));
        $app->redirect('index.php?option=com_ticketstation&controller=venues');
    }

    function apply()
    {

        $app 	    = Factory::getApplication();
        $jinput     = $app->input;
        $model	    = $this->getModel('venues');
        $data   	= $jinput->post->getArray();

        $data['venuedescription'] = $jinput->get('venuedescription', null, 'raw');
        $data['website']		= $jinput->get('website', null, 'raw');

        if ($model->store($data))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=venues&task=edit&cid=' . $model->getVenueID(), Text::_('COM_TICKETSTATION_VENUE_SAVED'));
        } else {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=venues', Text::_('COM_TICKETSTATION_VENUE_SAVED_FAILED'));
        }

    }

    public function save($cachable = false, $urlparams = [])
    {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Venues', Text::_('COM_TICKETSTATION_VENUE_SAVED'));
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Venues');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

}