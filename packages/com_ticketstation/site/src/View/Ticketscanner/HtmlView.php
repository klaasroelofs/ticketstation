<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Ticketscanner;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

class HtmlView extends BaseHtmlView {

    public $ticket;
    public $event;
    public $sold;
    public $user;
    public $scanner;

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

        if ($user->guest)
        {
            $return = base64_encode(Uri::getInstance());
            $login_url_with_return = Route::_('index.php?option=com_users&view=login&return=' . $return, false);
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_LOGIN'), 'notice');
            $app->redirect($login_url_with_return, 403);
        }

        $jinput   = $app->getInput();
        $eventid  = $jinput->getInt('eventid', 0);
        $ticketid = $jinput->getInt('ticketid', 0);
        $scanner  = $this->get('scanner');

        // Only an assigned event or ticket can be opened in the scanner.
        $allowed = $scanner && (
            ($eventid > 0 && Scanner::mayScanEvent($scanner, $eventid))
            || ($eventid === 0 && $ticketid > 0 && Scanner::mayScanTicket($scanner, $ticketid))
        );

        if ($allowed) {
            if ($eventid > 0) {
                $this->event = $this->get('eventdata');
                $this->sold  = $this->get('SoldbyEvent');
            } else {
                $this->ticket = $this->get('ticketdata');
                $this->sold   = $this->get('SoldbyTicket');
            }
        }

        if (!$allowed || (!$this->event && !$this->ticket))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETSCANNING_SCAN_NOT_ASSIGNED'), 'error');
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''), false));
        }

        $this->scanner = $scanner;
        $this->user    = $user;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}
