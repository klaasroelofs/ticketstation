<?php

namespace Ticketstation\Component\Ticketstation\Site\View\Statistics;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

class HtmlView extends BaseHtmlView {

    public $ticket;
    public $events;
    public $user;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $jinput	= $app->getInput();
        $user = $this->getCurrentUser();

        if ($user->id == 0) {
            $loggedIn = false;
        } else {
            $loggedIn = true;
        }

        if (!$loggedIn)
        {
            $return = base64_encode(Uri::getInstance());
            $login_url_with_return = Route::_('index.php?option=com_users&view=login&return=' . $return, false);
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_LOGIN'), 'notice');
            $app->redirect($login_url_with_return, 403);
        }

        $isadmin = $user->authorise('core.manage');

        $this->config = (new Config)->getPartialConfig([
            'show_waitinglist', 'dateformat', 'priceformat', 'valuta', 'show_quantity_eventlist', 'show_price_eventlist', 'transactioncosts', 'transcosts', 'variable_transcosts',
        ]);

        $this->ticket	= $this->get('ticket');

        //SALES
        $salesstats = $jinput->get('salesstats', 'ticket', 'string');
        $salesperticket = $jinput->get('salesperticket', '1', 'int');

        if ($salesstats == 'ticket') {
            $this->soldtickets = $this->get('soldticketsbyticket');
            $this->startingtickets = $this->ticket->starting_total_tickets;
            $this->availabletickets = $this->startingtickets - $this->soldtickets;
            $this->ordercount = $this->get('ordercountbyticket');
            $this->unfinished = $this->get('unfinishedordersbyticket');
            $this->lastorder = $this->get('lastorderbyticket');
            $this->orders_per_customer = $this->get('clientswithmultipleordersbyticket');

            if ($isadmin) {
                $this->pdfnotcreated = $this->get('Pdfnotcreatedbyticket');
                $this->pdfnotsent = $this->get('Pdfnotsentbyticket');
            }

        } elseif ($salesstats == 'event') {
            $soldticketsgrouped = $this->get('soldticketsbyevent');
            $this->soldtickets = array_sum(array_column($soldticketsgrouped, 'total_tickets_sold'));

            $ticketinfogrouped = $this->get('ticketsbyevent');

            $this->startingtickets = array_sum(array_column($ticketinfogrouped, 'starting_total_tickets'));
            $this->availabletickets = array_sum(array_column($ticketinfogrouped, 'starting_total_tickets')) - $this->soldtickets;

            $ordercountgrouped = $this->get('ordercountbyevent');
            $this->ordercount = array_sum(array_column($ordercountgrouped, 'orders'));

            $this->unfinished = $this->get('unfinishedordersbyevent');
            $this->lastorder = $this->get('lastorderbyevent');
            $this->orders_per_customer = $this->get('clientswithmultipleordersbyevent');

            if ($isadmin) {
                $this->pdfnotcreated = $this->get('Pdfnotcreatedbyevent');
                $this->pdfnotsent = $this->get('Pdfnotsentbyevent');
            }

            if ($salesperticket == 1) {
                $this->soldticketsgrouped = $soldticketsgrouped;
                $this->ordercountgrouped = $ordercountgrouped;
                $this->ticketinfogrouped = $ticketinfogrouped;
            }

        }
        $this->salesstats = $salesstats;
        $this->salesperticket = $salesperticket;

        //SCANS
        $scanstats = $jinput->get('scanstats', 'ticket', 'string');

        if ($scanstats == 'ticket') {
            $this->scannedperhour = $this->get('scannedperhourticket');
            $this->scannedperscanner = $this->get('scannedperscannerticket');
        } elseif ($scanstats == 'event') {
            $this->scannedperhour = $this->get('scannedperhourevent');
            $this->scannedperscanner = $this->get('scannedperscannerevent');
        }
        $this->scannedtotal = array_sum(array_column($this->scannedperhour, 'total_scanned'));
        $this->scanstats = $scanstats;

        $this->user = $user;
        $this->isadmin = $isadmin;
        $this->downloaded = $this->get('Downloadedticketsbyticket');
        $this->clientswithoutorder = $this->get('clientswithoutorder');
        $this->mollie     = $this->get('mollie');

        //Transactioncosts
        // variable_transcosts == 2: transaction costs are switched off, so none are received.
        $transcost_fee = ($this->config->variable_transcosts == 2) ? 0 : $this->config->transactioncosts;

        $this->transcost_received = round($this->ordercount * $transcost_fee, 2);
        $this->transcost_paid = round($this->ordercount * $this->mollie->trans_cost, 2);
        $this->transcost_profit = round($this->ordercount * ($transcost_fee - $this->mollie->trans_cost), 2);

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}