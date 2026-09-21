<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Waitinglist Controller
 */
class WaitinglistController extends BaseController
{
    protected $default_view = 'Waitinglist';

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'waitinglist');
        parent::display();
    }

    /**
     * Manually confirms the selected waiting-list entries, exactly as if the customer had
     * clicked the confirmation link in their email (see WaitingList::confirm()). Useful for
     * phone signups or when a confirmation email bounces.
     */
    function confirm()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count($cid) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SELECT_ITEM'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');

            return;
        }

        $model = $this->getModel('waitinglist');

        if ( ! $model->confirm($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_PROCESSING_WAITINGLIST_ITEM'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');

            return;
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_CONFIRMED_WAITINGLIST_ITEM'));
        $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');
    }

    function remove()
    {

        $app = Factory::getApplication();

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count($cid) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SELECT_ITEM'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');

            return;
        }

        $model = $this->getModel('waitinglist');

        if ( ! $model->remove($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_DELETE_WAITINGLIST_ITEM'), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');

            return;
        }

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_WAITINGLIST_DELETED'));
        $this->setRedirect('index.php?option=com_ticketstation&view=waitinglist');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }
}
