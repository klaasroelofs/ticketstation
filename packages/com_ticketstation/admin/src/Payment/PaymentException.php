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
 * A payment provider could not do what was asked (the provider refused it, or could not be
 * reached). The message is meant for the admin and may be shown as it is.
 */
class PaymentException extends \RuntimeException
{
}
