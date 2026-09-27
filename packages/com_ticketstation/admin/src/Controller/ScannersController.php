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
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


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
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'scanners');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'form');
        $jinput->set('view', 'scanners');
        parent::display();
    }



    function remove() {


        $app 	= Factory::getApplication();
        $post 	= $app->getInput()->post->getArray();

        ArrayHelper::toInteger($post['cid']);

        $model = $this->getModel('scanners');

        if(!$model->remove($post['cid'])) {

            if( $model->getError()  == null ){
                Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_SCANNER_DELETE_FAILED'), 'error');
            }else{
                Factory::getApplication()->enqueueMessage($model->getError(), 'error');
            }

        }else{

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_SCANNER_DELETED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=scanners');

    }

    function apply()
    {

        $app 	    = Factory::getApplication();
        $jinput     = $app->getInput();
        $model	    = $this->getModel('scanners');
        $data   	= $jinput->post->getArray();

        $data['events'] = json_encode($this->input->get('event', array(), 'int'));
        $data['tickets'] = json_encode($this->input->get('ticket', array(), 'int'));

        // Every scanner needs a user of its own. The form validator already asks for one and the user
        // list leaves out users of other scanners; this also catches a request that skipped both.
        $id     = (int) ($data['id'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $error  = null;

        if ($userid <= 0)
        {
            $error = 'COM_TICKETSTATION_SCANNING_USER_REQUIRED';
        }
        elseif ($model->isUserTaken($userid, $id))
        {
            $error = 'COM_TICKETSTATION_SCANNING_USER_TAKEN';
        }

        if ($error)
        {
            // Back to the form; for a new scanner edit without an id, since the CSRF gate refuses a GET for add.
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=scanners&task=edit' . ($id > 0 ? '&cid=' . $id : ''), Text::_($error), 'error');

            return false;
        }

        if ($model->store($data))
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=scanners&task=edit&cid=' . $model->getScannerID(), Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED'));

            return true;
        }

        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=scanners', Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED_FAILED'), 'error');

        return false;
    }

    public function save($cachable = false, $urlparams = [])
    {
        // Only a successful save goes back to the list; otherwise keep the redirect and message of apply()
        if ($this->apply())
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Scanners', Text::_('COM_TICKETSTATION_SCANNING_SCANNER_SAVED'));
        }
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