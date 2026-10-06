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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;

/**
 * The Payments screen: the provider that takes online payments, the currency, and the payment
 * plugins that are installed (with switching them on and off).
 */
class PaymentsController extends BaseController
{
    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Payments';

    public function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    public function main($cachable = false, $urlparams = array())
    {
        return parent::display($cachable, $urlparams);
    }

    /**
     * Saves the provider and the currency and shows the page again.
     */
    public function apply($cachable = false, $urlparams = [])
    {
        $jinput = Factory::getApplication()->getInput();
        $model  = $this->getModel('Payments');
        $link   = Uri::base() . 'index.php?option=com_ticketstation&view=payments';

        try {
            $model->store((string) $jinput->post->getString('payment_provider', ''), (string) $jinput->post->getString('payment_currency', ''));
        } catch (\RuntimeException $e) {
            $this->setRedirect($link, $e->getMessage(), 'error');

            return false;
        }

        $this->setRedirect($link, Text::_('COM_TICKETSTATION_PAYMENTS_SAVED'));

        return true;
    }

    /**
     * Saves and returns to the Control Panel.
     */
    public function save($cachable = false, $urlparams = [])
    {
        // On a failure, apply() keeps this screen open with the error.
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation', Text::_('COM_TICKETSTATION_PAYMENTS_SAVED'));
        }
    }

    /**
     * Switches a payment plugin on.
     */
    public function publish()
    {
        $this->switchPlugin(1);
    }

    /**
     * Switches a payment plugin off.
     */
    public function unpublish()
    {
        $this->switchPlugin(0);
    }

    /**
     * Doesn't save anything and returns to the Control Panel.
     */
    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation');
    }

    private function switchPlugin(int $value): void
    {
        $id   = Factory::getApplication()->getInput()->post->getInt('extension_id', 0);
        $link = Uri::base() . 'index.php?option=com_ticketstation&view=payments';

        try {
            $this->getModel('Payments')->setPluginEnabled($id, $value);
        } catch (\RuntimeException $e) {
            $this->setRedirect($link, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect($link, Text::_($value ? 'COM_TICKETSTATION_PAYMENTS_PLUGIN_ENABLED' : 'COM_TICKETSTATION_PAYMENTS_PLUGIN_DISABLED'));
    }
}
