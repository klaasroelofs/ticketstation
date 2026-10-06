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
 * The customer's way on to the provider's payment page.
 */
final class PaymentRedirect
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly string $providerPaymentId,
    ) {
    }
}
