<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * The web service Apple Wallet talks to for passes that can be updated (PassKit web service):
 * a device registers for a pass, asks which of its passes changed, fetches the new pass and
 * unregisters again. The address is in every pass (webServiceURL) and Apple adds /v1/... to it,
 * so it can't be a normal Joomla route: the system plugin hands the requests over to handle()
 * before Joomla's router sees them.
 *
 * The device authenticates with the pass's own token (Wallet::appleToken()). The pass itself is
 * built again from the ticket as it is now (WalletApple::pass()).
 *
 * @since 2.23.0
 */
class WalletAppleService
{
    public const PATH = 'ticketstation-wallet';

    /**
     * The webServiceURL of the passes: this site's own address, which Apple requires to be https.
     * Empty when the site isn't on https, so no pass gets a web service it can't reach.
     */
    public static function url(): string
    {
        $root = Uri::root();

        if (!str_starts_with($root, 'https://'))
        {
            return '';
        }

        return $root . 'index.php/' . self::PATH;
    }

    /**
     * Answers the request when it is for the web service.
     *
     * @return  bool  true when it was, and the answer has been sent
     */
    public static function handle(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if (!preg_match('#/' . self::PATH . '/v1/(.+)$#', $path, $match))
        {
            return false;
        }

        if (!WalletUpdate::appleEnabled())
        {
            self::respond(404);
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $parts  = array_map('rawurldecode', explode('/', trim($match[1], '/')));

        try
        {
            // devices/{device}/registrations/{passType}/{serial}: register or unregister
            if (count($parts) === 5 && $parts[0] === 'devices' && $parts[2] === 'registrations')
            {
                $method === 'POST' ? self::register($parts[1], $parts[3], $parts[4]) : null;
                $method === 'DELETE' ? self::unregister($parts[1], $parts[3], $parts[4]) : null;
            }
            // devices/{device}/registrations/{passType}: which passes changed
            elseif (count($parts) === 4 && $parts[0] === 'devices' && $parts[2] === 'registrations' && $method === 'GET')
            {
                self::changed($parts[1], $parts[3]);
            }
            // passes/{passType}/{serial}: the latest version of a pass
            elseif (count($parts) === 3 && $parts[0] === 'passes' && $method === 'GET')
            {
                self::latest($parts[1], $parts[2]);
            }
            // log: what devices report went wrong
            elseif ($parts === ['log'] && $method === 'POST')
            {
                self::log();
            }
        }
        catch (\Throwable $e)
        {
            // The device only sees the 500; the reason goes to the wallet log
            try
            {
                \Joomla\CMS\Log\Log::addLogger(['text_file' => 'com_ticketstation_wallet.php'], \Joomla\CMS\Log\Log::ALL, ['com_ticketstation.wallet']);
                \Joomla\CMS\Log\Log::add(
                    'Web service ' . $method . ' ' . $match[1] . ' failed: ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 300)
                    . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
                    \Joomla\CMS\Log\Log::ERROR,
                    'com_ticketstation.wallet'
                );
            }
            catch (\Throwable $ignored)
            {
            }

            self::respond(500);
        }

        self::respond(404);
    }

    private static function register(string $device, string $passType, string $serial): never
    {
        $pass = self::authorised($passType, $serial);
        $body = json_decode((string) file_get_contents('php://input'), true);
        $push = is_array($body) ? (string) ($body['pushToken'] ?? '') : '';

        if ($push === '')
        {
            self::respond(400);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT id FROM ' . $db->quoteName('#__ticketstation_wallet_devices')
            . ' WHERE device_id = ' . $db->quote($device) . ' AND pass_row = ' . (int) $pass->id);
        $existing = (int) $db->loadResult();

        if ($existing)
        {
            $db->setQuery('UPDATE ' . $db->quoteName('#__ticketstation_wallet_devices')
                . ' SET push_token = ' . $db->quote($push) . ' WHERE id = ' . $existing)->execute();

            self::respond(200);
        }

        // insertObject() takes the object by reference, so it can't be built in the call
        $row = (object) [
            'device_id'  => $device,
            'pass_row'   => (int) $pass->id,
            'push_token' => $push,
            'created'    => Date::localNow(),
        ];

        $db->insertObject('#__ticketstation_wallet_devices', $row);

        self::respond(201);
    }

    private static function unregister(string $device, string $passType, string $serial): never
    {
        $pass = self::authorised($passType, $serial);
        $db   = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('DELETE FROM ' . $db->quoteName('#__ticketstation_wallet_devices')
            . ' WHERE device_id = ' . $db->quote($device) . ' AND pass_row = ' . (int) $pass->id)->execute();

        self::respond(200);
    }

    /**
     * The serial numbers of the device's passes that changed since the tag it got last time.
     */
    private static function changed(string $device, string $passType): never
    {
        $since = (int) ($_GET['passesUpdatedSince'] ?? 0);
        $db    = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT p.pass_id, p.updated_at FROM ' . $db->quoteName('#__ticketstation_wallet_devices', 'd')
            . ' INNER JOIN ' . $db->quoteName('#__ticketstation_wallet_passes', 'p') . ' ON p.id = d.pass_row'
            . ' WHERE d.device_id = ' . $db->quote($device) . ' AND p.wallet = ' . $db->quote(Wallet::APPLE)
            . ' AND p.pass_id LIKE ' . $db->quote($db->escape($passType, true) . '/%', false)
            . ' AND p.updated_at > ' . $since);
        $rows = $db->loadObjectList();

        if (!$rows)
        {
            self::respond(204);
        }

        $serials = [];
        $latest  = 0;

        foreach ($rows as $row)
        {
            $serials[] = substr($row->pass_id, strlen($passType) + 1);
            $latest    = max($latest, (int) $row->updated_at);
        }

        self::respond(200, json_encode(['serialNumbers' => $serials, 'lastUpdated' => (string) $latest]), 'application/json');
    }

    /**
     * The pass as it should be now, built from the ticket.
     */
    private static function latest(string $passType, string $serial): never
    {
        $pass = self::authorised($passType, $serial);

        $modified = (int) floor(max((int) $pass->updated_at, 1000 * strtotime((string) $pass->created)) / 1000);

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $modified)
        {
            self::respond(304);
        }

        // The ticket was removed: the pass stays in the wallet as a void, expired pass, which was
        // made when the ticket was removed and holds nothing about the customer
        if ((int) $pass->removed === 1)
        {
            $json = json_decode((string) $pass->snapshot, true);

            if (!is_array($json))
            {
                self::respond(404);
            }

            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');

            self::respond(200, WalletApple::package($json, WalletApple::images()), 'application/vnd.apple.pkpass');
        }

        Factory::getApplication()->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR);

        $tickets = Wallet::tickets((int) $pass->ordercode, false);
        $ticket  = null;
        $valid   = [];

        foreach ($tickets as $candidate)
        {
            if ((int) $candidate->refund_state < Refund::TICKET_INVALID && (int) ($candidate->blacklisted ?? 0) === 0)
            {
                $valid[] = $candidate;
            }

            if ((int) $candidate->orderid === (int) $pass->orderid)
            {
                $ticket = $candidate;
            }
        }

        if ($ticket === null)
        {
            self::respond(404);
        }

        $isValid  = in_array($ticket, $valid, true);
        $position = 1;

        foreach ($valid as $index => $candidate)
        {
            if ((int) $candidate->orderid === (int) $ticket->orderid)
            {
                $position = $index + 1;
            }
        }

        $message = null;

        // A negative message_shown is a message this pass shows without a notification
        if ((int) $pass->message_shown !== 0)
        {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $db->setQuery('SELECT body FROM ' . $db->quoteName('#__ticketstation_wallet_messages') . ' WHERE id = ' . abs((int) $pass->message_shown));
            $message = $db->loadResult() ?: null;
        }

        $content = WalletApple::pass($ticket, $position, max(1, count($valid)), WalletApple::images(), $message, $isValid, (int) $pass->message_shown >= 0);

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');

        self::respond(200, $content, 'application/vnd.apple.pkpass');
    }

    private static function log(): never
    {
        $body = json_decode((string) file_get_contents('php://input'), true);

        if (is_array($body) && !empty($body['logs']))
        {
            \Joomla\CMS\Log\Log::addLogger(['text_file' => 'com_ticketstation_wallet.php'], \Joomla\CMS\Log\Log::ALL, ['com_ticketstation.wallet']);

            foreach ((array) $body['logs'] as $line)
            {
                \Joomla\CMS\Log\Log::add('Apple Wallet device: ' . mb_substr((string) $line, 0, 500), \Joomla\CMS\Log\Log::WARNING, 'com_ticketstation.wallet');
            }
        }

        self::respond(200);
    }

    /**
     * The recorded pass for a serial number, when the device sent the pass's own token;
     * otherwise the request is answered with 401 or 404.
     */
    private static function authorised(string $passType, string $serial): object
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        if ($header === '' && function_exists('getallheaders'))
        {
            foreach (getallheaders() as $name => $value)
            {
                if (strtolower($name) === 'authorization')
                {
                    $header = (string) $value;
                }
            }
        }

        $token = str_starts_with($header, 'ApplePass ') ? substr($header, 10) : '';

        if ($token === '' || !hash_equals(Wallet::appleToken($serial), $token))
        {
            self::respond(401);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('SELECT * FROM ' . $db->quoteName('#__ticketstation_wallet_passes')
            . ' WHERE wallet = ' . $db->quote(Wallet::APPLE) . ' AND pass_id = ' . $db->quote($passType . '/' . $serial));
        $pass = $db->loadObject();

        if (!$pass)
        {
            self::respond(404);
        }

        return $pass;
    }

    /**
     * Sends the answer and ends the request.
     */
    private static function respond(int $status, string $body = '', string $type = ''): never
    {
        while (ob_get_level() > 0)
        {
            ob_end_clean();
        }

        http_response_code($status);

        if ($type !== '')
        {
            header('Content-Type: ' . $type);
        }

        header('Cache-Control: no-store');

        echo $body;

        exit;
    }
}
