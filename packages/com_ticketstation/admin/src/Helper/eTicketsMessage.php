<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
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
    var $stringAttachments = [];
    var $user        = null;
    var $variables    = [];
    var $replacements = [];
    var $templates    = null;
    var $language     = '';

    /**
     * The placeholders every mail can use: the customer's details (from the client row, see
     * user()) and the company details from the Configuration (see setDefaultVariables()).
     */
    private const COMMON_FIELDS = [
        'COM_TICKETSTATION_TEMPLATE_FIELDS_CLIENT'  => ['name', 'firstname', 'emailaddress', 'phonenumber', 'address', 'zipcode', 'city'],
        'COM_TICKETSTATION_TEMPLATE_FIELDS_COMPANY' => ['company_name', 'company_address', 'company_zipcode', 'company_city', 'company_email', 'company_website'],
    ];

    /**
     * The order placeholders each template (mailid) gets filled in by the code that sends it.
     * The edit screen lists exactly these, so keep them in step with the senders.
     */
    private const TEMPLATE_FIELDS = [
        1 => ['ordercode', 'orderdate', 'orderlist', 'price', 'walletbuttons'],
        2 => ['ordercode', 'orderdate', 'orderlist', 'price', 'walletbuttons'],
        3 => ['ordercode', 'orderdate', 'orderlist', 'price', 'paymentlink'],
        4 => ['ordercode', 'orderdate', 'orderlist', 'confirmationlink'],
        5 => ['ordercode', 'orderdate', 'price', 'invoice_id'],
        6 => ['ordercode', 'orderdate', 'orderlist', 'price', 'paymentlink', 'removal_days', 'eventname'],
        7 => ['ordercode', 'orderdate', 'orderlist', 'price', 'subject', 'message', 'eventname', 'eventdate', 'eventtime', 'location'],
        8 => ['ordercode', 'orderdate', 'orderlist', 'price', 'eventname', 'eventdate', 'eventtime', 'doorsopen', 'location', 'ticketlink'],
    ];

    /**
     * Placeholders a template's body must contain: without them the mail has no purpose.
     */
    private const REQUIRED_FIELDS = [
        3 => ['paymentlink'],
        6 => ['paymentlink'],
        7 => ['message'],
        4 => ['confirmationlink'],
    ];

    /**
     * The placeholders the edit screen offers for a template, grouped by language key of the
     * group heading: [group => [tag, ...]].
     */
    public static function placeholders(int $mailid): array
    {
        $order = self::TEMPLATE_FIELDS[$mailid] ?? [];

        return array_filter([
            'COM_TICKETSTATION_TEMPLATE_FIELDS_CLIENT'  => self::COMMON_FIELDS['COM_TICKETSTATION_TEMPLATE_FIELDS_CLIENT'],
            'COM_TICKETSTATION_TEMPLATE_FIELDS_ORDER'   => $order,
            'COM_TICKETSTATION_TEMPLATE_FIELDS_COMPANY' => self::COMMON_FIELDS['COM_TICKETSTATION_TEMPLATE_FIELDS_COMPANY'],
        ]);
    }

    /**
     * The placeholders a template's body must contain.
     */
    public static function requiredPlaceholders(int $mailid): array
    {
        return self::REQUIRED_FIELDS[$mailid] ?? [];
    }

    /**
     * The required placeholders missing from a mail body, as tags ("{paymentlink}").
     */
    public static function missingPlaceholders(int $mailid, string $body): array
    {
        $missing = [];

        foreach (self::requiredPlaceholders($mailid) as $tag) {
            if (strpos($body, '{' . $tag . '}') === false && strpos($body, '%%' . strtoupper($tag) . '%%') === false) {
                $missing[] = '{' . $tag . '}';
            }
        }

        return $missing;
    }

    /**
     * The order placeholders shared by the mails about an order: its number, date, the list
     * of its tickets and its total, formatted with the date and price format of the
     * Configuration.
     */
    public static function orderVariables(int $ordercode): array
    {
        $payment = new PaymentAPI($ordercode);
        $config  = $payment->getConfig();
        $db      = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('orderdate') . ')')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . $ordercode);

        $db->setQuery($query);
        $orderdate = $db->loadResult();

        $total = OrderTotals::get($ordercode)->total;

        return [
            'ordercode' => $ordercode,
            'orderdate' => $orderdate ? Date::_($orderdate, $config->dateformat) : '',
            'orderlist' => $payment->getOrderList(),
            'price'     => TicketstationFunctions::showprice($config->priceformat, $total, $config->valuta),
        ];
    }

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
        $this->setSenderVariables();

        return $this;
    }

    /**
     * The sender is configured centrally; falls back on the company details when left empty.
     */
    private function setSenderVariables()
    {
        $config = $this->getConfig();

        $variables = [
            'from_name'  => trim((string) $config->from_name) ?: trim((string) $config->companyname),
            'from_email' => trim((string) $config->from_email) ?: trim((string) $config->email),
        ];

        $this->variables = array_merge($this->variables, $variables);
    }

    /**
     * A message made of the given (unsaved) subject and body, filled with example details
     * instead of those of a real order: the preview and the test mail of the edit screen.
     * The result renders and sends like any other message.
     *
     * @param   int     $mailid   The template the texts belong to (not used for the texts themselves).
     * @param   string  $subject  The subject as typed in the form.
     * @param   string  $body     The body as typed in the form.
     * @param   string  $email    Address and
     * @param   string  $name     name the example customer gets, so a test mail reaches the admin.
     */
    public static function sample(int $mailid, string $subject, string $body, string $email = '', string $name = ''): self
    {
        $message           = new self;
        $message->template = (object) ['mailid' => $mailid, 'mailsubject' => $subject, 'mailbody' => $body];
        $message->setSenderVariables();

        $name     = trim($name) !== '' ? trim($name) : Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_NAME');
        $customer = [
            'name'         => $name,
            'emailaddress' => $email,
            'phonenumber'  => '0123 456789',
            'address'      => Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_ADDRESS'),
            'zipcode'      => '1234 AB',
            'city'         => Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_CITY'),
        ];

        $link   = rtrim(Uri::root(), '/') . '/#sample';
        $event  = Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_EVENT');
        $orders = '<ul><li>2 &times; ' . Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_TICKET_ADULT') . '</li>'
            . '<li>1 &times; ' . Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_TICKET_CHILD') . '</li></ul>';

        $message->variables(array_merge($customer, [
            'ordercode'        => '123456',
            'orderdate'        => date('d-m-Y'),
            'orderlist'        => $orders,
            'price'            => Price::format(45, $message->getConfig()->valuta),
            'walletbuttons'    => '<p><em>' . Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_WALLET') . '</em></p>',
            'paymentlink'      => '<a href="' . $link . '">' . $link . '</a>',
            'confirmationlink' => '<a href="' . $link . '">' . $link . '</a>',
            'ticketlink'       => $link,
            'invoice_id'       => '2026-0001',
            'removal_days'     => '7',
            'eventname'        => $event,
            'eventdate'        => date('d-m-Y', strtotime('+30 days')),
            'eventtime'        => '20:00',
            'doorsopen'        => '19:30',
            'location'         => Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_VENUE'),
            'subject'          => Text::sprintf('COM_TICKETSTATION_TEMPLATE_SAMPLE_SUBJECT', $event),
            'message'          => Text::_('COM_TICKETSTATION_TEMPLATE_SAMPLE_MESSAGE'),
        ]));

        // send() wants a customer to send to
        $message->user = (object) $customer;

        return $message;
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
     * The customer has one name field. {name} is that full name, {firstname} its first part
     * and {lastname} the rest, so a greeting can still use the first name only.
     */
    private function setNameVariables()
    {
        $name = trim((string) ($this->variables['name'] ?? ''));

        if ($name === '') {
            return;
        }

        $this->variables['name']      = $name;
        $this->variables['firstname'] = PersonName::first($name);
        $this->variables['lastname']  = PersonName::rest($name);
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
        $this->setNameVariables();

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
        $this->setNameVariables();

        return $this;
    }

    public function showMessageVariables()
    {
        $result = [];
        $this->setDefaultVariables();

        return $this->variables;
    }

    /**
     * Prepares and sends the final email.
     *
     * @return  bool  Whether the mail was handed over to the mail server. Callers that record a
     *                mail as sent (pdfsent, the invoice's sent flag, the order history) only do
     *                so on true.
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
        $send_to_name  = $this->variables['name'];

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

        foreach ($this->stringAttachments as $attachment)
        {
            $mailer->addStringAttachment($attachment['content'], $attachment['name'], 'base64', $attachment['type']);
        }

        // Joomla's mailer returns false or throws (mail switched off, SMTP error); either way
        // the mail didn't go out.
        try
        {
            $sent = (bool) $mailer->Send();
        }
        catch (\Throwable $e)
        {
            $sent = false;
        }

        if ( ! $sent)
        {
            Factory::getApplication()
                ->enqueueMessage(Text::_('COM_TICKETSTATION_SENDMAIL_FAILED_MESSAGE_CLASS'), 'error');
        }

        return $sent;
    }

    public function showMessage()
    {
        return $this->getBody();
    }

    public function showHeader()
    {
        return $this->getSubject();
    }

    /**
     * Adds a placeholder at the end of the mail body when the template doesn't place it
     * itself, so something new (like the wallet buttons) reaches customers without every site
     * having to edit its templates first. Call after id().
     */
    public function appendPlaceholder(string $tag)
    {
        if (isset($this->template->mailbody)
            && strpos($this->template->mailbody, '{' . $tag . '}') === false
            && strpos($this->template->mailbody, '%%' . strtoupper($tag) . '%%') === false)
        {
            $this->template->mailbody .= '{' . $tag . '}';
        }

        return $this;
    }

    /**
     * Attaches generated content (no file on disk), e.g. the order's calendar file.
     */
    public function stringAttachment(string $content, string $name, string $type = 'application/octet-stream')
    {
        if ($content !== '')
        {
            $this->stringAttachments[] = ['content' => $content, 'name' => $name, 'type' => $type];
        }

        return $this;
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