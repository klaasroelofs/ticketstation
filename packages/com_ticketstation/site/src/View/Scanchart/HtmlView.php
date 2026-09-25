<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Scanchart;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

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

        // Only the seat chart of a ticket assigned to this scanner.
        $scanner  = Scanner::getByUserId((int) $user->id);
        $ticketid = $app->getInput()->getInt('id', 0);

        $this->items	= $scanner && Scanner::mayScanTicket($scanner, $ticketid) ? $this->get('items') : [];

        if (empty($this->items))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETSCANNING_SCAN_NOT_ASSIGNED'), 'error');
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''), false));
        }

        $this->user		= $user;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}