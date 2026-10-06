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

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=templates');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }
}