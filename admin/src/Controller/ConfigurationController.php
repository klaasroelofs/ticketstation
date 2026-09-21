<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
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


class ConfigurationController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Configuration';

    public function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        //$this->registerTask('main');
    }

    public function main($cachable = false, $urlparams = array()) {


        return parent::display($cachable, $urlparams);
    }

    /**
     * Handle the apply task which saves the configuration settings and shows the page again
     */
    public function apply($cachable = false, $urlparams = []) {


        $app 	= Factory::getApplication();
        $jinput = $app->input;
        $post 	= $jinput->post->getArray();

        $post['valuta'] = $app->input->get('valuta', null, 'raw');

        $model = $this->getModel('Configuration', 'Administrator');

        if ($model->store($post))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_CONFIG_SAVED'));

        } else {

            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Configuration', Text::_('COM_TICKETSTATION_CONFIG_NOTSAVED'));

        }


    }
    /**
     * Handle the save task which saves the configuration settings and returns to the Control Panel page
     */
    public function save($cachable = false, $urlparams = []) {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation', Text::_('COM_TICKETSTATION_CONFIG_SAVED'));
    }
    /**
     * Handle the cancel task which doesn't save anything and returns to the Control Panel page
     */
    public function cancel($cachable = false, $urlparams = []) {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation');
    }
}