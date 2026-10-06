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
 * A provider with payment methods. With more than one method the customer chooses on the payment
 * page of Ticketstation, and the payment is started with that method (PaymentRequest::$method), so
 * the provider's own screens don't ask again.
 */
interface MethodAwareInterface
{
    /**
     * The methods the customer may choose from at checkout, for a payment in this currency. The
     * customer doesn't choose when there is one or none.
     *
     * @return  PaymentMethodOption[]
     */
    public function getCheckoutMethods(string $currency): array;

    /**
     * Name of a payment method for the invoice and the Box Office, from the value stored with
     * the transaction (lower case).
     */
    public function methodLabel(string $storedMethod): string;
}
