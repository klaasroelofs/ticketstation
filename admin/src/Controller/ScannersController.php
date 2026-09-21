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


class ScannersController extends BaseController
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
        $jinput->set('view', 'scanners');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'form');
        $jinput->set('view', 'scanners');
        parent::display();
    }



    function remove() {


        $app 	= Factory::getApplication();
        $post 	= $app->input->post->getArray();

        ArrayHelper::toInteger($post['cid']);

        $model = $this->getModel('scanners');

        if(!$model->remove($post['cid'])) {

            if( $model->getError()  == null ){
                Factory::getApplication()->enqueueMessage('Kon scanner niet verwijderen', 'error');
            }else{
                Factory::getApplication()->enqueueMessage($model->getError(), 'error');
            }

        }else{

            Factory::getApplication()->enqueueMessage('Scanner verwijderd');
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=scanners');

    }

    function apply()
    {

        $app 	    = Factory::getApplication();
        $jinput     = $app->input;
        $model	    = $this->getModel('scanners');
        $data   	= $jinput->post->getArray();

        $data['events'] = json_encode($this->input->get('event', array(), 'int'));
        $data['tickets'] = json_encode($this->input->get('ticket', array(), 'int'));

        if ($model->store($data))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=scanners&task=edit&cid=' . $model->getScannerID(), Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED'));
        } else {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=scanners', Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED_FAILED'));
        }

    }

    public function save($cachable = false, $urlparams = [])
    {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Scanners', Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED'));
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Scanners');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

}