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

        $view = $this->getView($viewName, $viewFormat);
        $view->setModel($this->getModel('Order'), true);

        return parent::display($cachable, $urlparams);
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