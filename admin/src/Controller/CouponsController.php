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

//TODO: Save&Close doesn't function (acts as Apply). ?? Done with "return true" on line 159 ??

class CouponsController extends BaseController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Coupons';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask( 'add' , 'edit' );
        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'default');
        $jinput->set('view', 'coupons');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->input;
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'coupon');
        parent::display();
    }

    function publish()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

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
            $this->setRedirect('index.php?option=com_ticketstation&controller=coupons');
            return;
        }

        $model = $this->getModel('coupons');

        if($model->publish($cid, $publish))
        {
            if ($publish == 1) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=coupons', Text::_('COM_TICKETSTATION_COUPON_PUBLISHED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=coupons', Text::_('COM_TICKETSTATION_COUPON_UNPUBLISHED'));
            }

        } else {

            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_PUBLISHED_FAILED'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=coupons');

        }

    }

    function remove()
    {
        $this->checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        $model = $this->getModel('coupons');

        if(!$model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_DELETE_COUPON'), 'error');
            $app->redirect('index.php?option=com_ticketstation&controller=coupons');
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_DELETED'));
        $app->redirect('index.php?option=com_ticketstation&controller=coupons');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

}