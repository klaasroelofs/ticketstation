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

use Joomla\CMS\Http\HttpFactory;

/**
 * The Google Wallet API, for changing passes that customers already have: the same service
 * account that signs the "Save to Google Wallet" links gets an access token, and the class
 * (the event) and object (the ticket) are patched. The service account has to be added as a
 * user of the issuer in the Google Pay & Wallet Console for this.
 *
 * @since 2.22.0
 */
class WalletGoogleApi
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/wallet_object.issuer';
    private const BASE      = 'https://walletobjects.googleapis.com/walletobjects/v1/';

    /** @var array{token: string, until: int}|null */
    private static $token;

    /**
     * Live updates are switched on and Google Wallet can be reached with the stored key.
     */
    public static function ready(): bool
    {
        return (int) (Wallet::config()->wallet_google_updates ?? 0) === 1 && Wallet::googleReady();
    }

    /**
     * Changes a pass class. Missing from Google (nobody ever saved a pass of it) is not an error.
     *
     * @throws  \RuntimeException  when Google refuses
     */
    public static function patchClass(string $classId, array $data): ?array
    {
        return self::send('PATCH', 'eventTicketClass/' . rawurlencode($classId), $data);
    }

    /**
     * Changes a pass object.
     *
     * @return  bool  false when Google doesn't have the pass: the customer never saved it
     *
     * @throws  \RuntimeException  when Google refuses
     */
    public static function patchObject(string $objectId, array $data): bool
    {
        return self::send('PATCH', 'eventTicketObject/' . rawurlencode($objectId), $data) !== null;
    }

    /**
     * Shows a message on a pass, with a notification on the customer's device.
     *
     * @return  bool  false when Google doesn't have the pass
     *
     * @throws  \RuntimeException  when Google refuses
     */
    public static function addMessage(string $objectId, string $header, string $body): bool
    {
        return self::send('POST', 'eventTicketObject/' . rawurlencode($objectId) . '/addMessage', [
            'message' => ['header' => $header, 'body' => $body, 'messageType' => 'TEXT_AND_NOTIFY'],
        ]) !== null;
    }

    /**
     * @return  array|null  what Google answered, or null for "not found"
     */
    private static function send(string $method, string $path, array $body): ?array
    {
        $http    = HttpFactory::getHttp();
        $url     = self::BASE . $path;
        $json    = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = ['Authorization' => 'Bearer ' . self::accessToken(), 'Content-Type' => 'application/json'];

        $response = $method === 'PATCH'
            ? $http->patch($url, $json, $headers, 20)
            : $http->post($url, $json, $headers, 20);

        if ($response->code === 404)
        {
            return null;
        }

        if ($response->code < 200 || $response->code >= 300)
        {
            throw new \RuntimeException(self::error((int) $response->code, (string) $response->body));
        }

        return (array) json_decode((string) $response->body, true);
    }

    /**
     * An access token for the service account, kept for the rest of the request.
     */
    private static function accessToken(): string
    {
        if (self::$token !== null && self::$token['until'] > time() + 60)
        {
            return self::$token['token'];
        }

        $config  = Wallet::config();
        $account = WalletGoogle::serviceAccount($config->wallet_google_key ?? null);

        if ($account === null)
        {
            throw new \RuntimeException('Google Wallet has no service account key.');
        }

        $now = time();
        $jwt = WalletGoogle::jwt([
            'iss'   => $account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $account['private_key']);

        $response = HttpFactory::getHttp()->post(
            self::TOKEN_URL,
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            20
        );

        $data = json_decode((string) $response->body, true);

        if ($response->code !== 200 || empty($data['access_token']))
        {
            throw new \RuntimeException('Google refused the service account: ' . self::error((int) $response->code, (string) $response->body));
        }

        self::$token = ['token' => $data['access_token'], 'until' => $now + (int) ($data['expires_in'] ?? 3600)];

        return self::$token['token'];
    }

    /**
     * The message Google gave with an error, or the status code.
     */
    private static function error(int $code, string $body): string
    {
        $data    = json_decode($body, true);
        $message = $data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? '';

        return 'HTTP ' . $code . ($message !== '' && is_string($message) ? ': ' . $message : '');
    }
}
