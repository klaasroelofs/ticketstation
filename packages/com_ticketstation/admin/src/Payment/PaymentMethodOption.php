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
 * One payment method the customer can choose at checkout.
 */
final class PaymentMethodOption
{
    /**
     * @param   string  $id       The method as the provider calls it (passed back in PaymentRequest::$method).
     * @param   string  $label    Name shown to the customer.
     * @param   string  $iconUrl  Address of a small logo, or '' for none.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $iconUrl = '',
    ) {
    }
}
