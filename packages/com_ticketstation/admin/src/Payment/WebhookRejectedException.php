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
 * A webhook call that can't be right and isn't worth repeating: it isn't genuine (a wrong
 * signature, a payment the service doesn't know) or it is incomplete. Ticketstation answers it
 * with HTTP 400. Any other PaymentException from handleWebhook() (the service can't be reached,
 * an error on its side) is answered with HTTP 503, so the service tries again later.
 */
class WebhookRejectedException extends PaymentException
{
}
