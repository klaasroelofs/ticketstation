<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Checkout;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CheckoutFieldMap;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatOrphans;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SiteCaptcha;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\User;
use Ticketstation\Component\Ticketstation\Site\Controller\CheckoutController;
use Ticketstation\Component\Ticketstation\Site\Service\CartPage;


class HtmlView extends BaseHtmlView {

    /** @var bool The name and email address come from the account of the logged-in user */
    public $prefilled = false;

    /** The fields filled in from the account, which the clear button empties */
    public $clearFields = [];


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $app    = Factory::getApplication();
        $db     = Factory::getContainer()->get('DatabaseDriver');

        ## Seats that leave a single empty seat: back to the seat-picking page.
        SeatOrphans::guard();

        $info = $app->getUserState('com_ticketstation.registration');

        $model	= $this->getModel('checkout');

        $data	= $this->get('data');
        $config = $this->get('config');

        // The combined page has no separate cart to send the customer back to
        if ($config->pro_installed == 1 && (int) ($config->checkout_layout ?? 0) !== 1)
        {
            $require  = $this->get('datacheck');

            if (isset($require->total)?$require->total:0 > 0)
            {
                $itemid = TicketstationFunctions::getSiteItemid();
                $link = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
                $app->enqueueMessage($require->total.' '.Text::_( 'COM_TICKETSTATION_TICKETS_REQUIRES_SEAT' ), 'warning');
                $app->redirect($link);
            }

        }

        ## Filling the Array() for doors and make a select list for it.
        $gender = array(
            1 => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_MR' )),
            2 => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_MRS' )),
        );

        if($config->show_birthday != 0 )
        {
            ## Creating the drop down menu for days
            for ($i = 1, $n = 31; $i <= $n; $i++ )
            {
                $days[] = HTMLHelper::_('select.option', $i, $i);
            }

            ## Create <select name="year_from" class="inputbox"></select> ##
            $lists['day'] = HTMLHelper::_('select.genericlist', $days, 'day', 'class=" input-mini"', 'value', 'text', $info['day']);

            ## Filling the Array() for doors and make a select list for it.
            $month = array(
                1 => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_JANUARY' )),
                2 => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_FEBRUARY' )),
                3 => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_MARCH' )),
                4 => array('value' => '4', 'text' => Text::_( 'COM_TICKETSTATION_APRIL' )),
                5 => array('value' => '5', 'text' => Text::_( 'COM_TICKETSTATION_MAY' )),
                6 => array('value' => '6', 'text' => Text::_( 'COM_TICKETSTATION_JUNE' )),
                7 => array('value' => '7', 'text' => Text::_( 'COM_TICKETSTATION_JULY' )),
                8 => array('value' => '8', 'text' => Text::_( 'COM_TICKETSTATION_AUGUST' )),
                9 => array('value' => '9', 'text' => Text::_( 'COM_TICKETSTATION_SEPTEMBER' )),
                10 => array('value' => '10', 'text' => Text::_( 'COM_TICKETSTATION_OCTOBER' )),
                11 => array('value' => '11', 'text' => Text::_( 'COM_TICKETSTATION_NOVEMBER' )),
                12 => array('value' => '12', 'text' => Text::_( 'COM_TICKETSTATION_DECEMBER' )),

            );

            $lists['month'] = HTMLHelper::_('select.genericList', $month, 'month', ' class="input input-small" ' , 'value', 'text', $info['month'] );

            ## Get current year for dropdown menu:
            $current_year = date('Y');

            ## Creating the drop down menu for years,
            for ($i = 1930, $n = $current_year; $i <= $n; $i++ )
            {
                $years[] = HTMLHelper::_('select.option', $i, $i);
            }

            ## Create <select name="year_from" class="inputbox"></select> ##
            $lists['year'] = HTMLHelper::_('select.genericlist', $years, 'year', 'class="input  input-mini"', 'value', 'text', $info['year']);
        }

        $query = $db->getQuery(true);
        $query->select(array('country_id AS id', 'country AS name '));
        $query->from($db->quoteName('#__ticketstation_country'));
        $query->where($db->quoteName('published')." = ".$db->quote(1));
        $query->where($db->quoteName('country_id')." != ".$db->quote(1));
        $query->order('country ASC');

        $db->setQuery($query);

        $countrylist[]	  = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_PLS_SELECT' ), 'id', 'name' );
        $countrylist	      = array_merge( $countrylist, $db->loadObjectList() );
        ## The form's values: what the customer typed when the form came back with errors, or
        ## else the details already stored for this order. The errors are shown once.
        $client = (new User)->getClientByOrdercode((int) $app->getSession()->get('ordercode'));
        $typed  = $app->getUserState(CheckoutController::STATE_DATA);

        $this->values = is_array($typed) ? $typed : [
            'gender'       => $client->gender ?? '',
            'name'         => $client->name ?? '',
            'address'      => $client->address ?? '',
            'address2'     => $client->address2 ?? '',
            'address3'     => $client->address3 ?? '',
            'zipcode'      => $client->zipcode ?? '',
            'city'         => $client->city ?? '',
            'country_id'   => $client->country_id ?? '',
            'phonenumber'  => $client->phonenumber ?? '',
            'emailaddress' => $client->emailaddress ?? '',
        ];
        ## A logged-in visitor with no details stored for this order yet starts with the name and email
        ## address of the account; the customer can clear them to book for someone else.
        $user = $app->getIdentity();

        if (!is_array($typed) && $user && $user->id && empty($client->emailaddress) && trim((string) $user->email) !== '') {
            $this->values['name']         = trim((string) $user->name);
            $this->values['emailaddress'] = $user->email;
            $this->prefilled              = true;
            $this->clearFields            = ['name', 'emailaddress'];
        }

        ## The fields linked to a custom field of the user (Configuration > Checkout) are filled in
        ## from it, unless the order already has details of its own for that field.
        if (!is_array($typed) && $user && $user->id) {
            $values = CheckoutFieldMap::valuesFor($user, CheckoutFieldMap::decode($config->checkout_field_map ?? ''));

            foreach ($values as $field => $value) {
                $current = $this->values[$field] ?? '';
                $isEmpty = in_array($current, ['', null, 0, '0'], true) || ($field === 'country_id' && (int) $current === 1);

                if ($config->{CheckoutFieldMap::FIELDS[$field]} != 0 && $isEmpty) {
                    $this->values[$field] = $value;
                    $this->prefilled      = true;
                    $this->clearFields[]  = $field;
                }
            }
        }

        $this->errors = (array) $app->getUserState(CheckoutController::STATE_ERRORS, []);

        $app->setUserState(CheckoutController::STATE_DATA, null);
        $app->setUserState(CheckoutController::STATE_ERRORS, null);

        $lists['gender']  = HTMLHelper::_('select.genericList', $gender, 'gender', 'class="ts-select"', 'value', 'text', $this->values['gender'] ?: 1);
        $lists['country'] = HTMLHelper::_('select.genericlist',  $countrylist, 'country_id',
            'class="ts-select" required' . (isset($this->errors['country_id']) ? ' aria-invalid="true" aria-describedby="country_id-error"' : ''),
            'id', 'name', (int) $this->values['country_id']);

        ## The site's default captcha (Global Configuration), when one is set; not for staff.
        $this->captcha  = SiteCaptcha::display('checkout');

        $this->lists    = $lists;
        $this->data     = $data;
        $this->config   = $config;

        ## The combined page also shows the order and takes the payment
        if ((int) ($config->checkout_layout ?? 0) === 1) {
            $this->prepareCombined();
        }

        // Call the parent display to display the layout file
        parent::display($tpl);
    }

    /**
     * What the combined checkout page shows around the details form: the cart with its totals and
     * what the customer pays with. An empty cart goes back to the event list.
     *
     * @return  void
     */
    private function prepareCombined()
    {
        $page = CartPage::data();

        if (CartPage::isEmpty($page)) {
            $itemid = TicketstationFunctions::getSiteItemid();

            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''), false));
        }

        $this->page         = $page;
        $this->items        = $page->items;
        $this->customerNote = $page->customerNote;

        $app          = Factory::getApplication();
        $this->method = (string) $app->getUserState(CheckoutController::STATE_METHOD, '');

        $app->setUserState(CheckoutController::STATE_METHOD, null);

        $this->setLayout('onepage');
    }

}