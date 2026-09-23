<?php

namespace Ticketstation\Component\Ticketstation\Site\View\Event;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


class HtmlView extends BaseHtmlView {


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $db = Factory::getContainer()->get('DatabaseDriver');
        $app 	= Factory::getApplication();

        $items	= $this->get('items');
        $childs = $this->get('childs');
        $config	= $this->get('config');
        $soldtickets = $this->get('soldtickets');

        if(!$items)
        {
            $app->enqueueMessage('Ticket niet beschikbaar.', 'error');
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));
        }

        $n = count($childs);

        if ($n > 0)
        {
            $query = $db->getQuery(true);
            $query->select(array('ticketid', 'ticketname'));
            $query->from($db->quoteName('#__ticketstation_tickets'));
            $query->where($db->quoteName('published')." = ".$db->quote(1));
            $query->where($db->quoteName('parent')." = ".$db->quote($items->ticketid));
            $query->where($db->quoteName('totaltickets')." > ".$db->quote(0));
            $query->order('ticketname');

            $db->setQuery($query);

            $childlist[]	   = HTMLHelper::_('select.option',  '0', Text::_( 'SELECT TICKET' ), 'ticketid', 'ticketname' );
            $childlist	       = array_merge( $childlist, $db->loadObjectList() );
            $lists['tickets']  = HTMLHelper::_('select.genericlist',  $childlist, 'ticketid', 'class="inputbox" size="1" ', 'ticketid',
                'ticketname', 0);
        }
        else
        {
            $query = $db->getQuery(true);
            $query->select(array('ticketid', 'ticketname'));
            $query->from($db->quoteName('#__ticketstation_tickets'));
            $query->where($db->quoteName('published')." = ".$db->quote(1));
            $query->where($db->quoteName('parent')." = ".$db->quote($items->ticketid));
            $query->order('ticketname');

            $db->setQuery($query);

            $childlist[]	   = HTMLHelper::_('select.option',  $items->ticketid, $items->ticketname, 'ticketid', 'ticketname' );
            $lists['tickets']  = HTMLHelper::_('select.genericlist',  $childlist, 'ticketid', 'class="inputbox" size="1" ', 'ticketid',
                'ticketname', 0);
        }

        ## Starting a session.
        $session = $app->getSession();
        ## Gettig the orderid if there is one.
        $ordercode = $session->get('ordercode');

        $query = $db->getQuery(true);
        $query->select(array('COUNT(orderid) AS total'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode')." = ".$db->quote((int)$ordercode));
        $query->where($db->quoteName('paid')." = 0");
        $db->setQuery($query);
        $ticket = $db->loadObject();

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = '. $db->quote($ordercode));

        $db->setQuery($query);
        $ordered = $db->loadObjectList();


        $this->ticket       = $ticket;
        $this->ordered      = $ordered;
        $this->items        = $items;
        $this->childs       = $childs;
        $this->config       = $config;
        $this->lists        = $lists;
        $this->soldtickets  = $soldtickets;

        parent::display($tpl);
    }

}