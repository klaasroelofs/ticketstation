<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

/**
 * Ticketstation Component Controller
 * @since  0.0.2
 */
class DisplayController extends BaseController {

    public function display($cachable = false, $urlparams = array()) {
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $viewName = $this->input->getCmd('view', 'login');
        $viewFormat = $document->getType();

        if ($viewName === 'ticket') {
            $viewName = $this->resolveTicketView();
        }

        $view = $this->getView($viewName, $viewFormat);
        $view->setModel($this->getModel('Order'), true);

        return parent::display($cachable, $urlparams);
    }

    /**
     * view=ticket (the "Ticket" menu item type) is one entry point for both kinds of ticket
     * page: it hands the request to view=event (general admission, id=) or view=seatedevent
     * (seat chart, cid=), whichever fits the chosen ticket. Only parent tickets qualify; any
     * other id falls through to view=event, which reports the ticket as not available.
     *
     * @return  string  The view that renders the page.
     */
    private function resolveTicketView(): string
    {
        $id = $this->input->getInt('id', 0);
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('show_seatplans'))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . (int) $id)
            ->where($db->quoteName('parent') . ' = 0');

        $db->setQuery($query);
        $seated = $db->loadResult();

        if ($seated === null) {
            $id = 0;
        }

        $view = (int) $seated === 1 ? 'seatedevent' : 'event';

        $this->input->set('view', $view);
        $this->input->set($view === 'seatedevent' ? 'cid' : 'id', $id);

        return $view;
    }
/**
    public function display($cachable = false, $urlparams = array()) {
        $app = Factory::getApplication();
        $document = $app->getDocument();
        $viewName = $this->input->getCmd('view', 'login');
        $viewFormat = $document->getType();

        $view = $this->getView($viewName, $viewFormat);
	    $view->setModel($this->getModel('Message'), true);

        $view->document = $document;
        $view->display();
    }
*/
}