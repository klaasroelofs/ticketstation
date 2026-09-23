<?php

namespace Ticketstation\Component\Ticketstation\Site\View\Ticketscanner;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
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

    public $ticket;
    public $event;
    public $sold;
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

        $jinput   = Factory::getApplication()->getInput();
        $eventid = $jinput->get('eventid', '0', 'int');
        $ticketid = $jinput->get('ticketid', '0', 'int');
        $approved_for = $this->get('approvedfor');

        if (!empty($eventid)) {
            $this->event	= $this->get('eventdata');
            $this->sold = $this->get('SoldbyEvent');

            if(!in_array($eventid, json_decode($approved_for->events)))
            {
                Factory::getApplication()->enqueueMessage('Scan niet aan jou toegewezen!', 'error');
                $itemid = TicketstationFunctions::getSiteItemid();
                $app->redirect(Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : '')));
            }
        }

        if (!empty($ticketid)) {
            $this->ticket	= $this->get('ticketdata');
            $this->sold = $this->get('SoldbyTicket');

            if(!in_array($ticketid, json_decode($approved_for->tickets)))
            {
                Factory::getApplication()->enqueueMessage('Scan niet aan jou toegewezen!', 'error');
                $itemid = TicketstationFunctions::getSiteItemid();
                $app->redirect(Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : '')));
            }
        }


        $this->scanner_permissions = $this->get('scannerpermissions');

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}