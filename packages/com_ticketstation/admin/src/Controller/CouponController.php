<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


class CouponController extends FormController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Coupon';

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
        $jinput->set('view', 'coupon');
        parent::display();
    }

    function apply()
    {

        $app 	    = Factory::getApplication();
        $model	    = $this->getModel('coupon');
        $data       = $this->input->post->get('jform', array(), 'array');

        $coupon_name  = $data['coupon_name'];
        $coupon_id  = $data['coupon_id'];
        $couponcode = $data['coupon_code'];

        if (!empty($data['coupon_valid_to'])) {
            $data['coupon_valid_to'] = date('Y-m-d', strtotime($data['coupon_valid_to']));
        } else {
            $data['coupon_valid_to'] = null;
        }

        //Check if entered couponcode is unique
        if ((!empty($couponcode)) && (!$model->checkCouponcode($couponcode, $coupon_id)) || ($coupon_name == ''))
        {
            if (!$model->checkCouponcode($couponcode, $coupon_id)) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_CODE_EXISTS'), 'error');
            } else {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_NAME_EMPTY'), 'error');
            }

            // Save the data in the session.
            $session = $app->getSession();
            $session->set('postdata', $data);

            // Redirect back to the edit screen.
            $this->display();
            if ($this->getTask() == 'save') {
                return false;
            }

        } else {

            if ($model->store($data)) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=coupon&layout=edit&cid=' . $model->getCouponID(), Text::_('COM_TICKETSTATION_COUPON_SAVED'));
            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=coupon&layout=edit&cid=' . $model->getCouponID(), Text::_('COM_TICKETSTATION_COUPON_SAVED_FAILED', 'error'));
            }

            return true;

        }

    }

    public function save($cachable = false, $urlparams = [])
    {
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Coupons', Text::_('COM_TICKETSTATION_COUPON_SAVED'));
        }
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Coupons');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation');
    }

}