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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;
use Ticketstation\Component\Ticketstation\Administrator\Helper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\User;
use Ticketstation\Component\Ticketstation\Site\Model\CheckoutModel;

/**
 * Ticketstation Checkout Controller
 * @since  0.2.11
 */
class CheckoutController extends BaseController
{
    private $username;
    private $password;
    private $ordercode;
    private $email;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();

        $this->email    = $jinput->get('emailaddress', '0', 'raw');
        $this->name     = $jinput->get('name', '0', 'string');
        $this->password = $jinput->get('password', '0', 'raw');
        $this->username = $jinput->get('username', '0', 'username');

        $this->ordercode = Factory::getApplication()->getSession()->get('ordercode');
    }

    /**
     * Check and set a coupon code.
     *
     * @return bool
     *
     * @since 1.0.0
     */
    function coupon()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $couponcode = $jinput->get('couponcode', 'NONE', 'STRING');

        $itemid = TicketstationFunctions::getSiteItemid();
        $cartUrl = 'index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : '');

        if ($couponcode == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EMPTY_COUPON'), 'error');
            $this->setRedirect(Route::_($cartUrl));

        } else {

            $post  = $app->getInput()->post->getArray();
            //$model = $this->getModel('checkout');
            $model = new CheckoutModel();

            if ($model->checkCoupon($post))
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_COUPON_APPLIED_TO_CART'));
                //$this->setRedirect(JRoute::_('index.php?option=com_ticketmaster&view=checkout'));
                $this->setRedirect(Route::_($cartUrl));
            }
            else
            {
                //$app->enqueueMessage(Text::_('COM_TICKETSTATION_INVALID_COUPON'), 'error');
                $this->setRedirect(Route::_($cartUrl));
            }

            return true;

        }


    }



    function save()
    {

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $app    = Factory::getApplication();
        $jinput = Factory::getApplication()->getInput();

        // Register submitted data.
        $requestData = Factory::getApplication()->getInput()->post->getArray();

        // We do need this again for failed registrations.
        //$app->setUserState('com_ticketmaster.registration', $requestData);

        //CHECK RECAPTCHA
        //TODO: RECAPTCHA INTEGREREN
        /*
        JPluginHelper::importPlugin('captcha');
        $dispatcher = JDispatcher::getInstance();
        $res        = $dispatcher->trigger('onCheckAnswer', $jinput->get('recaptcha_response_field', '', 'raw'));

        $dispatcher = Factory::getApplication()->getDispatcher();
        $event = new Event('onCheckAnswer', $jinput->get('recaptcha_response_field', '', 'raw'));
        $res = $dispatcher->dispatch('onCheckAnswer', $event);

        if ( ! empty($res))
        {
            if ( ! $res[0])
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_CAPTCHA_INCORRECT'), 'error');
                $app->redirect(Route::_('index.php?option=com_ticketstation&view=checkout'));
            }
        }
        */

        // Getting the configuration
        $config = (new Config)->get(['use_automatic_login', 'auto_username', 'show_birthday', 'show_phone', 'show_country', 'show_address', 'show_secondaddress', 'show_thirdaddress', 'show_zipcode', 'show_city', 'show_salutation']);

        // Validating the form
        if ( ! $this->validateForm($config))
        {
            $itemid = TicketstationFunctions::getSiteItemid();
            $app->redirect(Route::_('index.php?option=com_ticketstation&view=checkout' . ($itemid ? '&Itemid=' . $itemid : '')));

            return false;
        }


        $post   = $jinput->post->getArray();


        // Connecting the model.
        $model = $this->getModel('checkout');

        // Check if mailaddress exists
        $emailaddress = $jinput->get('emailaddress', '', 'string');

        $query = $db->getQuery(true)
            ->select($db->quoteName('clientid'))
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('emailaddress') . ' = ' . $db->quote($emailaddress));

        $db->setQuery($query);

        $clientid = $db->loadResult();

        $models = new CheckoutModel();

        if ($clientid == '')
        {

            $post['name']	   		= $jinput->get('lastname', '', 'string');
            $post['firstname'] 		= $jinput->get('firstname', '', 'string');
            $post['emailaddress']   = $emailaddress;
            $post['ipaddress'] 		= $_SERVER['REMOTE_ADDR'];
            $post['published'] 		= 1;

            //$model->store($post);

            $models->store($post);

            $clientid = $models->getClientid();
        }

        //clientid in database vullen
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_clients'))
            ->set($db->quoteName('name') . ' = ' . $db->quote($jinput->get('lastname', '', 'string')))
            ->set($db->quoteName('firstname') . ' = ' . $db->quote($jinput->get('firstname', '', 'string')));

        if ($config->show_phone == 1)
        {
            $query->set($db->quoteName('phonenumber') . ' = ' . $db->quote($jinput->get('phonenumber', '', 'string')));
        }

        if ($config->show_salutation == 1)
        {
            $query->set($db->quoteName('gender') . ' = ' . $db->quote($jinput->get('gender', '', 'string')));
        }

        if ($config->show_address == 1)
        {
            $query->set($db->quoteName('address') . ' = ' . $db->quote($jinput->get('address', '', 'string')));
        }

        if ($config->show_secondaddress == 1)
        {
            $query->set($db->quoteName('address2') . ' = ' . $db->quote($jinput->get('address2', '', 'string')));
        }

        if ($config->show_thirdaddress == 1)
        {
            $query->set($db->quoteName('address3') . ' = ' . $db->quote($jinput->get('address3', '', 'string')));
        }

        if ($config->show_zipcode == 1)
        {
            $query->set($db->quoteName('zipcode') . ' = ' . $db->quote($jinput->get('zipcode', '', 'string')));
        }

        if ($config->show_city == 1)
        {
            $query->set($db->quoteName('city') . ' = ' . $db->quote($jinput->get('city', '', 'string')));
        }

        if ($config->show_country == 1)
        {
            $query->set($db->quoteName('country_id') . ' = ' . $db->quote($jinput->get('country_id', '1', 'int')));
        }

        $query->where($db->quoteName('clientid') . ' = ' . $db->quote($clientid));

        $db->setQuery($query);

        $result = $db->execute();

        // Remove from data from the userstate
        //$app->setUserState('com_ticketmaster.registration', null);

        // A customer with only waiting-list tickets has no order that a payment would close off.
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($this->ordercode));
        $db->setQuery($query);
        $waitingOnly = ((int) $db->loadResult() === 0);

        // Update the order with user information
        if (!$models->itemsupdate($clientid, $this->ordercode))
        {
            return false;
        }

        if ($waitingOnly)
        {
            // The signup is complete: start a fresh cart, so the next waiting-list signup is not
            // mixed up with this one (a payment clears the ordercode the same way). The payment
            // screen finds these rows again through the remembered ordercode.
            $session = $app->getSession();
            $session->set('ticketstation.waitinglist_ordercode', (int) $this->ordercode);
            $session->clear('ordercode');
        }

        // Joomla's built-in "borrow the active menu item's Itemid" fallback (System - SEF
        // plugin) is unreliable for a mid-request controller redirect like this one - see
        // TicketstationFunctions::getSiteItemid() for details. Pass the Itemid through
        // explicitly so the payment screen stays under the site's menu item instead of
        // falling back to the raw component/ticketstation/payment URL.
        $itemid = TicketstationFunctions::getSiteItemid();
        $app->redirect(Route::_('index.php?option=com_ticketstation&view=payment' . ($itemid ? '&Itemid=' . $itemid : '')));

        return true;
    }

    /**
     * Validating the form values.
     *
     * @param $config
     *
     * @return bool
     *
     * @since 1.0.0
     */
    private function validateForm($config)
    {
        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        // Check if the email address is not empty.
        if ($jinput->get('emailaddress', '', 'string') == '')
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_USER_EMAIL_INCORRECT'), 'error');

            return false;
        }

        // Check if email is valid, else redirect back
        if ( ! filter_var($jinput->getString('emailaddress'), FILTER_VALIDATE_EMAIL))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_USER_EMAIL_INCORRECT'), 'error');

            return false;
        }

        // If country enabled, check it
        if ($config->show_country == 1)
        {
            if ($jinput->get('country_id', '0', 'int') == 0)
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_COUNTRY_ID_FILLED'), 'error');

                return false;
            }
        }

        // Check if the name has been set.
        if ($jinput->get('lastname', '', 'string') == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_NAME_NOT_FILLED'), 'error');

            return false;
        }

        // Check if the firstname has been set.
        if ($jinput->get('firstname', '', 'string') == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_NAME_NOT_FILLED'), 'error');

            return false;
        }

        // If enabled, check the phone number.
        if ($config->show_phone == 1)
        {
            if ($jinput->get('phonenumber', '', 'string') == '')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_PHONE_NOT_FILLED'), 'error');

                return false;
            }
        }

        // If enabled the address, check it.
        if ($config->show_address == 1)
        {
            if ($jinput->get('address', '', 'string') == '')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_ADDRESS_NOT_FILLED'), 'error');

                return false;
            }
        }

        // If enabled the address, check it.
        if ($config->show_secondaddress == 1)
        {
            if ($jinput->get('address2', '', 'string') == '')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_ADDRESS2_NOT_FILLED'), 'error');

                return false;
            }
        }

        // If enabled the city, check it.
        if ($config->show_city == 1)
        {
            if ($jinput->get('city', '', 'string') == '')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_CHECKOUT_CITY_NOT_FILLED'), 'error');

                return false;
            }
        }

        if ($jinput->get('emailaddress', '', 'string') != $jinput->get('email2', '', 'string'))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_EMAILADDRESSES_DO_NOT_COMPARE'), 'error');

            return false;
        }

        return true;

    }


    /**
     * Generating a password on request.
     *
     * @param int    $length
     * @param string $chars
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function password($length = 7, $chars = '123456789')
    {
        $chars_length = (strlen($chars) - 1);

        // Start our string
        $string = $chars[rand(0, $chars_length)];

        // Generate random string
        for ($i = 1; $i < $length; $i = strlen($string))
        {
            $r = $chars[rand(0, $chars_length)];
            if ($r != $string[$i - 1]) $string .= $r;
        }

        return $string;
    }
}	