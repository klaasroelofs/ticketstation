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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Mollie\Api\MollieApiClient;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * Ticketstation Validate Controller
 * @since  0.9.4
 */
class ValidateController extends BaseController
{
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
        $itemid = TicketstationFunctions::getSiteItemid();

        if ($order == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $decoded   = base64_decode($order);
        $parts     = explode('=', $decoded);

        if ( ! isset($parts[1]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $token = $parts[1];
        $waitinglist = new Waitinglist;

        if ( ! $waitinglist->confirmByToken($token))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_VALIDATION_WAITINGLIST_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        // Note: CMSApplication::redirect()'s 2nd argument is the HTTP status code, not a message.
        $app->enqueueMessage(Text::_('COM_TICKETSTATION_VALIDATION_WAITINGLIST_COMPLETED'), 'success');
        $app->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

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
        $itemid = TicketstationFunctions::getSiteItemid();

        if (empty($paylater))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $decoded   = base64_decode($paylater);
        $parts     = explode('=', $decoded);

        if ( ! isset($parts[1]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $token = $parts[1];

        // Look up ordercode by validation token
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('ordercode')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('validation_token') . ' = ' . $db->quote($token));
        $db->setQuery($query);
        $result = $db->loadObject();

        if (!$result)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $ordercode = $result->ordercode;

        // Checking if there is an order.
        if ( ! (new Order)->isOrderPending($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_HAS_BEEN_PROCESS_BEFORE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        if(!(new Ordercode)->setOrdercode($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COULD_NOT_SET_ORDERCODE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }

        $this->setMessage(Text::_('COM_TICKETSTATION_THANK_YOU_FOR_MAKING_PAYMENT'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : '')));

        return true;
    }
}
