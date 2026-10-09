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

use Ticketstation\Component\Ticketstation\Administrator\Payment\PaymentException;

/**
 * Stripe doesn't know the object that was asked for.
 */
final class StripeNotFoundException extends PaymentException
{
}
