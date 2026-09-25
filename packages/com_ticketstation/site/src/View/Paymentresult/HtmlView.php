<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Paymentresult;

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

        $ordercode = Factory::getApplication()->getInput()->get('ordercode', '0', 'int');

        // Only the browser session that completed this order may see its details (name,
        // email) or the download button - otherwise guessing an ordercode in the URL would
        // be enough to see someone else's data, same as with the ticket download itself.
        $stored     = Factory::getApplication()->getSession()->get('ticketstation.authorized_ordercode');
        $authorized = ($ordercode !== 0) && ($stored !== null) && ((int) $stored === $ordercode);

        $this->ordercode        = $ordercode;
        $this->authorized       = $authorized;
        $this->mollieconfig     = $this->get('mollie');
        $this->contactEmail     = (new Config)->getContactEmail();

        if ($authorized) {
            $this->data   = $this->get('data');
            $this->unpaid = $this->get('unpaid');
        } else {
            $this->data   = [];
            $this->unpaid = null;
        }

        parent::display($tpl);

    }

}