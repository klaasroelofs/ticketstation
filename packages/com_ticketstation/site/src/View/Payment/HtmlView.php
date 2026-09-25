<?php

namespace Ticketstation\Component\Ticketstation\Site\View\Payment;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentMethod;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;

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

        $app    = Factory::getApplication();
        $db     = Factory::getContainer()->get('DatabaseDriver');

        ## Getting the global DB session
        $session = $app->getSession();
        ## Gettig the orderid if there is one.
        $ordercode = $session->get('ordercode');

        $model	= $this->getModel('payment');

        $query = $db->getQuery(true);
        ## Count the tickets in the order table.
        $query->select(array('COUNT(orderid) AS total'));
        $query->from($db->quoteName('#__ticketstation_orders'));
        $query->where($db->quoteName('ordercode')." = ".$db->quote($ordercode));

        $db->setQuery($query);
        $orders = $db->loadObjectList();

        ## A finished waiting-list-only signup clears the session ordercode (see
        ## CheckoutController::save()), so look its rows up by the remembered ordercode, once.
        $waitcode = $session->get('ticketstation.waitinglist_ordercode');

        if ($waitcode && empty($orders[0]->total))
        {
            $ordercode = $waitcode;
        }

        $session->clear('ticketstation.waitinglist_ordercode');

        $query = $db->getQuery(true);
        ## Check if there are any tickets on the waiting list.
        $query->select(array('COUNT(id) AS total'));
        $query->from($db->quoteName('#__ticketstation_waitinglist'));
        $query->where($db->quoteName('ordercode')." = ".$db->quote($ordercode));
        $query->where($db->quoteName('processed')." = ".$db->quote(0));

        $db->setQuery($query);
        $waitlist = $db->loadObjectList();

        $items	        = $this->get('data');
        $config	        = $this->get('config');
        $mollieconfig   = $this->get('mollie');

        if ($config->pro_installed == 1)
        {
            $coords	  = $this->get('extdata');
            $required  = $this->get('datacheck');

            ## Assign data to the view ;)
            $this->coords = $coords;
            $this->required = $required;
        }

        $this->items    = $items;
        $this->waitlist = $waitlist;
        $this->config   = $config;
        $this->mollieconfig = $mollieconfig;

        parent::display($tpl);

    }

}