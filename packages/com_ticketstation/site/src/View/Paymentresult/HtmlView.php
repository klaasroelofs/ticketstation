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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Calendar;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;



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
        $this->contactEmail     = (new Config)->getContactEmail();

        $this->canRetry    = false;
        $this->notFinished = false;

        if ($authorized) {
            $this->data   = $this->get('data');
            $this->unpaid = $this->get('unpaid');

            // "Pay again" only after an attempt that Mollie reported as failed, cancelled or
            // expired (5): an attempt that is still open could otherwise be paid twice.
            $attempt = (new PaymentAPI($ordercode))->getTempTransactionByOrdercode($ordercode);

            // The wait page gives up after a while (&notfinished=1): a customer who went back from
            // the provider's page without paying leaves an attempt that stays open, and is offered
            // a new try too, with a warning not to pay twice.
            $this->notFinished = Factory::getApplication()->getInput()->getInt('notfinished', 0) === 1
                && $attempt && in_array((int) $attempt->processed, [0, 3, 4], true);

            $this->canRetry = $this->unpaid->total > 0
                && $attempt && ((int) $attempt->processed === 5 || $this->notFinished);

            // "Add to calendar" only when the order has dated events
            $this->hasCalendar = Calendar::events($ordercode) !== [];

            // "Add to Apple/Google Wallet" once the order is paid and its tickets exist
            $this->walletButtons = $this->unpaid->total > 0
                ? ''
                : Wallet::buttons($ordercode, false, Factory::getApplication()->getLanguage()->getTag());
        } else {
            $this->data          = [];
            $this->unpaid        = null;
            $this->hasCalendar   = false;
            $this->walletButtons = '';
        }

        parent::display($tpl);

    }

}