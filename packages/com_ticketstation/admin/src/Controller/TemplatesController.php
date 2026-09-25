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
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class TemplatesController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Venues';
    /**
     * @var mixed|null
     */
    private $id;

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);


    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'templates');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'form');
        $jinput->set('view', 'templates');
        parent::display();
    }

    /**
     * Handle the apply task which saves the configuration settings and shows the page again
     */
    public function apply($cachable = false, $urlparams = []) {


        $app 	= Factory::getApplication();
        $jinput = $app->getInput();
        $post 	= $jinput->post->getArray();

        $post['mailbody'] = $jinput->get('mailbody', null, 'raw');

        $this->id = $jinput->get('cid', '0', 'INT');

        $model = $this->getModel('templates');

        if ($model->store($post))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . $this->id, Text::_('COM_TICKETSTATION_TEMPLATES_SAVED'));

        } else {

            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . $this->id, Text::_('COM_TICKETSTATION_TEMPLATES_NOTSAVED'));

        }


    }
    /**
     * Handle the save task which saves the configuration settings and returns to the Templates view
     */
    public function save($cachable = false, $urlparams = []) {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=templates', Text::_('COM_TICKETSTATION_TEMPLATES_SAVED'));
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=templates');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }
}