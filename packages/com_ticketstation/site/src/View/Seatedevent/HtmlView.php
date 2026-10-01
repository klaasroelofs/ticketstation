<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Seatedevent;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanSettings;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;


class HtmlView extends BaseHtmlView {


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $app 	= Factory::getApplication();

        ## Getting the items into a variable
        $data	= $this->get('data');
        $config	= $this->get('config');
        $seats	= $this->get('cartitems');

        $tickets	= $this->get('tickets');
        $ticketdetails	= $this->get('ticketdetails');

        if (!$ticketdetails || Shop::isClosed())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_NOT_AVAILABLE'), 'error');
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));
        }

        ## With online payments off a chart with paid seats is only sold at the box office.
        if (Shop::boxOfficeOnly((int) $ticketdetails->ticketid, false))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_TICKET'), 'info');
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));
        }

        ## Every seat of the chart: free seats and section seats.
        $items	= $this->get('seats');

        $db = Factory::getContainer()->get('DatabaseDriver');
        ## Getting the global DB session
        $session = Factory::getApplication()->getSession();
        $session_ordercode = $session->get('ordercode');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = '. $db->quote($session_ordercode));

        $db->setQuery($query);
        $ordered = $db->loadObjectList();

        ## With price categories the customer picks one per free seat.
        $pricechoice = (bool) SeatplanSettings::priceCategories((int) $ticketdetails->ticketid);

        $this->pricechoice      = $pricechoice;

        $this->tickets          = $tickets;
        $this->ticketdetails    = $ticketdetails;
        $this->ordered          = $ordered;
        $this->seats            = $seats;
        $this->items            = $items;
        $this->data             = $data;
        $this->config           = $config;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}