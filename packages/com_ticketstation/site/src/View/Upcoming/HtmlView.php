<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Upcoming;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;

class HtmlView extends BaseHtmlView {


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null)
    {
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $app 	 = Factory::getApplication();

        $this->config = (new Config)->getPartialConfig([
            'show_waitinglist', 'dateformat', 'priceformat', 'valuta', 'show_quantity_eventlist', 'show_price_eventlist', 'show_venue', 'transactioncosts', 'transcosts', 'variable_transcosts',
        ]);

        $items      = $this->get('list');
        $events	    = $this->get('events');
        $upcoming	= $this->get('upcomingevents');
        $added      = $this->get('added');
        $sold       = $this->get('sold');
        $pagination = $this->get('pagination');
        $mollie     = $this->get('mollie');

        // In test mode only logged-in users see the tickets (with a warning that the mode is
        // on); other visitors only see the events whose sale is about to start.
        if (Shop::isClosed())
        {
            $items  = [];
            $events = [];
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

        $this->ticket     = $ticket;
        $this->items      = $items;
        $this->events	  = $events;
        $this->upcoming	  = $upcoming;
        $this->added      = $added;
        $this->sold       = $sold;
        $this->pagination = $pagination;
        $this->testmode   = Shop::inTestMode() && !Shop::isClosed();
        $this->mollie     = $mollie;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}