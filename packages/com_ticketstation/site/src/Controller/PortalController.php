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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Calendar;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Portal;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SiteCaptcha;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * The customer portal "My orders": getting in with a link, and the downloads of an order.
 *
 * Who may see which order is decided by Portal alone; every task here asks it first. The tasks that
 * only read (the links from a mail, the downloads) are exempt from the token check, see CsrfGate;
 * requesting a link and leaving carry a token like any form.
 *
 * @since 2.36.0
 */
class PortalController extends BaseController
{
    /**
     * The portal page, with the Itemid of the request when there is one.
     */
    private function portalUrl(): string
    {
        $itemid = $this->input->getInt('Itemid', 0) ?: TicketstationFunctions::getSiteItemid();

        return Route::_('index.php?option=com_ticketstation&view=portal' . ($itemid ? '&Itemid=' . $itemid : ''), false);
    }

    /**
     * Every task needs the portal to be on: with it off, nothing here exists.
     */
    private function requireOn(): void
    {
        if (!Portal::enabled())
        {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
    }

    /**
     * The form on the portal page: mails the link to the address entered. The page always answers
     * the same, whether or not the address has orders, so the form can't be used to find out who
     * has ordered.
     */
    public function request()
    {
        $this->requireOn();

        $app   = Factory::getApplication();
        $email = trim($this->input->post->getString('email', ''));

        if (!MailHelper::isEmailAddress($email))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_LOSTTICKETS_INVALID_EMAIL'), 'error');
            $this->setRedirect($this->portalUrl());

            return;
        }

        if (!SiteCaptcha::check('portal'))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_CAPTCHA_INCORRECT'), 'error');
            $this->setRedirect($this->portalUrl());

            return;
        }

        // The mail helpers report their own failures as messages; drop those so a failure
        // doesn't give away that the address has orders.
        $queue = $app->getMessageQueue(true);

        Portal::requestLink($email, Portal::visitorIp());

        $app->getMessageQueue(true);

        foreach ($queue as $message)
        {
            $app->enqueueMessage($message['message'], $message['type']);
        }

        $app->enqueueMessage(
            Text::sprintf('COM_TICKETSTATION_PORTAL_REQUEST_SENT', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), Portal::LINK_MINUTES),
            'message'
        );
        $this->setRedirect($this->portalUrl());
    }

    /**
     * The link in the portal mail: opens the orders of that address. The key is taken out of the
     * address right away, by redirecting to the page.
     */
    public function enter()
    {
        $this->requireOn();

        if (!$this->clicked())
        {
            return;
        }

        $input = $this->input;

        if (!Portal::enter($input->getInt('c', 0), $input->getInt('x', 0), $input->getAlnum('key', '')))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_LINK_INVALID'), 'warning');
        }

        $this->setRedirect($this->portalUrl());
    }

    /**
     * The link in the ticket mail: opens that one order.
     */
    public function order()
    {
        $this->requireOn();

        if (!$this->clicked())
        {
            return;
        }

        $input = $this->input;

        if (!Portal::grantOrder($input->getInt('order', 0), $input->getAlnum('key', '')))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_ORDER_LINK_INVALID'), 'warning');
        }

        $this->setRedirect($this->portalUrl());
    }

    /**
     * Links the address opened in this session to the Joomla account the visitor is logged in
     * with, for good. The mail link opened in this session is the confirmation.
     */
    public function link()
    {
        $this->requireOn();

        $app = Factory::getApplication();

        if (!Portal::accountsEnabled())
        {
            $this->refuse();
        }

        $linked = Portal::link();

        $app->enqueueMessage(
            Text::_($linked ? 'COM_TICKETSTATION_PORTAL_LINKED' : 'COM_TICKETSTATION_PORTAL_LINK_FAILED'),
            $linked ? 'message' : 'warning'
        );
        $this->setRedirect($this->portalUrl());
    }

    /**
     * Removes the link of an address from the account.
     */
    public function unlink()
    {
        $this->requireOn();

        $app = Factory::getApplication();

        if (!Portal::accountsEnabled())
        {
            $this->refuse();
        }

        Portal::unlink($this->input->post->getInt('link', 0));

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_UNLINKED'), 'message');
        $this->setRedirect($this->portalUrl());
    }

    /**
     * Ends the visit; nothing of the portal stays open in this browser.
     */
    public function leave()
    {
        $this->requireOn();

        Portal::leave();

        Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_LEFT'), 'message');
        $this->setRedirect($this->portalUrl());
    }

    /**
     * The tickets of an order as a PDF, while there is a valid ticket for an event that isn't over.
     */
    public function download()
    {
        $this->requireOn();

        $ordercode = $this->input->getInt('order', 0);

        if (!Portal::canAccess($ordercode) || !Portal::usable($ordercode) || ($file = Portal::ticketFile($ordercode)) === '')
        {
            $this->refuse();
        }

        $this->send($file, 'application/pdf');
    }

    /**
     * The invoice of an order; it stays available after the event.
     */
    public function invoice()
    {
        $this->requireOn();

        $ordercode = $this->input->getInt('order', 0);

        if (!Portal::canAccess($ordercode) || ($file = Portal::invoiceFile($ordercode)) === '')
        {
            $this->refuse();
        }

        $this->send($file, 'application/pdf');
    }

    /**
     * The events of an order as a calendar file.
     */
    public function calendar()
    {
        $this->requireOn();

        $ordercode = $this->input->getInt('order', 0);

        if (!Portal::canAccess($ordercode) || !Portal::usable($ordercode) || ($ics = Calendar::ics($ordercode)) === '')
        {
            $this->refuse();
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-' . $ordercode . '.ics"');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . strlen($ics));
        echo $ics;

        Factory::getApplication()->close();
    }

    /**
     * The order and the tickets picked on an order card (a form), or back to the page with a
     * message when the visitor has no access, nothing can be used any more or nothing was picked.
     *
     * @return  array{0: int, 1: int[]}
     */
    private function picked(): array
    {
        $ordercode = $this->input->post->getInt('order', 0);

        if (!Portal::canAccess($ordercode) || !Portal::usable($ordercode))
        {
            $this->refuse();
        }

        $orderids = Portal::pick($ordercode, $this->input->post->get('tickets', [], 'array'));

        if (!$orderids)
        {
            $app = Factory::getApplication();

            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_SELECT_NONE'), 'warning');
            $app->redirect($this->portalUrl());
        }

        return [$ordercode, $orderids];
    }

    /**
     * Only the tickets picked, as one PDF.
     */
    public function selection()
    {
        $this->requireOn();

        [$ordercode, $orderids] = $this->picked();

        $pdf = Portal::ticketsPdf($ordercode, $orderids);

        if ($pdf === '')
        {
            $this->refuse();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Tickets-' . $ordercode . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
        echo $pdf;

        Factory::getApplication()->close();
    }

    /**
     * The tickets picked, mailed to the address of the order.
     */
    public function mailself()
    {
        $this->requireOn();

        [$ordercode, $orderids] = $this->picked();

        $this->deliver($ordercode, $orderids, '', '');
    }

    /**
     * The tickets picked, mailed to someone else with a short message.
     */
    public function mailother()
    {
        $this->requireOn();

        if (!Portal::sharingEnabled())
        {
            $this->refuse();
        }

        [$ordercode, $orderids] = $this->picked();

        $to   = trim($this->input->post->getString('recipient', ''));
        $note = trim($this->input->post->getString('note', ''));

        if (!MailHelper::isEmailAddress($to))
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_RECIPIENT_INVALID'), 'error');
            $this->setRedirect($this->portalUrl());

            return;
        }

        $this->deliver($ordercode, $orderids, strtolower($to), mb_substr($note, 0, 200));
    }

    /**
     * Sends the mail of mailself() and mailother() and answers on the page.
     */
    private function deliver(int $ordercode, array $orderids, string $to, string $note): void
    {
        $app = Factory::getApplication();

        if (!Portal::maySend($ordercode, Portal::visitorIp()))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_TOO_OFTEN'), 'warning');
            $this->setRedirect($this->portalUrl());

            return;
        }

        if (Portal::sendTickets($ordercode, $orderids, $to, $note))
        {
            $app->enqueueMessage(
                Text::sprintf(
                    'COM_TICKETSTATION_PORTAL_MAIL_SENT',
                    htmlspecialchars($to !== '' ? $to : Portal::customerAddress($ordercode), ENT_QUOTES, 'UTF-8')
                ),
                'message'
            );
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_MAIL_FAILED'), 'error');
        }

        $this->setRedirect($this->portalUrl());
    }

    /**
     * Whether the request to open a link is a click (see Portal::isUserNavigation()). When it isn't,
     * nothing is opened and the visitor goes to the page with a message.
     */
    private function clicked(): bool
    {
        if (Portal::isUserNavigation())
        {
            return true;
        }

        Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_LINK_NOT_CLICKED'), 'warning');
        $this->setRedirect($this->portalUrl());

        return false;
    }

    /**
     * Back to the portal with a message, for a download that isn't there or isn't theirs. The
     * answer is the same for both, so an order number can't be tested.
     */
    private function refuse(): never
    {
        $app = Factory::getApplication();

        $app->enqueueMessage(Text::_('COM_TICKETSTATION_PORTAL_NOT_AVAILABLE'), 'warning');
        $app->redirect($this->portalUrl());
    }

    private function send(string $path, string $type): never
    {
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
        readfile($path);

        Factory::getApplication()->close();
    }
}
