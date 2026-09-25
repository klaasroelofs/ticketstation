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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Utilities\ArrayHelper;

/**
 * Ticketstation Coupons Model
 * @since 0.0.1
 */
class CouponsModel extends BaseDatabaseModel
{
    function __construct()
    {
        parent::__construct();

        $app    	= Factory::getApplication();

        ## Get the pagination request variables
        $limit      = $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
        $limitstart = $app->getUserStateFromRequest( 'products.limitstart', 'limitstart', 0, 'int' );

        ## In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
        }

        return $this->_pagination;
    }

    function getTotal()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_coupons'));

        $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_coupons'));

        $db->setQuery($query, $this->getState('limitstart'), $this->getState('limit' ));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_coupons'));
        $query->where($db->quoteName('coupon_id') . ' = '. $db->quote((int)$this->id));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
    }

    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = '. $db->quote(1));

        $db->setQuery($query);
        $data = $db->loadObject();

        return $data;
    }

    function publish($cid = array(), $publish = 1)
    {
        ## Count the cids
        if (count( $cid ))
        {
            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $fields = array(
                $db->quoteName('published') . ' = ' . $db->quote((int) $publish)
            );

            $conditions = array(
                $db->quoteName('coupon_id') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_coupons'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

        }

        return true;
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

    function remove($cid)
    {
        if (count( $cid )) {

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('coupon_id') . ' IN ( '.$cids.' )',
            );

            $query->delete($db->quoteName('#__ticketstation_coupons'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result) {
                return false;
            }

            return true;

        }

    }

}