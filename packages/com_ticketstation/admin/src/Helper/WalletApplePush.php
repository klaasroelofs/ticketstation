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

/**
 * The empty push notification that tells an iPhone a pass has changed, so it asks the web
 * service for the new version (see WalletAppleService). Sent to the Apple Push Notification
 * service over HTTP/2, authenticated with the same Pass Type ID certificate that signs the
 * passes.
 *
 * @since 2.23.0
 */
class WalletApplePush
{
    private const HOST = 'https://api.push.apple.com/3/device/';

    /**
     * Whether this server's cURL can talk HTTP/2, which Apple's push service requires.
     */
    public static function supported(): bool
    {
        if (!function_exists('curl_init') || !defined('CURL_VERSION_HTTP2') || !defined('CURLOPT_SSLCERT_BLOB'))
        {
            return false;
        }

        $version = curl_version();

        return (bool) ($version['features'] & CURL_VERSION_HTTP2);
    }

    /**
     * A one-line description of what this server can do, for the Configuration.
     */
    public static function describe(): string
    {
        if (!function_exists('curl_init'))
        {
            return 'cURL is not available';
        }

        $version = curl_version();

        return 'cURL ' . $version['version'] . ', ' . $version['ssl_version'] . ', HTTP/2 ' . (self::supported() ? 'yes' : 'no');
    }

    /**
     * Sends the notification to one device.
     *
     * @return  array{status: int, reason: string}  status 200 is accepted; 410 means the device
     *                                              no longer has the pass; 0 means no answer
     */
    public static function send(string $pushToken): array
    {
        $config = Wallet::config();
        $info   = WalletApple::certificateInfo($config->wallet_apple_cert ?? null);

        if ($info === null || empty($config->wallet_apple_key))
        {
            return ['status' => 0, 'reason' => 'Apple Wallet has no certificate'];
        }

        if (!self::supported())
        {
            return ['status' => 0, 'reason' => 'This server cannot do HTTP/2 with cURL, which Apple push notifications need'];
        }

        $ch = curl_init(self::HOST . rawurlencode($pushToken));

        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => ['apns-topic: ' . $info['passTypeId']],
            CURLOPT_SSLCERTTYPE    => 'PEM',
            CURLOPT_SSLCERT_BLOB   => (string) $config->wallet_apple_cert,
            CURLOPT_SSLKEYTYPE     => 'PEM',
            CURLOPT_SSLKEY_BLOB    => (string) $config->wallet_apple_key,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);

        curl_close($ch);

        if ($body === false)
        {
            return ['status' => 0, 'reason' => $error !== '' ? $error : 'no answer'];
        }

        $reason = (string) (json_decode((string) $body, true)['reason'] ?? '');

        return ['status' => $status, 'reason' => $reason];
    }
}
