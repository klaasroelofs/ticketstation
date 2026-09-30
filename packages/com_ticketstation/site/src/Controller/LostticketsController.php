<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SiteCaptcha;

/**
 * Ticketstation Losttickets Controller: the form on the "Lost tickets" page.
 */
class LostticketsController extends BaseController
{
    /**
     * Mails the tickets for upcoming events to the address entered. The page always answers
     * with the same message, whether or not anything was found, so the form can't be used to
     * find out which addresses have ordered tickets.
     */
    public function send()
    {
        $app    = Factory::getApplication();
        $itemid = $this->input->getInt('Itemid', 0);
        $back   = Route::_('index.php?option=com_ticketstation&view=losttickets' . ($itemid ? '&Itemid=' . $itemid : ''), false);

        $email = trim($this->input->post->getString('email', ''));

        if (!MailHelper::isEmailAddress($email))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_LOSTTICKETS_INVALID_EMAIL'), 'error');
            $this->setRedirect($back);

            return;
        }

        if (!SiteCaptcha::check('losttickets'))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_CAPTCHA_INCORRECT'), 'error');
            $this->setRedirect($back);

            return;
        }

        // The mail helpers report their own failures as messages; drop those so a failure
        // doesn't give away that the address has orders.
        $queue = $app->getMessageQueue(true);

        $this->getModel('Losttickets')->resend($email);

        $app->getMessageQueue(true);

        foreach ($queue as $message)
        {
            $app->enqueueMessage($message['message'], $message['type']);
        }

        $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_LOSTTICKETS_SENT', htmlspecialchars($email, ENT_QUOTES, 'UTF-8')), 'message');
        $this->setRedirect($back);
    }
}
