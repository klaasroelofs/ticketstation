<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Ticketstation Coupon Model
 * @since 0.0.1
 */
class CouponModel extends AdminModel
{
    /**
     * @var int|\Joomla\Database\DatabaseInterface|mixed
     */
    private $couponid;

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form. [optional]
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not. [optional]
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_ticketstation.coupon', 'coupon', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form))
        {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_ticketstation.edit.coupon.data', array());

        if (empty($data))
        {
            $session = $app->getSession();
            $session_postdata = $session->get('postdata');
            if (!empty($session_postdata)) {
                $data = (object) $session_postdata;
                $session->clear('postdata');
            } else {
                $input      	= $app->getInput()->get('cid', array(0), 'array');
                $this->couponid	= (int)$input[0];
                $data 			= $this->getItem($this->couponid);
            }
        }

        $this->preprocessData('com_ticketstation.coupon', $data);

        return $data;
    }

    function getItem($pk = null)
    {

        $app  			= Factory::getApplication();
        $input      	= $app->getInput()->get('cid', array(0), 'array');
        $this->couponid	= (int)$input[0];

        return parent::getItem($this->couponid);

    }

    function checkCouponcode($couponcode, $cid)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('coupon_id');
        $query->from($db->quoteName('#__ticketstation_coupons'));
        $query->where($db->quoteName('coupon_code') . ' = '. $db->quote($couponcode));

        $db->setQuery($query);
        $result = $db->loadResult();

        if (!empty($result)) {
            if ($result <> $cid){
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }

    }

    public function store($data)
    {
        $table = $this->getTable();

        // Bind the data.
        if (!$table->bind($data)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_BIND_FAILED'), 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Check the data.
        if (!$table->check()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_CHECK_FAILED'), 'error');
            //$this->setError($table->getError());
            return false;
        }

        // Store the data.
        if (!$table->store()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_STORE_FAILED') . ' ' . $table->getError(), 'error');
            //$this->setError($table->getError());
            return false;
        }

        if ($data['coupon_id'] != 0) {
            $this->coupon_id = $data['coupon_id'];
        } else {
            $this->coupon_id = $this->_db->insertid();
        }

        return true;

    }

    function getCouponID()
    {
        return $this->coupon_id;
    }

}