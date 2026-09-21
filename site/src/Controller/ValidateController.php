<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Mollie\Api\MollieApiClient;
use Ticketstation\Component\Ticketstation\Administrator\Helper\confirmation;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Validate Controller
 * @since  0.9.4
 */
class ValidateController extends BaseController
{
    private $id;
    private $ordercode;

    function __construct()
    {
        parent::__construct();

        $jinput          = Factory::getApplication()->getInput();
        $this->ordercode = $jinput->get('oc', '', 'int');
        $this->id        = $jinput->get('cid', '', 'int');
    }

    /**
     * Below the option for paylater will be checked.
     * Check if this is an payment order
     *
     * @since 1.0.0
     */
    function waitinglist()
    {
        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $order  = $jinput->get('order', '', 'string');

        if ($order == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        $decoded   = base64_decode($order);
        $ordercode = explode('=', $decoded);

        if ( ! isset($ordercode[1]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        $waitinglist = new Waitinglist;

        if ( ! $waitinglist->confirm($ordercode[1]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_VALIDATION_WAITINGLIST_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        $msg = Text::_('COM_TICKETSTATION_VALIDATION_WAITINGLIST_COMPLETED');
        $app->redirect('index.php?option=com_ticketstation&view=upcoming', $msg);

        return true;
    }

    /**
     * Customer is requesting a payment, let's check his previous order.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    function pay()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $paylater = $jinput->get('order', null, 'string');

        if (empty($paylater))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        $decoded   = base64_decode($paylater);
        $request   = explode('=', $decoded);
        $ordercode = $request[1];

        // Checking if there is an ordercode.
        if ( ! isset($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        // Checking if there is an order.
        if ( ! (new Order)->isOrderPending($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_HAS_BEEN_PROCESS_BEFORE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        if(!(new Ordercode)->setOrdercode($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COULD_NOT_SET_ORDERCODE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming'));

            return false;
        }

        $this->setMessage(Text::_('COM_TICKETSTATION_THANK_YOU_FOR_MAKING_PAYMENT'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=cart'));

        return true;
    }

    /**
     * Validating an order by request.
     *
     * @since 1.0.0
     */
    public function validate()
    {
        $app = Factory::getApplication();

        // If ordercode or id is empty.. Stop here.
        if ($this->ordercode == 0 || $this->id == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_VALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation'));

            return false;
        }

        require_once JPATH_ADMINISTRATOR . '/components/com_ticketmaster/classes/confirmation.php';

        if ( ! (new Order)->setOrderToValidated($this->ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_VALIDATION_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation'));

            return false;
        }

        if (isset($this->ordercode))
        {
            require_once  JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helpers/confirmation.php';

            $sendconfirmation = new confirmation((int) $this->ordercode);
            $sendconfirmation->doConfirm();
            $sendconfirmation->doSend();
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_VALIDATED'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ticketstation'));

        return true;
    }
}
