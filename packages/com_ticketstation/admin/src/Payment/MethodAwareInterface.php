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
 * A provider with payment methods the admin can choose from.
 */
interface MethodAwareInterface
{
    /**
     * The method ids offered at checkout.
     *
     * @return  string[]
     */
    public function getAllowedMethods(): array;

    /**
     * Name of a payment method for the invoice and the Box Office, from the value stored with
     * the transaction (lower case).
     */
    public function methodLabel(string $storedMethod): string;
}
