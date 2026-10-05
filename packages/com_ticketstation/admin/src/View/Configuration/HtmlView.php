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

        $yesno = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')],
        ];

        $variablefixed = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_FIXED')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_VARIABLE')],
            '3' => ['value' => '3', 'text' => Text::_('COM_TICKETSTATION_FIXED_AND_VARIABLE')],
            '2' => ['value' => '2', 'text' => Text::_('COM_TICKETSTATION_NONE')],
        ];

        $lists = [];

        $lists['show_thirdaddress'] = HTMLHelper::_('select.genericList', $yesno, 'show_thirdaddress', ' class="form-select" ' . '',
            'value', 'text', $config->show_thirdaddress);
        
        $lists['show_secondaddress'] = HTMLHelper::_('select.genericList', $yesno, 'show_secondaddress', ' class="form-select" ' . '',
            'value', 'text', $config->show_secondaddress);

        $lists['payments_on'] = HTMLHelper::_('select.genericList', $yesno, 'payments_on', ' class="form-select" ' . '',
            'value', 'text', $config->payments_on);

        $lists['man_payment'] = HTMLHelper::_('select.genericList', $yesno, 'man_payment', ' class="form-select" ' . '',
            'value', 'text', $config->man_payment);

        $lists['show_cancel'] = HTMLHelper::_('select.genericList', $yesno, 'show_cancel', ' class="form-select" ' . '',
            'value', 'text', $config->show_cancel);

        $lists['variable_transcosts'] = HTMLHelper::_('select.genericList', $variablefixed, 'variable_transcosts', ' class="form-select" ' . '',
            'value', 'text', $config->variable_transcosts);

        $lists['show_available_tickets'] = HTMLHelper::_('select.genericList', $yesno, 'show_available_tickets', ' class="form-select" ' . '',
            'value', 'text', $config->show_available_tickets);

        $lists['show_quantity_eventlist'] = HTMLHelper::_('select.genericList', $yesno, 'show_quantity_eventlist', ' class="form-select" ' . '',
            'value', 'text', $config->show_quantity_eventlist);

        $lists['show_price_eventlist'] = HTMLHelper::_('select.genericList', $yesno, 'show_price_eventlist', ' class="form-select" ' . '',
            'value', 'text', $config->show_price_eventlist);

        $lists['show_venue'] = HTMLHelper::_('select.genericList', $yesno, 'show_venue', ' class="form-select" ' . '',
            'value', 'text', $config->show_venue);

        $lists['show_venue_address'] = HTMLHelper::_('select.genericList', $yesno, 'show_venue_address', ' class="form-select" ' . '',
            'value', 'text', $config->show_venue_address);

        $lists['show_venue_description'] = HTMLHelper::_('select.genericList', $yesno, 'show_venue_description', ' class="form-select" ' . '',
            'value', 'text', $config->show_venue_description);

        $lists['show_venue_website'] = HTMLHelper::_('select.genericList', $yesno, 'show_venue_website', ' class="form-select" ' . '',
            'value', 'text', $config->show_venue_website);

        $lists['reminder_on'] = HTMLHelper::_('select.genericList', $yesno, 'reminder_on', ' class="form-select" ' . '',
            'value', 'text', $config->reminder_on);

        $reminderHours = [];

        foreach ([6, 12, 24, 36, 48, 72] as $hours) {
            $reminderHours[] = ['value' => (string) $hours, 'text' => $hours . ' ' . Text::_('COM_TICKETSTATION_HOURS')];
        }

        $lists['reminder_hours'] = HTMLHelper::_('select.genericList', $reminderHours, 'reminder_hours', 'class="form-select"', 'value',
            'text', (string) $config->reminder_hours);

        $lists['show_jsonld'] =HTMLHelper::_('select.genericList', $yesno, 'show_jsonld', ' class="form-select" ' . '',
            'value', 'text', $config->show_jsonld);

        $lists['send_profile_mail'] = HTMLHelper::_('select.genericList', $yesno, 'send_profile_mail', ' class="form-select" ' . '',
            'value', 'text', $config->send_profile_mail);

        $lists['show_country'] = HTMLHelper::_('select.genericList', $yesno, 'show_country', ' class="form-select" ' . '',
            'value', 'text', $config->show_country);

        $lists['show_birthday'] = HTMLHelper::_('select.genericList', $yesno, 'show_birthday', ' class="form-select" ' . '',
            'value', 'text', $config->show_birthday);

        $lists['show_salutation'] = HTMLHelper::_('select.genericList', $yesno, 'show_salutation', ' class="form-select" ' . '',
            'value', 'text', $config->show_salutation);

        $lists['show_address'] = HTMLHelper::_('select.genericList', $yesno, 'show_address', ' class="form-select" ' . '',
            'value', 'text', $config->show_address);

        $lists['show_city'] = HTMLHelper::_('select.genericList', $yesno, 'show_city', ' class="form-select" ' . '',
            'value', 'text', $config->show_city);

        $lists['auto_username'] = HTMLHelper::_('select.genericList', $yesno, 'auto_username', ' class="form-select" ' . '',
            'value', 'text', $config->auto_username);

        $lists['show_mailchimp_signup'] = HTMLHelper::_('select.genericList', $yesno, 'show_mailchimps', ' class="form-select" ' . '',
            'value', 'text', $config->show_mailchimps);

        $lists['pro_installed'] = HTMLHelper::_('select.genericList', $yesno, 'pro_installed', ' class="form-select" ' . '',
            'value', 'text', $config->pro_installed);

        $lists['use_coupons'] = HTMLHelper::_('select.genericList', $yesno, 'use_coupons', ' class="form-select" ' . '',
            'value', 'text', $config->use_coupons);

        $lists['show_remark_field'] = HTMLHelper::_('select.genericList', $yesno, 'show_remark_field', ' class="form-select" ' . '',
            'value', 'text', $config->show_remark_field);

        $lists['show_waitinglist'] = HTMLHelper::_('select.genericList', $yesno, 'show_waitinglist', ' class="form-select" ' . '',
            'value', 'text', $config->show_waitinglist);

        $lists['show_phone'] = HTMLHelper::_('select.genericList', $yesno, 'show_phone', ' class="form-select" ' . '',
            'value', 'text', $config->show_phone);

        $lists['show_zipcode'] = HTMLHelper::_('select.genericList', $yesno, 'show_zipcode', ' class="form-select" ' . '',
            'value', 'text', $config->show_zipcode);

        $lists['remove_unfinished'] = HTMLHelper::_('select.genericList', $yesno, 'remove_unfinished', ' class="form-select" ' . '',
            'value', 'text', $config->remove_unfinished);

        $lists['load_bootstrap_tpl'] = HTMLHelper::_('select.genericList', $yesno, 'load_bootstrap_tpl', ' class="form-select" ' . '',
            'value', 'text', $config->load_bootstrap_tpl);

        $lists['load_bootstrap'] = HTMLHelper::_('select.genericList', $yesno, 'load_bootstrap', ' class="form-select" ' . '',
            'value', 'text', $config->load_bootstrap);

        $lists['send_invoice'] = HTMLHelper::_('select.genericList', $yesno, 'send_invoice', ' class="form-select" ' . '',
            'value', 'text', $config->send_invoice);

        $lists['send_tickets_directly'] = HTMLHelper::_('select.genericList', $yesno, 'send_tickets_directly', ' class="form-select" ' . '',
            'value', 'text', $config->send_tickets_directly ?? 1);

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

        $formatList = function (array $formats, string $current) use ($sample) {
            $options = [];

            if ($current !== '' && !in_array($current, $formats, true)) {
                $options[] = ['value' => $current, 'text' => Text::sprintf('COM_TICKETSTATION_FORMAT_CURRENT', $sample->format($current))];
            }

            foreach ($formats as $format) {
                $options[] = ['value' => $format, 'text' => $sample->format($format)];
            }

            return $options;
        };

        $dateformats = $formatList(['d-m-Y', 'd/m/Y', 'd.m.Y', 'j-n-Y', 'Y-m-d', 'm/d/Y'], (string) $config->dateformat);
        $timeformats = $formatList(['H:i', 'H.i', 'G:i', 'g:i A'], (string) $config->time_format);

        $lists['dateformat'] = HTMLHelper::_('select.genericList', $dateformats, 'dateformat', 'class="form-select"', 'value',
            'text', $config->dateformat);
        $lists['time_format'] = HTMLHelper::_('select.genericList', $timeformats, 'time_format', 'class="form-select"', 'value',
            'text', $config->time_format);

        ## Filling the Array() for a dropdown list.
        $placeholder          = [
            '1'  => ['value' => '1', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER1')],
            '2'  => ['value' => '2', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER2')],
            '3'  => ['value' => '3', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER3')],
            '4'  => ['value' => '4', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER4')],
            '5'  => ['value' => '5', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER5')],
            '6'  => ['value' => '6', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER6')],
            '7'  => ['value' => '7', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER7')],
            '8'  => ['value' => '8', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER8')],
            '9'  => ['value' => '9', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER9')],
            '10' => ['value' => '10', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER10')],
            '11' => ['value' => '11', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER11')],
            '12' => ['value' => '12', 'text' => '' . Text::_('COM_TICKETSTATION_PLACEHOLDER12')],
        ];
        $lists['placeholder'] = HTMLHelper::_('select.genericList', $placeholder, 'priceformat', 'class="form-select" ="1"' . '', 'value',
            'text', $config->priceformat);

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
        $lists['wallet_apple_updates'] = HTMLHelper::_('select.genericList', $yesno, 'wallet_apple_updates', ' class="form-select" ',
            'value', 'text', (int) ($config->wallet_apple_updates ?? 0));

        $lists['wallet_apple'] = HTMLHelper::_('select.genericList', $yesno, 'wallet_apple', ' class="form-select" ',
            'value', 'text', (int) ($config->wallet_apple ?? 0));

        $lists['wallet_google_updates'] = HTMLHelper::_('select.genericList', $yesno, 'wallet_google_updates', ' class="form-select" ',
            'value', 'text', (int) ($config->wallet_google_updates ?? 0));

        $lists['wallet_google'] = HTMLHelper::_('select.genericList', $yesno, 'wallet_google', ' class="form-select" ',
            'value', 'text', (int) ($config->wallet_google ?? 0));

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

        parent::display($tpl);
    }


}