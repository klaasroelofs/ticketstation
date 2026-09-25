<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Ticketscanning;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;

class HtmlView extends BaseHtmlView {

    public $tickets;
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

        $this->config = (new Config)->getPartialConfig([
            'show_waitinglist', 'dateformat', 'priceformat', 'valuta', 'show_quantity_eventlist', 'show_price_eventlist', 'transactioncosts', 'transcosts', 'variable_transcosts',
        ]);

        $this->tickets	= $this->get('list');
        $this->events	= $this->get('events');
        $this->user		= $user;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}