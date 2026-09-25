<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;

/**
 * Ticketstation Invoices Model
 * @since 2.0.11
 */
class InvoicesModel extends BaseDatabaseModel
{
    function __construct()
    {
        parent::__construct();

        $app = Factory::getApplication();

        $limit      = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest('invoices.limitstart', 'limitstart', 0, 'int');

        $limitstart = ($limit != 0 ? (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);
    }

    function getPagination()
    {
        if (empty($this->_pagination))
        {
            $this->_pagination = new Pagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
        }

        return $this->_pagination;
    }

    private function getBaseQuery($db)
    {
        return $db->getQuery(true)
            ->select(['i.*', 'c.name AS client_name', 'c.firstname AS client_firstname', 'c.emailaddress AS client_email'])
            ->from($db->quoteName('#__ticketstation_invoices', 'i'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('i.userid'));
    }

    function getTotal()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $this->getBaseQuery($db);

        $this->_total = $this->_getListCount($query, $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $this->getBaseQuery($db)->order($db->quoteName('i.invoicedate') . ' DESC');

        $db->setQuery($query, $this->getState('limitstart'), $this->getState('limit'));

        return $db->loadObjectList();
    }

    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);

        return $db->loadObject();
    }
}
