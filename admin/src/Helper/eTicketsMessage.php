<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

class eTicketsMessage
{
    var $template     = null;
    var $attachment   = [];
    var $user         = null;
    var $variables    = [];
    var $replacements = [];
    var $templates    = null;
    var $language     = '';

    public function __construct($alias = '')
    {
        $this->setDefaultVariables();

        if ($alias)
        {
            $this->id($alias);
        }
    }

    public static function getInstance($alias = '')
    {
        $instance = new eTicketsMessage;

        if ($alias)
        {
            $instance->id($alias);
        }

        return $instance;
    }

    /*
     * Sets the message based on the id
     */
    public function id($alias)
    {
        $this->setTemplate($alias);

        // The sender is configured centrally; fall back on the company details when left empty
        $config = $this->getConfig();

        $variables = [
            'from_name'  => trim((string) $config->from_name) ?: trim((string) $config->companyname),
            'from_email' => trim((string) $config->from_email) ?: trim((string) $config->email),
        ];

        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }

    /*
     * Returns the message object based on the given id
     */
    public function setTemplate($tmpl = '')
    {
        if ( ! $tmpl && ! is_null($this->template))
        {
            return $this;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $alias_condition = $db->quoteName('mailid') . ' = ' . $db->quote($tmpl);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_templates'))
            ->where($alias_condition);

        $db->setQuery($query, 0, 1);

        $this->template = $db->loadObject();

        return $this;
    }

    /*
     * Gets the configuration for the component
     */
    function getConfig()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = 1');

        $db->setQuery($query);
        $config = $db->loadObject();

        return $config;
    }

    /*
     * Adds variable aliases to the variables list
     */
    private function setVariablesAliases($aliases = [])
    {
        $variables = [];

        foreach ($aliases as $variable => $key)
        {
            $variables[$variable] = isset($variables[$key]) ? $variables[$key] : '';
        }

        $this->variables = array_merge($this->variables, $variables);

        if (empty($this->variables['password']))
        {
            $this->variables['password'] = '***';
        }
    }

    /*
     * Adds a set of default variables to the variables list
     */
    private function setDefaultVariables()
    {
        $app = Factory::getApplication();
        $config = $this->getConfig();

        $this->variables = [

            '@owner'           => 'COM_TICKETSTATION_OWNER',
            'company_name'     => $config->companyname,
            'companyname'      => $config->companyname,
            'company_address1' => $config->address1,
            'company_address'  => $config->address1,
            'companyaddress'   => $config->address1,
            'company_address2' => $config->address2,
            'company_zipcode'  => $config->zipcode,
            'company_zip_city' => $config->zipcode . ' ' . $config->city,
            'company_city'     => $config->city,
            'company_email'    => $config->email,
            'company_website'  => $config->website,

            '@registration' => 'COM_TICKETSTATION_REGISTRATION',
            'username'      => '',
            'password'      => '',
            'email'         => '',
            'userid'        => '',

            '@customer'    => 'COM_TICKETSTATION_CUSTOMER',
            'firstname'    => '',
            'lastname'     => '',
            'name'         => '',
            'address'      => '',
            'address2'     => '',
            'address3'     => '',
            'zipcode'      => '',
            'city'         => '',
            'phonenumber'  => '',
            'ip_address'   => '',

            '@confirmation_invoice'     => 'COM_TICKETSTATION_CONFIRMATION_INVOICE_TEMPLATE',
            'order_date'                => '',
            'order_list'                => '',
            'order_number'              => '',
            'ordered_items'             => '',
            'invoice_bruto'             => '',
            'invoice_discount'          => '',
            'invoice_vat'               => '',
            'invoice_netto'             => '',
            'invoice_discounted_ex_vat' => '',
            'invoice_discounted_price'  => '',
            'invoice_fees'              => '',
            '@confirmation'             => 'COM_TICKETSTATION_CONFIRMATION_TEMPLATE',
            'payment_state'             => '',
            'payment_link'             => '',
            '@invoice'                  => 'COM_TICKETSTATION_INVOICE_TEMPLATE',
            'client_id'                 => '',
            'invoice_id'                => '',
            'payment_transaction'       => '',
            'payment_date'              => '',
            'payment_provider'          => '',
            'brutoprice'                => '',
            'discount'                  => '',
            'totalvat'                  => '',
            'nettoprice'                => '',
            '@items'                    => 'COM_TICKETSTATION_INVOICE_CONFIRMATION_ITEMS',
            'item_amount'               => '',
            'item_price'                => '',
            'item_vat'                  => '',
            'item_discount'             => '',
            'item_vat_pct'              => '',
            'price_ex_vat'              => '',
            'ticket_price'              => '',
            'ticket_name'               => '',
            'ticket_date'               => '',
            'ticket_group'              => '',
            'event_name'                => '',
            'venue_name'                => '',
            'venue_street'              => '',
            'venue_zipcode'             => '',
            'venue_city'                => '',

            '@site'          => 'COM_TICKETSTATION_SITE_INFO',
            'sitename'       => $app->get('sitename'),
            'siteurl'        => Uri::root(),
            'from_name'      => '',
            'from_email'     => '',
            'reply_to_name'  => '',
            'reply_to_email' => '',

            '@end' => 'Ignore any other variables in the visual output of the Template Variable list',
        ];
    }

    /*
     * Adds user-defined variables to the variables list
     */
    public function variables($variables = [])
    {
        if (is_object($variables))
        {
            $variables = (array) $variables;
        }

        $this->variables = array_merge($this->variables, $variables);

        if (is_null($this->user) && ! empty($variables['userid']))
        {
            $this->user($variables['userid']);
        }

        $this->setVariablesAliases();

        return $this;
    }

    /*
     * Sets the user based on the id and set the user variables
     */
    public function user($id)
    {
        if ( ! $id)
        {
            $id = isset($this->variables['userid']) ? (int) $this->variables['userid'] : 0;
        }

        $this->user = $this->getUserObject($id);

        $this->user->password = '';

        $this->variables = array_merge($this->variables, (array) $this->user);

        return $this;
    }

    public function showMessageVariables()
    {
        $result = [];
        $this->setDefaultVariables();

        return $this->variables;
    }

    /*
     * Prepares and sends the final email
     */
    public function send()
    {
        if (empty($this->template))
        {
            Factory::getApplication()
                ->enqueueMessage(Text::_('COM_TICKETSTATION_NO_MESSAGE_FOUND_IN_MESSAGE_CLASS'), 'error');

            return false;
        }

        if (empty($this->user))
        {
            Factory::getApplication()
                ->enqueueMessage(Text::_('COM_TICKETSTATION_NO_USER_FOUND_IN_MESSAGE_CLASS'), 'error');

            return false;
        }

        // Wrap the body in sans-serif styling
        $body = '<div style="font-family:sans-serif">' . $this->getBody() . '</div>';

        // Should the email be sent to the user or the shop owner?
        $send_to_email = $this->variables['emailaddress'];
        $send_to_name  = $this->variables['firstname'] . ' ' . $this->variables['name'];

        // Compile mailer function:
        $mailer = Factory::getMailer();
        $mailer->setSubject($this->getSubject());
        $mailer->setBody($body);

        // Without a usable sender address the mailer keeps the global Joomla sender
        if (MailHelper::isEmailAddress($this->variables['from_email']))
        {
            $mailer->setSender([$this->variables['from_email'], $this->variables['from_name']]);
        }

        $mailer->addRecipient($send_to_email, $send_to_name);
        //$mailer->addReplyTo($this->variables['reply_to_email'], $this->variables['reply_to_name']);
        $mailer->isHTML(true);

        $mailer->AltBody = $this->getAltBody();

        if ( ! empty($this->attachment))
        {
            foreach ($this->attachment as $attachment)
            {
                $mailer->addAttachment($attachment);
            }
        }

        if ( ! $mailer->Send())
        {
            Factory::getApplication()
                ->enqueueMessage(Text::_('COM_TICKETSTATION_SENDMAIL_FAILED_MESSAGE_CLASS'), 'error');
        }
    }

    public function showMessage()
    {
        return $this->getBody();
    }

    public function showHeader()
    {
        return $this->getSubject();
    }

    public function attachment($file)
    {
        if (is_array($file))
        {
            foreach ($file as $attachment)
            {
                if (file_exists($attachment))
                {
                    $this->attachment[] = $attachment;
                }
            }
        }
        else
        {
            if (file_exists($file))
            {
                $this->attachment[] = $file;
            }
        }

        return $this;
    }

    /*
     * Returns the body of the message with replaced vaiables
     */
    public function getAltBody()
    {
        if ( ! isset($this->template->altbody))
        {
            return '';
        }

        $string = $this->template->altbody;

        $this->replaceVariables($string);

        return $string;
    }

    /*
     * Returns the body of the message with replaced vaiables
     */
    public function getBody()
    {
        if ( ! isset($this->template->mailbody))
        {
            return '';
        }

        $string = $this->template->mailbody;

        $this->replaceVariables($string);

        return $string;
    }

    /*
     * Returns the subject of the message with replaced vaiables
     */
    public function getSubject()
    {
        if ( ! isset($this->template->mailsubject))
        {
            return '';
        }

        $string = $this->template->mailsubject;

        $this->replaceVariables($string);

        return $string;
    }

    private function getUserObject($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');


        $query = $db->getQuery(true)->select([
            'uu.*',
        ])->from($db->quoteName('#__ticketstation_clients', 'uu'));


        // Only limit by user id when a userid is given (logged in)
        if ( ! empty($id))
        {
            $query->where($db->quoteName('uu.clientid') . ' = ' . (int) $id);
        }

        $db->setQuery($query, 0, 1);

        $user = $db->loadAssoc();

        if (empty($user))
        {
            return $this->getUserObject(0);
        }

        // Empty the values if no user id is set (when visitor is a guest)
        // As the returned values will be from the first user found in the database
        if (empty($id))
        {
            $user = array_fill_keys(array_keys($user), '');
        }

        return (object) $user;
    }

    /*
     * Replaces all variables in the string: templates, if structures and normal variable tags
     */
    private function replaceVariables(&$string)
    {
        // Set/Init variables if not already done so
        $this->variables();

        $this->replaceMainVariables($string);
    }

    /*
     * Replaces the main variables in the string
     */
    private function replaceMainVariables(&$string)
    {
        list($search, $replace) = $this->getVariableSearchReplace();

        $string = str_replace($search, $replace, $string);

        // Remove the empty value placeholder including leading whitespace
        $string = preg_replace('#(&nbsp;|&\#160;|\s)*<\!-- EMPTY VAR -->#', '', $string);
    }

    /*
     * Gets the searches (variable tags) and replaces (output values) for the main variables
     */
    private function getVariableSearchReplace()
    {
        if ( ! empty($this->replacements))
        {
            return [
                array_keys($this->replacements),
                array_values($this->replacements),
            ];
        }

        array_walk($this->variables, [
            $this,
            'getSearchReplaceItem',
        ]);

        return [
            array_keys($this->replacements),
            array_values($this->replacements),
        ];
    }

    /*
     * Converts a variable key to a tag syntax and adds it to the replacements array, together with its output value
     */
    private function getSearchReplaceItem($value, $tag)
    {
        if (empty($tag) || $tag['0'] == '@')
        {
            return;
        }

        $this->replacements['%%' . strtoupper($tag) . '%%'] = $value;
        $this->replacements['{' . $tag . '}']               = $value;
    }
}