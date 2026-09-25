<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Cart;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;


class HtmlView extends BaseHtmlView {


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $items    = $this->get('data');
        $waiters  = $this->get('waiters');
        $config   = $this->get('config');
        $requests = $this->get('RequiredInformation');
        $coords  = $this->get('extdata');
        $require = $this->get('datacheck');

        $this->items    = $items;
        $this->waiters  = $waiters;
        $this->config   = $config;
        $this->requests = $requests;
        $this->coords   = $coords;
        $this->require  = $require;

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}