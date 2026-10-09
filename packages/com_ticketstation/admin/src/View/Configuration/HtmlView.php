<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Configuration;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CheckoutFieldMap;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletApple;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletApplePush;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletAppleService;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletGoogle;

/**
 * Ticketstation Configuration Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the Ticketstation configuration view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $config = [];

    function display($tpl = null) {

        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_CONFIGURATION_TITLE'), 'icon-cog');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolbarHelper::cancel();

        if (AclGate::can('core.admin')) {
            ToolbarHelper::preferences('com_ticketstation', 500, 900, 'COM_TICKETSTATION_TOOLBAR_PERMISSIONS');
        }

        Docs::toolbarButton('configuration');

        $model = $this->getModel('Configuration', 'Administrator');
        $config = $model->getData();

        // A Yes/No setting is a Joomla switcher, like the on/off settings in Joomla's own configuration.
        // This screen binds its fields by hand, so every switcher is rendered by a one-field Form of its own.
        $switch = function (string $name, $value): string {
            $form = new Form('com_ticketstation.configuration.' . $name);
            $form->load('<form><field name="' . $name . '" type="radio" layout="joomla.form.field.radio.switcher" default="0">'
                . '<option value="0">COM_TICKETSTATION_NO</option><option value="1">COM_TICKETSTATION_YES</option>'
                . '</field></form>');
            $form->bind([$name => (int) $value]);

            return $form->getField($name)->input;
        };

        $variablefixed = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_FIXED')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_VARIABLE')],
            '3' => ['value' => '3', 'text' => Text::_('COM_TICKETSTATION_FIXED_AND_VARIABLE')],
            '2' => ['value' => '2', 'text' => Text::_('COM_TICKETSTATION_NONE')],
        ];

        $lists = [];

        $lists['show_thirdaddress'] = $switch('show_thirdaddress', $config->show_thirdaddress);
        
        $lists['show_secondaddress'] = $switch('show_secondaddress', $config->show_secondaddress);

        $lists['payments_on'] = $switch('payments_on', $config->payments_on);

        $lists['man_payment'] = $switch('man_payment', $config->man_payment);

        $lists['show_cancel'] = $switch('show_cancel', $config->show_cancel);

        $lists['variable_transcosts'] = HTMLHelper::_('select.genericList', $variablefixed, 'variable_transcosts', ' class="form-select" ' . '',
            'value', 'text', $config->variable_transcosts);

        $lists['show_available_tickets'] = $switch('show_available_tickets', $config->show_available_tickets);

        $lists['show_quantity_eventlist'] = $switch('show_quantity_eventlist', $config->show_quantity_eventlist);

        $lists['show_price_eventlist'] = $switch('show_price_eventlist', $config->show_price_eventlist);

        $lists['show_venue'] = $switch('show_venue', $config->show_venue);

        $lists['show_venue_address'] = $switch('show_venue_address', $config->show_venue_address);

        $lists['show_venue_description'] = $switch('show_venue_description', $config->show_venue_description);

        $lists['show_venue_website'] = $switch('show_venue_website', $config->show_venue_website);

        $lists['reminder_on'] = $switch('reminder_on', $config->reminder_on);

        $reminderHours = [];

        foreach ([6, 12, 24, 36, 48, 72] as $hours) {
            $reminderHours[] = ['value' => (string) $hours, 'text' => $hours . ' ' . Text::_('COM_TICKETSTATION_HOURS')];
        }

        $lists['reminder_hours'] = HTMLHelper::_('select.genericList', $reminderHours, 'reminder_hours', 'class="form-select"', 'value',
            'text', (string) $config->reminder_hours);

        $lists['show_jsonld'] =$switch('show_jsonld', $config->show_jsonld);

        $lists['send_profile_mail'] = $switch('send_profile_mail', $config->send_profile_mail);

        $lists['show_country'] = $switch('show_country', $config->show_country);

        $lists['show_birthday'] = $switch('show_birthday', $config->show_birthday);

        $lists['show_salutation'] = $switch('show_salutation', $config->show_salutation);

        $lists['show_address'] = $switch('show_address', $config->show_address);

        $lists['show_city'] = $switch('show_city', $config->show_city);

        $lists['auto_username'] = $switch('auto_username', $config->auto_username);

        $lists['show_mailchimp_signup'] = $switch('show_mailchimps', $config->show_mailchimps);

        $lists['pro_installed'] = $switch('pro_installed', $config->pro_installed);

        $lists['use_coupons'] = $switch('use_coupons', $config->use_coupons);

        $lists['show_remark_field'] = $switch('show_remark_field', $config->show_remark_field);

        $lists['checkout_layout'] = HTMLHelper::_('select.genericList', [
            ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_CHECKOUT_LAYOUT_STEPS')],
            ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_CHECKOUT_LAYOUT_COMBINED')],
        ], 'checkout_layout', ' class="form-select" ', 'value', 'text', (string) ($config->checkout_layout ?? 0));

        $lists['show_waitinglist'] =$switch('show_waitinglist', $config->show_waitinglist);

        $lists['show_phone'] = $switch('show_phone', $config->show_phone);

        $lists['show_zipcode'] = $switch('show_zipcode', $config->show_zipcode);

        $lists['remove_unfinished'] = $switch('remove_unfinished', $config->remove_unfinished);

        $lists['load_bootstrap_tpl'] = $switch('load_bootstrap_tpl', $config->load_bootstrap_tpl);

        $lists['load_bootstrap'] = $switch('load_bootstrap', $config->load_bootstrap);

        $lists['send_invoice'] = $switch('send_invoice', $config->send_invoice);

        $lists['send_tickets_directly'] = $switch('send_tickets_directly', $config->send_tickets_directly ?? 1);

        ## Filling the Array() for a dropdown list.
        $jquery               = [
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_JQ_LOAD_FROM_CDN_JQUERY')],
            '2' => ['value' => '2', 'text' => Text::_('COM_TICKETSTATION_JQ_LOAD_LOCALLY')],
            '3' => ['value' => '3', 'text' => Text::_('COM_TICKETSTATION_JQ_LOAD_IN_TEMPLATE')],
        ];
        $lists['load_jquery'] = HTMLHelper::_('select.genericList', $jquery, 'load_jquery', 'class="form-select" ' . '', 'value',
            'text', $config->load_jquery);

        ## Filling the Array() for a dropdown list.
        $hours                  = [
            '0' => ['value' => '0.17', 'text' => '10 ' . Text::_('COM_TICKETSTATION_MINUTES')],
            '1' => ['value' => '0.25', 'text' => '15 ' . Text::_('COM_TICKETSTATION_MINUTES')],
            '2' => ['value' => '0.5', 'text' => '30 ' . Text::_('COM_TICKETSTATION_MINUTES')],
            '3' => ['value' => '1', 'text' => '1 ' . Text::_('COM_TICKETSTATION_HOUR')],
            '4' => ['value' => '2', 'text' => '2 ' . Text::_('COM_TICKETSTATION_HOURS')],
            '5' => ['value' => '3', 'text' => '3 ' . Text::_('COM_TICKETSTATION_HOURS')],
            '6' => ['value' => '4', 'text' => '4 ' . Text::_('COM_TICKETSTATION_HOURS')],
            '7' => ['value' => '5', 'text' => '5 ' . Text::_('COM_TICKETSTATION_HOURS')],
        ];
        $lists['removal_hours'] = HTMLHelper::_('select.genericList', $hours, 'removal_hours', 'class="form-select"', 'value',
            'text', $config->removal_hours);

        ## Filling the Array() for a dropdown list.
        $days                  = [
            '0' => ['value' => '1', 'text' => '1 ' . Text::_('COM_TICKETSTATION_DAY')],
            '1' => ['value' => '2', 'text' => '2 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '2' => ['value' => '3', 'text' => '3 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '3' => ['value' => '4', 'text' => '4 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '4' => ['value' => '5', 'text' => '5 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '5' => ['value' => '7', 'text' => '7 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '6' => ['value' => '14', 'text' => '14 ' . Text::_('COM_TICKETSTATION_DAYS')],
            '7' => ['value' => '21', 'text' => '21 ' . Text::_('COM_TICKETSTATION_DAYS')],
        ];
        $lists['removal_days'] = HTMLHelper::_('select.genericList', $days, 'removal_days', 'class="form-select"', 'value',
            'text', $config->removal_days);

        ## Date and time notations to pick from, shown with an example. The example dates are chosen
        ## so every notation reads differently: day 14 can't be a month, and 09:05 shows leading zeros
        ## and the 12-hour clock. A notation entered earlier that isn't in the list stays selectable
        ## as it is, so saving the screen never changes it.
        $sample = new \DateTime('2026-03-14 09:05:00');

        $formatList = function (array $formats) use ($sample) {
            $options = [];

            foreach ($formats as $format) {
                $options[] = ['value' => $format, 'text' => $sample->format($format)];
            }

            return $options;
        };

        // A notation is typed in as a PHP date format; the presets come along as suggestions
        $formatInput = function (string $name, array $options, string $current, string $default) {
            $list = '<datalist id="' . $name . '_list">';

            foreach ($options as $option) {
                $list .= '<option value="' . htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($option['text'], ENT_QUOTES, 'UTF-8') . '</option>';
            }

            return '<input type="text" class="form-control" name="' . $name . '" id="' . $name . '" list="' . $name . '_list"'
                . ' maxlength="32" value="' . htmlspecialchars($current !== '' ? $current : $default, ENT_QUOTES, 'UTF-8') . '">' . $list . '</datalist>';
        };

        $dateformats = $formatList(['d-m-Y', 'd/m/Y', 'd.m.Y', 'j-n-Y', 'Y-m-d', 'm/d/Y']);
        $timeformats = $formatList(['H:i', 'H.i', 'G:i', 'g:i A']);

        $lists['dateformat']   = $formatInput('dateformat', $dateformats, (string) $config->dateformat, 'd-m-Y');
        $lists['time_format']  = $formatInput('time_format', $timeformats, (string) $config->time_format, 'H:i');
        /*
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = "SELECT emailid AS id, mailsubject AS name FROM #__ticketstation_emails";
        $db->setQuery($query);

        $email[] = HTMLHelper::_('select.option', '0', Text::_('COM_TICKETSTATION_PLS_SELECT'), 'id', 'name');
        $email   = array_merge($email, $db->loadObjectList());

        ## Creating a list for the activation email.
        $lists['activation_email'] = HTMLHelper::_('select.genericlist', $email, 'activation_email', 'class="input" ="1" ', 'id', 'name', intval($config->activation_email));

        ## Creating the list for terms of service page.
        $lists['tos_tpl'] = HTMLHelper::_('select.genericlist', $email, 'tos_tpl', 'class="input" ="1" ', 'id', 'name', intval($config->tos_tpl));
        */

        // This screen binds every field manually (Table::bind($post)), not via a real Joomla
        // Form-driven view, so there's no existing form to attach a media field to. A small
        // standalone Form instance renders just this one native Joomla media picker - kept
        // ungrouped (no <fields> wrapper) so its input name stays the bare "company_logo",
        // matching every other plain input on this page.
        $logoForm = new Form('com_ticketstation.configuration.logo');
        $logoForm->load('<form><field name="company_logo" type="media" preview="true" /></form>');
        $logoForm->bind(['company_logo' => $config->company_logo]);
        // ->input (not ->getInput(), which is protected) so we get just the picker widget -
        // this page draws its own <label> for every field already, matching the surrounding
        // markup. FormField exposes getInput()'s result through this public magic property.
        $this->companyLogoField = $logoForm->getField('company_logo')->input;

        // Wallet tab: the pass logo is a media picker of its own, like the company logo
        $lists['wallet_apple_updates'] = $switch('wallet_apple_updates', (int) ($config->wallet_apple_updates ?? 0));

        $lists['wallet_apple'] = $switch('wallet_apple', (int) ($config->wallet_apple ?? 0));

        $lists['wallet_google_updates'] = $switch('wallet_google_updates', (int) ($config->wallet_google_updates ?? 0));

        $lists['wallet_google'] = $switch('wallet_google', (int) ($config->wallet_google ?? 0));

        $walletLogoForm = new Form('com_ticketstation.configuration.walletlogo');
        $walletLogoForm->load('<form><field name="wallet_logo" type="media" preview="true" /></form>');
        $walletLogoForm->bind(['wallet_logo' => $config->wallet_logo ?? '']);
        $this->walletLogoField = $walletLogoForm->getField('wallet_logo')->input;

        // What is stored, without ever showing the keys themselves
        $this->walletApple = WalletApple::certificateInfo($config->wallet_apple_cert ?? null);
        $this->walletApplePending = ! empty($config->wallet_apple_pending_key);
        $this->appleServer  = WalletApplePush::describe();
        $this->appleService = WalletAppleService::url();
        $this->walletGoogle = WalletGoogle::serviceAccount($config->wallet_google_key ?? null);
        $this->walletColors = [
            Wallet::color($config->wallet_bg_color ?? '', Wallet::DEFAULT_BACKGROUND),
            Wallet::color($config->wallet_fg_color ?? '', Wallet::DEFAULT_FOREGROUND),
        ];
        $this->walletLogoProblem = Wallet::logoProblem();
        $this->walletCsrLink =Route::_('index.php?option=com_ticketstation&controller=configuration&task=walletcsr&' . Session::getFormToken() . '=1', false);

        $this->config = $config;
        $this->lists = $lists;
        $this->userFields = CheckoutFieldMap::userFields();
        $this->checkoutMap = CheckoutFieldMap::decode($config->checkout_field_map ?? '');

        parent::display($tpl);
    }


}