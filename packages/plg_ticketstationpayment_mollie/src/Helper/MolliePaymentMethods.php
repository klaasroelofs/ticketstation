<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_mollie
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Mollie\Helper;

use Joomla\CMS\Language\Text;
use Mollie\Api\MollieApiClient;

defined('_JEXEC') or die;

/**
 * The Mollie payment methods an admin may offer at checkout, stored as a list in the parameters of
 * the plugin ("payment_methods"). Only methods that settle straight away are offered: bank
 * transfer stays "open" for days while the seats are held, and pay-later methods use the
 * "authorized" status, which the webhook does not handle.
 */
class MolliePaymentMethods
{
    /**
     * Mollie method id => brand name. Methods without a brand name get a language key.
     */
    public const METHODS = [
        'ideal'      => 'iDEAL | Wero',
        'bancontact' => 'Bancontact',
        'creditcard' => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_METHOD_CREDITCARD',
        'applepay'   => 'Apple Pay',
        'paypal'     => 'PayPal',
        'kbc'        => 'KBC/CBC',
        'belfius'    => 'Belfius',
        'giftcard'   => 'PLG_TICKETSTATIONPAYMENT_MOLLIE_METHOD_GIFTCARD',
    ];

    /**
     * Used when nothing valid is stored, which is how the site worked before methods could be
     * chosen.
     */
    public const DEFAULT = ['ideal'];

    public static function label(string $method): string
    {
        $label = self::METHODS[$method] ?? $method;

        return str_starts_with($label, 'PLG_') ? Text::_($label) : $label;
    }

    /**
     * Keeps the known methods, in the order of METHODS, without duplicates.
     */
    public static function filter(array $methods): array
    {
        return array_values(array_intersect(array_keys(self::METHODS), $methods));
    }

    /**
     * Whether a list lets every customer pay: Apple Pay only shows on Apple devices, so it
     * cannot be the only method.
     */
    public static function isUsable(array $methods): bool
    {
        return array_diff($methods, ['applepay']) !== [];
    }

    /**
     * The methods to offer, from the stored value: a list, or a comma-separated string.
     *
     * @param   array|string|null  $stored
     */
    public static function fromConfig($stored): array
    {
        $stored  = is_array($stored) ? $stored : explode(',', (string) $stored);
        $methods = self::filter(array_map('strval', $stored));

        return self::isUsable($methods) ? $methods : self::DEFAULT;
    }

    /**
     * The method ids that are active in the Mollie account behind this API key, or null when
     * Mollie cannot be asked (no key, no connection, invalid key).
     */
    public static function activeInMollie(?string $apiKey): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($apiKey);

            // Wallets are only listed when asked for explicitly. Google Pay can't be passed as a
            // method; Mollie shows it within "creditcard", so it is only asked for to tell the
            // admin whether it is switched on in the Mollie Dashboard.
            $active = [];

            foreach ($mollie->methods->allEnabled(['includeWallets' => ['applepay', 'googlepay']]) as $method) {
                $active[] = $method->id;
            }

            return $active;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
