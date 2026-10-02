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
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Wallet;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletApple;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WalletGoogle;

/**
 * "Add to Apple Wallet" / "Add to Google Wallet": the links in the ticket mail and on the
 * payment result page. Read-only, and authorised by the signed key in the link (see
 * Wallet::token()) rather than a browser session, because they're opened from a mail.
 *
 * @since 2.16.0
 */
class WalletController extends BaseController
{
    /**
     * The tickets of the order as an Apple Wallet pass (.pkpass) or bundle (.pkpasses).
     */
    public function apple()
    {
        $tickets = $this->tickets(Wallet::APPLE);

        try
        {
            $pass = WalletApple::download($tickets);
        }
        catch (\Throwable $e)
        {
            $this->fail($e);
        }

        header('Content-Type: ' . $pass['type']);
        header('Content-Disposition: attachment; filename="' . $pass['filename'] . '"');
        header('Content-Length: ' . strlen($pass['content']));
        header('Cache-Control: private, no-store');
        echo $pass['content'];

        Factory::getApplication()->close();
    }

    /**
     * To Google Wallet, which shows the tickets and lets the customer save them.
     */
    public function google()
    {
        $tickets = $this->tickets(Wallet::GOOGLE);

        try
        {
            $url = WalletGoogle::saveUrl($tickets);
        }
        catch (\Throwable $e)
        {
            $this->fail($e);
        }

        header('Cache-Control: private, no-store');
        Factory::getApplication()->redirect($url);
    }

    /**
     * The logo Google Wallet fetches for the passes. Public, like any image on the site.
     */
    public function logo()
    {
        $app = Factory::getApplication();
        $png = Wallet::googleReady() ? WalletGoogle::logo() : null;

        if ($png === null)
        {
            http_response_code(404);
            $app->close();
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        header('Cache-Control: public, max-age=86400');
        echo $png;

        $app->close();
    }

    /**
     * The tickets of the order in the link, or back to the home page with a message when the
     * link isn't valid, the wallet is switched off or the order has no tickets (any more).
     */
    private function tickets(string $wallet): array
    {
        $input     = Factory::getApplication()->getInput();
        $ordercode = $input->getInt('order', 0);
        $contact   = (new Config)->getContactEmail();

        if (!Wallet::checkToken($ordercode, $input->getAlnum('key', '')))
        {
            $this->leave(Text::sprintf('COM_TICKETSTATION_WALLET_LINK_INVALID', $contact));
        }

        if (!in_array($wallet, Wallet::available(), true))
        {
            $this->leave(Text::sprintf('COM_TICKETSTATION_WALLET_UNAVAILABLE', $contact));
        }

        $tickets = Wallet::tickets($ordercode);

        if (!$tickets)
        {
            $this->leave(Text::sprintf('COM_TICKETSTATION_WALLET_NO_TICKETS', $contact));
        }

        return $tickets;
    }

    /**
     * Logs what went wrong for the site owner and tells the customer.
     */
    private function fail(\Throwable $e): never
    {
        Log::addLogger(['text_file' => 'com_ticketstation_wallet.php'], Log::ALL, ['com_ticketstation.wallet']);
        Log::add('Wallet pass failed: ' . $e->getMessage(), Log::ERROR, 'com_ticketstation.wallet');

        $this->leave(Text::sprintf('COM_TICKETSTATION_WALLET_FAILED', (new Config)->getContactEmail()));
    }

    private function leave(string $message): never
    {
        $app = Factory::getApplication();
        $app->enqueueMessage($message, 'warning');
        $app->redirect(Uri::root());
    }
}
