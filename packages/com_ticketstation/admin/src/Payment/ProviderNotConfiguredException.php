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
 * A payment provider has no credentials for the current mode. Apart from PaymentException because
 * its message is complete as it is, where a PaymentException message comes from the provider.
 */
class ProviderNotConfiguredException extends \RuntimeException
{
}
