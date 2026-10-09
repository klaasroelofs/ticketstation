<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TemplateDefaults;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class TemplatesController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Venues';
    /**
     * @var mixed|null
     */
    private $id;

    function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);


    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'templates');
        parent::display();
    }

    public function edit()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'form');
        $jinput->set('view', 'templates');
        parent::display();
    }

    /**
     * Handle the apply task which saves the configuration settings and shows the page again
     */
    public function apply($cachable = false, $urlparams = []) {


        $app 	= Factory::getApplication();
        $jinput = $app->getInput();
        $post 	= $jinput->post->getArray();

        $post['mailbody'] = $jinput->get('mailbody', null, 'raw');

        $this->id = $jinput->get('cid', '0', 'INT');

        $editUrl = Uri::base() . 'index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . $this->id;

        // A payment-link mail without {paymentlink} (or a waiting-list mail without
        // {confirmationlink}) leaves the customer nothing to click: refuse it, and keep what
        // was typed so the form shows it again (see HtmlView::_displayForm()).
        $missing = eTicketsMessage::missingPlaceholders((int) $this->id, (string) $post['mailbody']);

        if ($missing)
        {
            $app->setUserState('com_ticketstation.edit.template.data', [
                'mailid'      => (int) $this->id,
                'mailsubject' => $post['mailsubject'] ?? '',
                'mailbody'    => $post['mailbody'],
            ]);
            $this->setRedirect($editUrl, Text::sprintf('COM_TICKETSTATION_TEMPLATE_REQUIRED_MISSING', implode(', ', $missing)), 'error');

            return false;
        }

        $model = $this->getModel('templates');

        if ($model->store($post))
        {
            $this->setRedirect($editUrl, Text::_('COM_TICKETSTATION_TEMPLATES_SAVED'));

            return true;
        }

        $this->setRedirect($editUrl, Text::_('COM_TICKETSTATION_TEMPLATES_NOTSAVED'), 'error');

        return false;
    }
    /**
     * Handle the save task which saves the configuration settings and returns to the Templates view
     */
    public function save($cachable = false, $urlparams = []) {
        // When saving fails, apply() has already sent the user back to the form with the reason.
        if ($this->apply())
        {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=templates', Text::_('COM_TICKETSTATION_TEMPLATES_SAVED'));
        }
    }

    /**
     * AJAX (assets/js/templates.js): the subject and body as typed in the form, filled with
     * example details. Nothing is saved.
     */
    public function preview()
    {
        $message = $this->sampleFromForm();

        $this->respond(['subject' => $message->getSubject(), 'body' => $message->getBody()]);
    }

    /**
     * AJAX (assets/js/templates.js): sends the mail as typed in the form, filled with example
     * details, to the logged-in admin. Nothing is saved; the attachments are left out.
     */
    public function testmail()
    {
        $app      = Factory::getApplication();
        $identity = $app->getIdentity();
        $message  = $this->sampleFromForm($identity->email, $identity->name);

        // Marks the mail as a test; the placeholders in the subject are still filled in on sending
        $message->template->mailsubject = '[' . Text::_('COM_TICKETSTATION_TEMPLATE_TESTMAIL_PREFIX') . '] ' . $message->template->mailsubject;
        $sent = $message->send();

        // send() reports a failure through the message queue, which would only show up on the
        // next page; the answer to this request carries it instead.
        $app->getMessageQueue(true);

        $this->respond($sent
            ? ['ok' => true, 'message' => Text::sprintf('COM_TICKETSTATION_TEMPLATE_TESTMAIL_SENT', $identity->email)]
            : ['ok' => false, 'message' => Text::_('COM_TICKETSTATION_TEMPLATE_TESTMAIL_FAILED')]);
    }

    /**
     * Puts the texts a fresh installation starts with back in the form. Nothing is saved until
     * the admin saves, so cancelling keeps the current texts.
     */
    public function resetdefault()
    {
        $app     = Factory::getApplication();
        $mailid  = $app->getInput()->getInt('cid', 0);
        $default = TemplateDefaults::get($mailid);
        $editUrl = Uri::base() . 'index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . $mailid;

        if (!$default) {
            $this->setRedirect($editUrl, Text::_('COM_TICKETSTATION_TEMPLATE_NO_DEFAULT'), 'error');

            return false;
        }

        $app->setUserState('com_ticketstation.edit.template.data', [
            'mailid'      => $mailid,
            'mailsubject' => $default['mailsubject'],
            'mailbody'    => $default['mailbody'],
        ]);
        $this->setRedirect($editUrl, Text::_('COM_TICKETSTATION_TEMPLATE_DEFAULT_LOADED'), 'warning');

        return true;
    }

    /**
     * The message the form describes: the subject and body as typed, with example details.
     */
    private function sampleFromForm(string $email = '', string $name = ''): eTicketsMessage
    {
        $input = Factory::getApplication()->getInput();

        return eTicketsMessage::sample(
            $input->getInt('cid', 0),
            $input->get('mailsubject', '', 'string'),
            $input->get('mailbody', '', 'raw'),
            $email,
            $name
        );
    }

    /**
     * Ends the request with a JSON answer.
     */
    private function respond(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        Factory::getApplication()->close();
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=templates');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }
}