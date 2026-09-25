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
use Joomla\CMS\Toolbar\ToolbarHelper;

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

        $model = $this->getModel('Configuration', 'Administrator');
        $config = $model->getData();

        $yesno = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')],
        ];

        $variablefixed = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_FIXED')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_VARIABLE')],
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

        $lists['send_multi_ticket_admin'] = HTMLHelper::_('select.genericList', $yesno, 'send_multi_ticket_admin', ' class="form-select" ' . '',
            'value', 'text', $config->send_multi_ticket_admin);

        $lists['send_multi_ticket_only'] = HTMLHelper::_('select.genericList', $yesno, 'send_multi_ticket_only', ' class="form-select" ' . '',
            'value', 'text', $config->send_multi_ticket_only);

        $lists['send_pdf_tickets'] = HTMLHelper::_('select.genericList', $yesno, 'send_pdf_tickets', ' class="form-select" ' . '',
            'value', 'text', $config->send_pdf_tickets);

        $lists['send_invoice'] = HTMLHelper::_('select.genericList', $yesno, 'send_invoice', ' class="form-select" ' . '',
            'value', 'text', $config->send_invoice);

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

        ## Filling the Array() for a dropdown list.
        $currencyholder            = [
            '0' => ['value' => '1', 'text' => '' . Text::_('COM_TICKETSTATION_NO')],
            '1' => ['value' => '2', 'text' => '' . Text::_('COM_TICKETSTATION_EURO')],
            '2' => ['value' => '3', 'text' => '' . Text::_('COM_TICKETSTATION_POUND')],
        ];
        $lists['use_euros_in_pdf'] = HTMLHelper::_('select.genericList', $currencyholder, 'use_euros_in_pdf', 'class="form-select" ="1"' . '', 'value',
            'text', $config->use_euros_in_pdf);

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

        $this->config = $config;
        $this->lists = $lists;

        parent::display($tpl);
    }


}