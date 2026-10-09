<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_stripe
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Stripe\Api;

defined('_JEXEC') or die;

/**
 * Checks the Stripe-Signature header of a webhook call: "t=<unix time>,v1=<hmac>[,v1=...]", where
 * the HMAC (SHA-256, key = the endpoint's signing secret "whsec_...") is over "<t>.<raw body>".
 * A call older (or newer) than the tolerance is refused, so a captured call can't be replayed later.
 */
final class WebhookSignature
{
    public const TOLERANCE = 300;

    public static function verify(string $rawBody, string $header, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = 0;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp <= 0 || !$signatures || abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
