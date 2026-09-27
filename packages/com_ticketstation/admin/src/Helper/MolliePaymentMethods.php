<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Language\Text;
use Mollie\Api\MollieApiClient;

defined('_JEXEC') or die('Restricted access');

/**
 * The Mollie payment methods an admin may offer at checkout, stored comma-separated in
 * #__ticketstation_mollie.payment_methods and passed as the "method" list when a payment
 * is created. Only methods that settle straight away are offered: bank transfer stays
 * "open" for days while the seats are held, and pay-later methods use the "authorized"
 * status, which the webhook does not handle.
 */
class MolliePaymentMethods
{
    /**
     * Mollie method id => brand name. Methods without a brand name get a language key.
     */
    public const METHODS = [
        'ideal'      => 'iDEAL | Wero',
        'bancontact' => 'Bancontact',
        'creditcard' => 'COM_TICKETSTATION_MOLLIE_METHOD_CREDITCARD',
        'applepay'   => 'Apple Pay',
        'paypal'     => 'PayPal',
        'kbc'        => 'KBC/CBC',
        'belfius'    => 'Belfius',
        'giftcard'   => 'COM_TICKETSTATION_MOLLIE_METHOD_GIFTCARD',
    ];

    /**
     * Used when nothing valid is stored, which is how the site worked before methods
     * could be chosen.
     */
    public const DEFAULT = ['ideal'];

    public static function label(string $method): string
    {
        $label = self::METHODS[$method] ?? $method;

        return str_starts_with($label, 'COM_TICKETSTATION_') ? Text::_($label) : $label;
    }

    /**
     * Keeps the known methods, in the order of METHODS, without duplicates.
     */
    public static function filter(array $methods): array
    {
        return array_values(array_intersect(array_keys(self::METHODS), $methods));
    }

    /**
     * Whether a list lets every customer pay: Apple Pay only shows on Apple devices,
     * so it cannot be the only method.
     */
    public static function isUsable(array $methods): bool
    {
        return array_diff($methods, ['applepay']) !== [];
    }

    /**
     * The methods to offer, from the stored comma-separated value.
     */
    public static function fromConfig(?string $stored): array
    {
        $methods = self::filter(explode(',', (string) $stored));

        return self::isUsable($methods) ? $methods : self::DEFAULT;
    }

    /**
     * The method ids that are active in the Mollie account behind this API key, or null
     * when Mollie cannot be asked (no key, no connection, invalid key).
     */
    public static function activeInMollie(?string $apiKey): ?array
    {
        if (empty($apiKey)) {
            return null;
        }

        require_once JPATH_SITE . '/components/com_ticketstation/vendor/autoload.php';

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey($apiKey);

            // Apple Pay is only listed when asked for explicitly.
            $active = [];

            foreach ($mollie->methods->allActive(['includeWallets' => 'applepay']) as $method) {
                $active[] = $method->id;
            }

            return $active;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
