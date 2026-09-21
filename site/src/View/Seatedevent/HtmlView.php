<?php

namespace Ticketstation\Component\Ticketstation\Site\View\Seatedevent;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
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

        $app 	= Factory::getApplication();

        ## Getting the items into a variable
        $data	= $this->get('data');
        $config	= $this->get('config');
        $seats	= $this->get('cartitems');

        $tickets	= $this->get('tickets');
        $ticketdetails	= $this->get('ticketdetails');

        if(!$ticketdetails)
        {
            $app->enqueueMessage('Ticket niet beschikbaar.', 'error');
            $app->redirect(Route::_('index.php?com_ticketstation&view=upcoming'));
        }

        ## Getting specific items, depending on ticket kind
        if($data->multi_seat != 1) {

            $items	= $this->get('seats');
            $order	= $this->get('cart');

        }else{

            ## Check if an item has childs.
            $childs = $this->get('checkchilds');

            ## if childs > 0 than run the queries below.
            if($childs->total != 0){

                $items	= $this->get('nochilds');
                $order	= $this->get('cartnochilds');

                ## There are no childs, use this queries.
            }else{

                $items	= $this->get('nochilds');
                $order	= $this->get('cart');

            }

        }

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


        $this->tickets          = $tickets;
        $this->ticketdetails    = $ticketdetails;
        $this->order            = $order;
        $this->ordered          = $ordered;
        $this->seats            = $seats;
        $this->items            = $items;
        $this->data             = $data;
        $this->config           = $config;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}