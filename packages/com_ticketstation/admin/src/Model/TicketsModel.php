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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Model\Mixin\ListState;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Client\ClientHelper;
use Joomla\Filesystem\File;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketcleaner;

/**
 * Ticketstation Tickets Model
 * @since 0.0.1
 */
class TicketsModel extends ListModel
{
    use ListState;

    function __construct()
    {
        parent::__construct();

        $app    	= Factory::getApplication();

        $this->populateListState('tickets', [
            'event' => ['filter_ordering_t', 0, 'int'],
            'state' => ['filter_state', 3, 'int'],
            'venue' => ['filter_ordering_venue', 0, 'int'],
        ]);

        $array = $app->getInput()->get('cid', array(0), 'array');
        $this->id = (int)$array[0];
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

    function getListQuery($parent=true)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $app    	= Factory::getApplication();

        $eventid 		  = (int) $this->getState('filter.event');
        $filter_state	  = (int) $this->getState('filter.state');
        $filter_venue	  = (int) $this->getState('filter.venue');

        $query = $db->getQuery(true);

        $query->select(array('a.*', 'b.eventname', 'b.eventcode', 'v.venue', 'v.city', 'v.id AS venueid'));
        $query->from($db->quoteName('#__ticketstation_tickets', 'a'));
        $query->join('LEFT', $db->quoteName('#__ticketstation_events', 'b') . ' ON (' . $db->quoteName('a.eventid') . ' = ' . $db->quoteName('b.eventid').')');
        $query->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON (' . $db->quoteName('a.venue') . ' = ' . $db->quoteName('v.id').')');

        if($parent == true && $filter_state == 3)
        {
            $query->where($db->quoteName('a.published') . ' >= 0');
        }
        else if($parent == true && $filter_state == 0)
        {
            $query->where($db->quoteName('a.published') . ' = 0');
        }
        else if($parent == true && $filter_state == 1)
        {
            $query->where($db->quoteName('a.published') . ' = 1');
        }
        else
        {
            $query->where($db->quoteName('a.published') . ' >= 0');
        }

        if($filter_venue > 0 && $parent == true)
        {
            $query->where($db->quoteName('a.venue') . ' = ' . (int)$filter_venue);
        }
        else
        {
            $query->where($db->quoteName('a.venue') . ' >= 0');
        }

        if($parent)
        {
            $query->where($db->quoteName('a.parent') . ' = 0');
        }
        else{
            $query->where($db->quoteName('a.parent') . ' != 0');
        }

        if($eventid == 0)
        {
            $query->where($db->quoteName('a.eventid') . ' > 0');
        }
        else
        {
            $query->where($db->quoteName('a.eventid') . ' = '. (int)$eventid);
        }

        return $query;
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
        $this->_total = $this->_getListCount($this->getListQuery(true), $this->getState('limitstart'), $this->getState('limit'));

        return $this->_total;
    }

    function getList()
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getListQuery(true), $this->getState('limitstart'), $this->getState('limit' ));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function getSold()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select(array('ticketid', 'COUNT(orderid) AS soldtickets'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        //$query->where($db->quoteName('paid') . ' = 1');
        $query->where(Refund::heldSql());
        $query->group($db->quoteName('ticketid'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    function getChilds()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery($this->getListQuery(false));
        $this->data = $db->loadObjectList();

        return $this->data;
    }

    function publish($cid = array(), $publish = 1)
    {
        if (count( $cid ))
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            ## Make cids safe, against SQL injections
            ArrayHelper::toInteger($cid);
            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $query = $db->getQuery(true);

            $fields = array(
                $db->quoteName('published') . ' = '.(int) $publish
            );

            $conditions = array(
                $db->quoteName('ticketid') . ' IN ('.$cids.')'
            );

            $query->update($db->quoteName('#__ticketstation_tickets'))->set($fields)->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result)
            {
                return false;
            }

        }
        return true;
    }

    function remove($cid)
    {
        if (count( $cid ))
        {
            ArrayHelper::toInteger($cid);

            ## Implode cids for more actions (when more selected)
            $cids = implode( ',', $cid );

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select(array('orderid', 'ordercode'));
            $query->from($db->quoteName('#__ticketstation_orders'));
            $query->where($db->quoteName('ticketid') . ' IN ('.$cids.')');

            $db->setQuery($query);
            $data = $db->loadObjectList();

            ## Loop the ticketnumbers for deletion
            for ($i = 0, $n = count($data); $i < $n; $i++ )
            {
                $row  = $data[$i];

                $this->_deleteOrderFiles($row->ordercode);
            }

            $query = $db->getQuery(true);

            $conditions = array(
                $db->quoteName('ticketid') . ' IN ('.$cids.')'
            );

            $query->delete($db->quoteName('#__ticketstation_tickets'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            if (!$result)
            {
                return false;
            }

            foreach ($cid as $ticketid)
            {
                $this->_deleteDesignFiles((int) $ticketid);
            }

            $query = $db->getQuery(true);

            $query->delete($db->quoteName('#__ticketstation_orders'));
            $query->where($conditions);

            $db->setQuery($query);

            $result = $db->execute();

            ## When query goes wrong.. Show message with error.
            if (!$result)
            {
                return false;
            }

            return true;
        }
    }

    /**
     * Deletes the generated PDFs of an order (Ticket-<ordercode>.pdf and Tickets-<ordercode>.pdf).
     * A missing file is no error: they are made on demand.
     */
    function _deleteOrderFiles($ordercode)
    {
        $folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

        foreach (['Ticket-', 'Tickets-'] as $prefix)
        {
            $file = $folder . $prefix . (int) $ordercode . '.pdf';

            if (is_file($file))
            {
                @unlink($file);
            }
        }
    }

    /**
     * Deletes the uploaded design (eTicket-<ticketid>.jpg or .pdf) of a ticket.
     */
    function _deleteDesignFiles($ticketid)
    {
        $folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/';

        foreach (['jpg', 'pdf'] as $ext)
        {
            $file = $folder . 'eTicket-' . (int) $ticketid . '.' . $ext;

            if (is_file($file))
            {
                @unlink($file);
            }
        }
    }

    function cleanup()
    {

        $cleaner = new Ticketcleaner;
        $cleaner->cleanup();

        $msg = $cleaner->error();
        $count = $cleaner->counter();

        if ($msg == '')
        {
            return $count;
        }
        else
        {
            return false;
        }
    }

    function resetScanstate($cid = [])
    {

        if (count($cid))
        {
            $cids = implode(',', $cid);

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $fields = [
                $db->quoteName('scanned') . ' = 0',
                $db->quoteName('scandate') . ' = 0',
                $db->quoteName('scanner') . ' = 0',
            ];

            $conditions = [
                $db->quoteName('ticketid') . ' IN (' . $cids . ')',
            ];

            $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

            $db->setQuery($query);

            if( ! $result = $db->execute()) {
                return false;
            } else {
                return true;
            }

        }
    }

}