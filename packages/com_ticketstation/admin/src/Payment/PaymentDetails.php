<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

defined('_JEXEC') or die;

/**
 * What a payment provider sent with a payment, as stored with the transaction (PaymentUpdate::$details,
 * a query string), made readable for the order screen.
 */
final class PaymentDetails
{
    /**
     * One row per value, named by its path ("amount / value"). The links to the provider's API and
     * empty values are left out.
     *
     * @return  array<string, string>
     */
    public static function rows(string $details): array
    {
        parse_str($details, $data);

        $rows = [];

        self::flatten($data, '', $rows);

        return $rows;
    }

    /**
     * The provider's own id of the payment (Mollie "id", Stripe "payment_intent"), '' when unknown.
     */
    public static function paymentId(array $rows): string
    {
        return (string) ($rows['id'] ?? $rows['payment_intent'] ?? '');
    }

    /**
     * 'live' or 'test' for a payment that says so (Mollie "mode", Stripe "livemode"), '' otherwise.
     */
    public static function mode(array $rows): string
    {
        $mode = (string) ($rows['mode'] ?? $rows['livemode'] ?? '');

        return in_array($mode, ['live', 'test'], true) ? $mode : '';
    }

    private static function flatten(array $data, string $prefix, array &$rows): void
    {
        foreach ($data as $key => $value) {
            if ($prefix === '' && $key === '_links') {
                continue;
            }

            $name = $prefix === '' ? (string) $key : $prefix . ' / ' . $key;

            if (is_array($value)) {
                self::flatten($value, $name, $rows);
            } elseif ((string) $value !== '') {
                $rows[$name] = (string) $value;
            }
        }
    }
}
